<?php

namespace BNS\App\StatisticsBundle\Command;

use BNS\App\StatisticsBundle\Model\Marker;
use BNS\App\StatisticsBundle\Model\MarkerQuery;
use Doctrine\Common\Inflector\Inflector;
use Predis\Collection\Iterator\Keyspace;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class StatisticCommand
 * @package BNS\App\StatisticsBundle\Command
 */
class StatisticCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('bns:statistics-safe')
            ->setDescription('Launch cron statistic safe (use redis scan)')
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->launchStatisticCron($input, $output);

        return 0;
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function launchStatisticCron($input, $output)
    {
        // update memory limit
        ini_set('memory_limit', '6000M');
        // prevent memory issue
        \Propel::disableInstancePooling();

        $output->writeln('<info>Launch statistic cron...</info>');
        $redis = $this->getContainer()->get('snc_redis.default');

        $markerList = MarkerQuery::create()->find();

        /** @var Marker $marker */
        foreach ($markerList as $marker) {
            $moduleUniqueName = $marker->getModuleUniqueName();

            $moduleClassName = Inflector::classify(strtolower($moduleUniqueName));
            $moduleUniqueNameCamelCase = "BNS\\App\\StatisticsBundle\\Model\\".$moduleClassName;
            if (!class_exists($moduleUniqueNameCamelCase)) {
                $output->writeln(sprintf('[%s] Error : invalid marker  class name "%s"', $marker->getUniqueName(), $moduleUniqueNameCamelCase));
                continue;
            }

            // get Marker name
            $markerUniqueName = $marker->getUniqueName();

            // We use redis scan command to prevent db lock
            $iterator = new Keyspace($redis, $markerUniqueName . ":*", 100);
            $i = 0;
            $error = 0;
            foreach ($iterator as $key) {
                try {
                    $value = $redis->get($key);
                    $valuesTab = explode(":", $key);
                    if (count($valuesTab) > 2) {
                        // new stat object
                        $object = new $moduleUniqueNameCamelCase();

                        $dateTime = new \DateTime();
                        $date = explode("-", $valuesTab[1]);
                        $hours = explode("-", $valuesTab[2]);
                        $dateTime->setDate($date[0], $date[1], $date[2]);
                        $dateTime->setTime($hours[0], 0, 0);

                        $object->setMarkerId($valuesTab[0]);
                        $object->setDate($dateTime);
                        $object->setGroupId($valuesTab[3]);
                        $object->setRoleId($valuesTab[4]);
                        $object->setValue($value);

                        //si le marqueur possède une info supplémentaire
                        if (isset($valuesTab[5])) {
                            $object->setInfo($valuesTab[5]);
                        } else {
                            $object->setInfo("NULL");
                        }

                        $objectQueryName = $moduleUniqueNameCamelCase."Query";
                        $objectCurrent = $objectQueryName::create()
                            ->filterByMarkerId($object->getMarkerId())
                            ->filterByDate($object->getDate())
                            ->filterByGroupId($object->getGroupId())
                            ->filterByRoleId($object->getRoleId())
                            ->findOne()
                        ;

                        if (!$objectCurrent) {
                            //sauvegarde l'objet en base
                            $object->save();
                        } else {
                            $objectCurrent->setValue($objectCurrent->getValue() + $object->getValue());
                            $objectCurrent->save();
                        }
                    } else {
                        $output->writeln(sprintf('[%s] Error : invalid data "%s"', $marker->getUniqueName(), var_export($value, true)));
                    }

                    $redis->del($key);
                    $i++;
                } catch (\Exception $e) {
                    $output->writeln(sprintf('[%s] Error data "%s" : %s', $marker->getUniqueName(), var_export($value, true), $e->getMessage()));
                    $error++;
                }
            }
            if ($error > 0) {
                $output->writeln(sprintf('Imported "%s" : <info>%s</info> stats lines <error>Error  %s</error>', $marker->getUniqueName(), $i, $error));
            } else {
                $output->writeln(sprintf('Imported "%s" : <info>%s</info> stats lines', $marker->getUniqueName(), $i));
            }
        }

        $output->writeln("<info>End of statistic cron.</info>");
    }
}
