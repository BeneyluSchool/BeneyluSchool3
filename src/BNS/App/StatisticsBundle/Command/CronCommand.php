<?php

namespace BNS\App\StatisticsBundle\Command;

use \BNS\App\StatisticsBundle\Model\MarkerPeer;

use Doctrine\Common\Inflector\Inflector;
use Propel\PropelBundle\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use \Criteria;

/**
 * La commande CronCommand permet de sauvegarder dans la base de données
 * les informations présentes dans REDIS
 * @author Florian Rotagnon <florian.rotagnon@atos.net>
 */
class CronCommand extends AbstractCommand
{
    /**
     * Configure l'algorithme de sauvegarde
     */
    protected function configure()
    {
        $this
            ->setName('bns:statistics')
            ->setDescription('Launch cron statistic')
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'Connection to use')
            ->setHelp('Statistic: following markers; ')
        ;
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        list($connectionName, $defaultConfig) = $this->getConnection($input, $output);
        $con = \Propel::getConnection($connectionName);
        \Propel::setForceMasterConnection(true);

        try
        {
            $con->beginTransaction();

            $this->launchStatisticCron($input, $output);

            $con->commit();
        }
        catch (\Exception $e)
        {
            $con->rollBack();

            throw $e;
        }
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    private function launchStatisticCron($input, $output)
    {
        $this->writeSection($output, 'Launch statistic cron...');

        //récupère le service REDIS
        $redis = $this->getContainer()->get('snc_redis.default');

        //récupère la liste des marqueurs
        $markerList = MarkerPeer::doSelect(new Criteria());

        //pour chaque marqueur de la liste
        foreach ($markerList as $marker) {
            //récupère le nom du module correspondant au marqueur
            $moduleUniqueName = $marker->getModuleUniqueName();
            //passe le module en CamelCase Et charge son emplacement a la volée
            $moduleClassName = Inflector::classify(strtolower($moduleUniqueName));
            $moduleUniqueNameCamelCase = "BNS\\App\\StatisticsBundle\\Model\\".$moduleClassName;
            //récupère le nom du marqueur
            $markerUniqueName = $marker->getUniqueName();

            //récupère la listes des utilisations de ce marqueur dans REDIS
            $statLineList = $redis->keys($markerUniqueName."*");

            //pour chaque ligne récupère la valeur et l'ajoute en BDD
            foreach ($statLineList as $statLine) {
                //récupère la valeur
                $value = $redis->get($statLine);
                //Découpe les sous valeurs
                $valuesTab = explode(":", $statLine);
                //crée l'objet correspondant
                $object = new $moduleUniqueNameCamelCase();
                //remplie les données
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
                if(count($valuesTab) > 5) {
                    $object->setInfo($valuesTab[5]);
                }
                else {
                    $object->setInfo("NULL");
                }

                $objectQueryName = $moduleUniqueNameCamelCase."Query";
                $existingObject = $objectQueryName::create()
                        ->filterByMarkerId($object->getMarkerId())
                        ->filterByDate($object->getDate())
                        ->filterByGroupId($object->getGroupId())
                        ->filterByRoleId($object->getRoleId())
                        ->findOne();
                if($existingObject == null) {
                    //sauvegarde l'objet en base
                    $object->save();
                }
                else {
                    $existingObject->setValue($existingObject->getValue() + $object->getValue());
                    $existingObject->save();
                }
                //supprime la ligne qui vient d'être traité
                $redis->del($statLine);

            }

        }

        $output->writeln('Start Activation statistics');

        $this->getContainer()->get('bns_group.activation_statistics')->generateAllActivationStatistics();

        $output->writeln('End Activation statistics');

        $this->writeSection($output, "End of statistic cron.");
    }
}
