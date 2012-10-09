<?php
namespace BNS\App\FixtureBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use BNS\App\GPSBundle\Model\GpsCategory;
use BNS\App\GPSBundle\Model\GpsPlace;
use Symfony\Component\Yaml\Yaml;
use Propel;

/**
 *
 * @author Eymeric Taelman
 */
class LoadGPSCommand extends ContainerAwareCommand
{
		
    protected function configure()
    { 
        $this
			->setName('bns:load-gps')
			->setDescription('Load GPS datas')
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
    	
    	$categories			= file_get_contents(__DIR__ . '/../Resources/data/GPS/categories.txt');
    	$categories			= preg_split('#\r\n#', $categories);
    	
		$places			= Yaml::parse(__DIR__ . '/../Resources/data/GPS/places.yml');
		$countPlaces      	= count($places);
	
		
    	try
    	{
    		$this->con->beginTransaction();

			$geocoords_manager = $this->getContainer()->get('bns.geocoords_manager');
			$groupManager = $this->getContainer()->get('bns.group_manager');

			$groups = $groupManager->getAllGroups();

			//Create liaison books
			foreach($groups as $group)
			{
				//For each test datas
				for ($i = 0; $i < 2; $i++)
				{	$used = array();
					$cat = new GpsCategory();
					$cat->setGroupId($group->getId());
					$cat->setLabel($categories[$i]);
					$cat->setIsActive(true);
					$cat->save();
					
					for ($ii = 0; $ii < 3; $ii++)
					{	
						$j = rand(0,$countPlaces - 1);
						if(!in_array($j, $used)){
							$used[] = $j;
							$place = new GpsPlace();
							$place->setGpsCategoryId($cat->getId());
							$place->setLabel($places[$j]['label']);
							$place->setDescription($places[$j]['description']);
							$place->setAddress($places[$j]['address']);
							$place->setIsActive(true);
							$place->save();
							$geocoords_manager->setGeoCoords($place);
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