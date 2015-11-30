<?php

namespace BNS\App\FixtureBundle\Command;

use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\FixtureBundle\Loader\YamlDataLoader;
use Propel\PropelBundle\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class FixturesLoadCommand extends AbstractCommand
{
	protected function configure()
    {
        $this
            ->setName('bns:fixtures:load')
            ->setDescription('Load all fixtures')
			->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'Connection to use')
			->addArgument('group_id',  InputArgument::REQUIRED, 'The group id where the users will be retrieved')
			->addArgument('file_query',  InputArgument::REQUIRED, 'The propel file query that you want to load.')
			->addArgument('bundle_dir',  InputArgument::REQUIRED, 'The bundle name where you want to load fixtures')
			->addOption('delete', null, InputOption::VALUE_NONE, 'This option will delete (clean) related tables before inserts')
        ;
    }

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $group = GroupQuery::create('g')->findPk($input->getArgument('group_id'));
        if (null == $group) {
            throw new \InvalidArgumentException('The group with #ID ' . $input->getArgument('group_id') . ' is NOT found !');
        }
        
        $this->getContainer()->get('fixture.marker_manager')->setGroup($group);
        $this->loadFixtures($input, $output);
    }

	/**
     * Load fixtures
     *
     * @param  InputInterface   $input
     * @param  OutputInterface $output
     * @return void
     */
    protected function loadFixtures(InputInterface $input, OutputInterface $output)
    {
        // Validate and loading fixtures file
		$file = __DIR__ . '/../../' . $input->getArgument('bundle_dir') . '/Resources/fixtures/fixtures_data_' . strtolower($input->getArgument('file_query')) . '.yml';
        if (!is_file($file)) {
            throw new \InvalidArgumentException('The fixtures file for the bundle ' . $input->getArgument('bundle_dir')  . ' is NOT found !');
        }

        if ($input->getOption('delete')) {
            $choice = null;
            $output->writeln('');

            while (null == $choice || !in_array(strtolower($choice), array('y', 'n', 'o'))) {
                $choice = $this->getHelper('dialog')->ask($output, '    > /!\ All related tables for fixtures in "' . $input->getArgument('bundle_dir') . '" will be deleted ! Are you sure to delete this data ? [y/n][n] : ', 'n');
            }

            if ('n' == strtolower($choice)) {
                $this->writeSection($output, '    # Fixtures were not loaded.');
                return;
            }

            $output->writeln('');
        }

        $data = array(
            new \SplFileInfo($file)
        );

		list($conName, $defaultConfig) = $this->getConnection($input, $output);
		$loader = new YamlDataLoader($this->getContainer(), $input, $output);
		$loader->load($data, $conName);

		$this->writeSection($output, '# Fixtures loaded successfully !');

		return true;
    }
}