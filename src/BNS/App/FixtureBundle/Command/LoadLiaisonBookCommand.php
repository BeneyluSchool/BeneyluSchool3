<?php
namespace BNS\App\FixtureBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use BNS\App\CoreBundle\Model\LiaisonBookSignature;
use Propel;

/**
 *
 * @author ROUAYS Pierre-Luc
 */
class LoadLiaisonBookCommand extends ContainerAwareCommand
{
		
    protected function configure()
    { 
        $this
			->setName('bns:load-liaisonbook')
			->setDescription('Load liaison book\'s events')
   			->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'Connexion a utiliser')
        ;
    }
	
    protected function getConnection(InputInterface $input, OutputInterface $output)
    {
        $propelConfiguration = $this->getContainer()->get('propel.configuration');
        $name = $input->getOption('connection') ?: $this->getContainer()->getParameter('propel.dbal.default_connection');

        if (isset($propelConfiguration['datasources'][$name])) {
            $defaultConfig = $propelConfiguration['datasources'][$name];
        } else {
            throw new \InvalidArgumentException(sprintf('Connection named %s doesn\'t exist', $name));
        }

        $output->writeln(sprintf('Use connection named <comment>%s</comment> in <comment>%s</comment> environment.',
            $name, $this->getApplication()->getKernel()->getEnvironment()));

        return array($name, $defaultConfig);
    }
    
	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output 
	 */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
    	list($connectionName, $defaultConfig) = $this->getConnection($input, $output);
    	$this->con = Propel::getConnection($connectionName);
    	Propel::setForceMasterConnection(true);
    	
    	$content			= file_get_contents(__DIR__ . '/../Resources/data/LiaisonBook/content.txt');
    	$contents			= preg_split('#\r\n#', $content);
    	$countContents                  = count($contents);
    	
    	$title                      = file_get_contents(__DIR__ . '/../Resources/data/LiaisonBook/title.txt');
    	$titles                     = preg_split('#\r\n#', $title);
    	$countTitles                = count($titles);
    	
    	try
    	{
    		$this->con->beginTransaction();

                $liaisonBookManager = $this->getContainer()->get('bns.liaison_book_manager');
                $groupManager = $this->getContainer()->get('bns.group_manager');
                
                $groups = $groupManager->getAllGroups();
                
                //Create liaison books
                foreach($groups as $group)
                {
                    //For each test datas
                    for ($i = 0; $i < $countTitles; $i++)
                    {	
                        $liaisonBookManager->createLiaisonBook(array(
                                    'title' 		=> $titles[$i],
                                    'content' 		=> $contents[$i],
                                    'group_id' 		=> $group->getId(),
                            ));
                    }
                }

                //Create some liaisonBook signatures
                foreach($groups as $group)
                {
                    $usersThatCanSign = $liaisonBookManager->getUsersThatHaveThePermissionInGroup('LIAISONBOOK_ACCESS_SIGN', $group->getId());
                    
                    //Pour chaque carnet de liaison (news)
                    $liaisonBooks = $liaisonBookManager->getLiaisonBooksByGroupId($group->getId());
                    
                    foreach($liaisonBooks as $liaisonBook)
                    {                    
                        //Pour chaque utilisateur pouvant signer
                        foreach($usersThatCanSign as $userThatCanSign)
                        {
                            $randomNb = rand(0, 1);
                            //Une fois de temps en temps
                            if($randomNb == 1)
                            {
                                $userId = $userThatCanSign['id'];
                                $liaisonBookId = $liaisonBook->getId();

                                //Create liaison_book_signature
                                $signature = new LiaisonBookSignature();
                                $signature->setUserId($userId);
                                $signature->setLiaisonBookId($liaisonBookId);
                                $signature->save();
                            }
                        }   
                    }

                }
                
                
                
    		$this->con->commit();
    	}
    	catch (Exception $e)
    	{
    		$this->con->rollBack();
    		throw $e;
    	}
    }
	
}