<?php

namespace BNS\App\FixtureBundle\Command;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Propel;
use BNS\App\HomeworkBundle\Model\Homework,
    BNS\App\HomeworkBundle\Model\HomeworkSubject,
    BNS\App\HomeworkBundle\Model\HomeworkPreferences;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\GroupPeer,
    BNS\App\HomeworkBundle\Model\HomeworkPeer;

/**
 *
 * @author brian.clozel@atos.net
 */
class LoadHomeworkCommand extends ContainerAwareCommand
{

    protected $schoolDays = array('MO', 'TU', 'WE', 'TH', 'FR');

    protected function configure()
    {
        $this
                ->setName('bns:load-homework')
                ->setDescription('Load homework tasks')
                ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'Connexion a utiliser')
        ;
    }

    protected function getConnection(InputInterface $input, OutputInterface $output)
    {
        $propelConfiguration = $this->getContainer()->get('propel.configuration');
        $name = $input->getOption('connection') ? : $this->getContainer()->getParameter('propel.dbal.default_connection');

        if (isset($propelConfiguration['datasources'][$name])) {
            $defaultConfig = $propelConfiguration['datasources'][$name];
        } else {
            throw new \InvalidArgumentException(sprintf('Connection named %s doesn\'t exist', $name));
        }

        $output->writeln(sprintf('Use connection named <comment>%s</comment> in <comment>%s</comment> environment.', $name, $this->getApplication()->getKernel()->getEnvironment()));

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

        $homeworkData = Yaml::parse(__DIR__ . '/../Resources/data/Homework/homework.yml');
        
        $homework_manager = $this->getContainer()->get('bns.homework_manager');

        try {
            $this->con->beginTransaction();

            // Récupération des classes
            $classrooms = GroupQuery::create()
                    ->add(GroupPeer::GROUP_TYPE_ID, 2) // Classroom
                    ->find();

            foreach ($classrooms as $classroom) {


                $groupManager = $this->getContainer()->get('bns.group_manager');
                $groupManager->setGroup($classroom);

                // Création des préférences pour chaque classe
                $prefs = new HomeworkPreferences();
                $prefs->setDays($this->schoolDays);
                $prefs->setActivateValidation(true);
                $prefs->setShowTasksDone(true);
                $prefs->setGroupId($classroom->getId());
                $prefs->save();


                // Création des matières pour chaque classe
                $root = HomeworkSubject::fetchRoot($classroom->getId());
                $subjects = array();

                foreach ($homeworkData['subjects'] as $subject => $subsubjects) {

                    $homeworksubject = new HomeworkSubject();
                    $homeworksubject->setGroupId($classroom->getId());
                    $homeworksubject->setName($subject);
                    $homeworksubject->insertAsLastChildOf($root);
                    $homeworksubject->save();
                    $subjects[] = $homeworksubject;

                    if (is_array($subsubjects)) {
                        foreach ($subsubjects as $subsubject) {
                            $homeworksubsubject = new HomeworkSubject();
                            $homeworksubsubject->setGroupId($classroom->getId());
                            $homeworksubsubject->setName($subsubject);
                            $homeworksubsubject->insertAsLastChildOf($homeworksubject);
                            $homeworksubsubject->save();

                            $subjects[] = $homeworksubsubject;
                        }
                    }
                    $homeworksubject = null;
                }

                // Une seule occurence de devoir, demain
                $hw1 = new Homework();
                $hw1->addGroup($classroom);
                $hw1->setName($this->randomValueIn($homeworkData['homeworks']['name']));
                $hw1->setDescription($this->randomValueIn($homeworkData['homeworks']['description']));
                $hw1->setHelptext($this->randomValueIn($homeworkData['homeworks']['helptext']));
                $hw1->setHomeworkSubject($this->randomValueIn($subjects));
                $hw1->setDate(time() + 86400);
                $hw1->setRecurrenceType(HomeworkPeer::RECURRENCE_TYPE_ONCE);
                $hw1->setRecurrenceDays(null);
                $hw1->setRecurrenceEndDate(null);
                $hw1->save();
                $homework_manager->processHomework($hw1);

                // Toutes les semaines pendant un mois, les lundi et jeudi
                $hw2 = new Homework();
                $hw2->addGroup($classroom);
                $hw2->setName($this->randomValueIn($homeworkData['homeworks']['name']));
                $hw2->setDescription($this->randomValueIn($homeworkData['homeworks']['description']));
                $hw2->setHelptext($this->randomValueIn($homeworkData['homeworks']['helptext']));
                $hw2->setHomeworkSubject($this->randomValueIn($subjects));
                $hw2->setDate(time());
                $hw2->setRecurrenceType(HomeworkPeer::RECURRENCE_TYPE_EVERY_WEEK);
                $hw2->setRecurrenceDays(array('MO', 'TH'));
                $hw2->setRecurrenceEndDate(time() + 31 * 86400);
                $hw2->save();
                $homework_manager->processHomework($hw2);
                
                // Toutes les deux semaines pendant un mois, les lundi et vendredi
                $hw3 = new Homework();
                $hw3->addGroup($classroom);
                $hw3->setName($this->randomValueIn($homeworkData['homeworks']['name']));
                $hw3->setDescription($this->randomValueIn($homeworkData['homeworks']['description']));
                $hw3->setHelptext($this->randomValueIn($homeworkData['homeworks']['helptext']));
                $hw3->setHomeworkSubject($this->randomValueIn($subjects));
                $hw3->setDate(time());
                $hw3->setRecurrenceType(HomeworkPeer::RECURRENCE_TYPE_EVERY_TWO_WEEKS);
                $hw3->setRecurrenceDays(array('MO', 'FR'));
                $hw3->setRecurrenceEndDate(time() + 31 * 86400);
                $hw3->save();
                $homework_manager->processHomework($hw3);

                // Toutes mois, les mardi et jeudi, pendant 3 mois
                $hw4 = new Homework();
                $hw4->addGroup($classroom);
                $hw4->setName($this->randomValueIn($homeworkData['homeworks']['name']));
                $hw4->setDescription($this->randomValueIn($homeworkData['homeworks']['description']));
                $hw4->setHelptext($this->randomValueIn($homeworkData['homeworks']['helptext']));
                $hw4->setHomeworkSubject($this->randomValueIn($subjects));
                $hw4->setDate(time());
                $hw4->setRecurrenceType(HomeworkPeer::RECURRENCE_TYPE_EVERY_MONTH);
                $hw4->setRecurrenceDays(array('TU', 'TH'));
                $hw4->setRecurrenceEndDate(time() + 3 * 31 * 86400);
                $hw4->save();
                $homework_manager->processHomework($hw4);

                // Toutes les semaines pendant un mois, les lundi et mardi
                $hw5 = new Homework();
                $hw5->addGroup($classroom);
                $hw5->setName($this->randomValueIn($homeworkData['homeworks']['name']));
                $hw5->setDescription($this->randomValueIn($homeworkData['homeworks']['description']));
                $hw5->setHelptext($this->randomValueIn($homeworkData['homeworks']['helptext']));
                $hw5->setHomeworkSubject($this->randomValueIn($subjects));
                $hw5->setDate(time());
                $hw5->setRecurrenceType(HomeworkPeer::RECURRENCE_TYPE_EVERY_WEEK);
                $hw5->setRecurrenceDays(array('MO', 'TU'));
                $hw5->setRecurrenceEndDate(time() + 31 * 86400);
                $hw5->save();
                $homework_manager->processHomework($hw5);
                
                // Toutes les semaines pendant deux mois, les mardi et vendredi
                // Commence il y a 3 semaines (test de l'historique)
                $hw6 = new Homework();
                $hw6->addGroup($classroom);
                $hw6->setName($this->randomValueIn($homeworkData['homeworks']['name']));
                $hw6->setDescription($this->randomValueIn($homeworkData['homeworks']['description']));
                $hw6->setHelptext($this->randomValueIn($homeworkData['homeworks']['helptext']));
                $hw6->setHomeworkSubject($this->randomValueIn($subjects));
                $hw6->setDate(time() - 15 * 86400);
                $hw6->setRecurrenceType(HomeworkPeer::RECURRENCE_TYPE_EVERY_WEEK);
                $hw6->setRecurrenceDays(array('TU', 'FR'));
                $hw6->setRecurrenceEndDate(time() + 15 * 86400);
                $hw6->save();
                $homework_manager->processHomework($hw6);
            }

            $this->con->commit();
        } catch (Exception $e) {
            $this->con->rollBack();
            throw $e;
        }
    }

    public function randomValueIn($v)
    {
        return $v[rand(0, count($v) - 1)];
    }

}