<?php
namespace BNS\App\FixtureBundle\Command;

use BNS\App\StatisticsBundle\Model\Marker;
use Propel\PropelBundle\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use Propel;

/**
 * @author Florian Rotagnon <florian.rotagnon@atos.net>
 */
class LoadStatisticCommand extends AbstractCommand
{
	/**
	 * @var PropelPDO MySQL connexion
	 */
	private $con;
	
	protected function configure()
    {
        $this
            ->setName('bns:load-statistics')
            ->setDescription('Load all marker')
			->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'Connection to use')
			->setHelp('Creer les marqueurs de statistiques pour chacun des modules')
        ;
    }
	
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		list($connectionName, $defaultConfig) = $this->getConnection($input, $output);
		$this->con = Propel::getConnection($connectionName);
		Propel::setForceMasterConnection(true);
		
		try
		{
			$this->con->beginTransaction();
			
			$statisticsData = Yaml::parse(__DIR__ . '/../Resources/data/Statistic/marker.yml');
			foreach ($statisticsData as $moduleName => $moduleData) {
				foreach ($moduleData as $uniqueName => $data) {
					$marker = new Marker();
					$marker->setModuleUniqueName($moduleName);
					$marker->setUniqueName($uniqueName);
					
					$marker->save();
					
					$marker = null;
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