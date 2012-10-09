<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace BNS\App\FixtureBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Yaml\Yaml;
use Propel;

use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Access\BNSAccess;

/**
 * Create and load fixtures for BNS
 *
 * @author Eric Chau <eric.chau@pixel-cookers.com>
 */
class LoadClassroomsCommand extends ContainerAwareCommand
{
    const SCHOOL_COUNT               = 2;
	const CLASSROOM_BY_SCHOOL        = 2;
    const CHILDREN_BY_CLASSROOM      = 2;

    /**
     * @var PropelPDO MySQL connexion
     */
    private $con;

    protected function configure()
    {
        $this
            ->setName('bns:load-classrooms')
            ->setDescription('Load BNS classroom fixtures')
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
	
	protected function writeSection(OutputInterface $output, $text, $style = 'bg=blue;fg=white')
    {
        $output->writeln(array(
            '',
            $this->getHelperSet()->get('formatter')->formatBlock($text, $style, true),
            '',
        ));
    }

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output 
	 */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
		// To get the container
		BNSAccess::setContainer($this->getContainer());
		
        list($connectionName, $defaultConfig) = $this->getConnection($input, $output);
        $this->con = Propel::getConnection($connectionName);
        Propel::setForceMasterConnection(true);

        $firstNames			= file_get_contents(__DIR__ . '/../Resources/data/User/firstname.txt');
        $firstNames			= preg_split('#\r\n#', $firstNames);
        $countFirstNames	= count($firstNames) - 1;

        $emails				= file_get_contents(__DIR__ . '/../Resources/data/User/email.txt');
        $emails				= preg_split('#\r\n#', $emails);
        $countEmails		= count($emails) - 1;

        $lastNames			= file_get_contents(__DIR__ . '/../Resources/data/User/lastname.txt');
        $lastNames			= preg_split('#\r\n#', $lastNames);
        $countLastNames		= count($lastNames) - 1;

        $langs				= file_get_contents(__DIR__ . '/../Resources/data/User/lang.txt');
        $langs				= preg_split('#\r\n#', $langs);
        $countLangs			= count($langs) - 1;
        
        $teamNames          = file_get_contents(__DIR__ . '/../Resources/data/Team/name.txt');
        $teamNames			= preg_split('#\r\n#', $teamNames);
        $countTeamNames		= count($teamNames) - 1;
		$schools = Yaml::parse(__DIR__ . '/../Resources/data/School/school.yml');
		$groupManager = $this->getContainer()->get('bns.group_manager');
		
        try
        {
            //$this->con->beginTransaction();

            // ICI le chargement des classes
			$environmentGroup = GroupQuery::create()->useGroupTypeQuery()->filterByType('ENVIRONMENT')->endUse()->findOneByLabel($this->getContainer()->getParameter('domain_name'));
			if (null == $environmentGroup) {
				throw new \Exception('An environment\'s group must be find with label: '. $this->getContainer()->getParameter('domain_name'));
			}
			
			
			$i = 1;
			while (self::SCHOOL_COUNT >= $i) {	
				//Création des écoles
				$params = array();
				$params['label'] = $schools[$i]['name'];
				$params['type'] = 'SCHOOL';
				$params['validated'] = true;
				$groupManager->createGroup($params);
				$school = $groupManager->getGroup();
				$school->setAttribute('NAME',$schools[$i]['name']);
				$school->setAttribute('UAI',$schools[$i]['uai']);
				$school->setAttribute('ADDRESS',$schools[$i]['address']);
				$school->setAttribute('CITY',$schools[$i]['city']);
				$school->setAttribute('EMAIL',$schools[$i]['email']);
				
				$groupManager->linkGroupWithSubgroup($environmentGroup->getId(),$school->getId());
				
				$j = 0;
				$i++;
				while (self::CLASSROOM_BY_SCHOOL > $j) {
					$j++;
					$params = array();
					$params['label'] = 'Ma classe';
					$params['attributes']['LEVEL'] = array('CE1', 'CE2');
					$params['attributes']['LANGUAGE'] = 'fr';
					$params['validated'] = true;
					$params['group_parent_id'] = $environmentGroup->getId();
					$classroomManagerTeach = $this->getContainer()->get('bns.classroom_manager');
					$classroom_manager_pup = $this->getContainer()->get('bns.classroom_manager');
					$classroom = $classroomManagerTeach->createClassroom($params);
					
					$groupManager->linkGroupWithSubgroup($school->getId(),$classroom->getId());

					// Création d'un enseignant dans la classe et de X élèves
					$teacher = $this->getContainer()->get('bns.user_manager')->createUser(
						array(
							'first_name'    => $firstNames[rand(0, $countFirstNames)],
							'last_name'		=> $lastNames[rand(0, $countLastNames)],
							'email'			=> $emails[rand(0, $countEmails)] . rand(1,999999),
							'username'		=> 'teacher_' . ($j+1),
							'lang'			=> 'fr',
							'birthday'		=> new \DateTime()
						)
					);
				
					$classroomManagerTeach->assignTeacher($teacher);

					// Création d'une équipe par classe
					$teamParams = array(
						'label'             => $teamNames[rand(0, $countTeamNames)],
						'attributes'        => array(),
						'group_parent_id'   => $classroom->getId(),
					);
				
					$this->getContainer()->get('bns.team_manager')->createTeam($teamParams);
					$k = 0;
					while (self::CHILDREN_BY_CLASSROOM > $k)
					{
						
						//Create pupil
						$pupil = $this->getContainer()->get('bns.user_manager')->createUser(
							array(
								'first_name'    => $firstNames[rand(0, $countFirstNames)],
								'last_name'     => $lastNames[rand(0, $countLastNames)],
								'username'      => 'pupil_' . ($i+1),
								'lang'          => 'fr',
								'birthday'      => new \DateTime(),
								'email'         => $emails[rand(0, $countEmails)] . rand(1,999999)
							)
						);

						$classroom_manager_pup->assignPupil($pupil);

//						//Create parent
//						$parent = $this->getContainer()->get('bns.user_manager')->createUser(
//							array(
//								'first_name'    => $firstNames[rand(0, $countFirstNames)],
//								'last_name'     => $lastNames[rand(0, $countLastNames)],
//								'username'      => 'parent_' . ($i+1),
//								'lang'          => 'fr',
//								'birthday'      => new \DateTime(),
//								'email'         => $emails[rand(0, $countEmails)] . rand(1,999999)
//							)
//						);
//						$classroom_manager_pup->assignParent($parent);
//						$classroom_manager_pup->linkPupilWithParent($pupil, $parent);
						$k++;
					}
				}	
			}
            //$this->con->commit();
        }
        catch (Exception $e)
        {
            $this->con->rollBack();
            throw $e;
        }
    }
}