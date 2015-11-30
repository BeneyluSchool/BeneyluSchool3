<?php

namespace BNS\App\FixtureBundle\Command;

use BNS\App\FixtureBundle\Dumper\YamlDataDumper;
use Propel\PropelBundle\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class FixturesDumpCommand extends AbstractCommand
{
	protected function configure()
    {
        $this
            ->setName('bns:fixtures:dump')
            ->setDescription('Dump all fixtures')
			->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'Connection to use')
			->addArgument('file_query',  InputArgument::REQUIRED, 'The propel file query that you want to dump. Note: use * for joker')
			->addArgument('bundle_dir',  InputArgument::OPTIONAL, 'The bundle directory where the finder will find the TableMap. Ex: AcmeBundle')
        ;
    }

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
		list($conName, $defaultConfig) = $this->getConnection($input, $output);

        $dir = __DIR__ . '/../../' . $input->getArgument('bundle_dir');
        if (!is_dir($dir)) {
            throw new \InvalidArgumentException('The bundle dir "' . $input->getArgument('bundle_dir') . '" is NOT found !');
        }

        $dir = $dir . '/Resources/fixtures';
        if (!is_dir($dir)) {
            mkdir($dir);
        }

        // Override fixtures file ?
        $file = $dir . '/fixtures_data_' . strtolower($input->getArgument('file_query')) . '.yml';
        $dialog = $this->getHelperSet()->get('dialog');
        if (is_file($file)) {
            $choice = null;
            $output->writeln('');
            
            while (null == $choice || !in_array(strtolower($choice), array('y', 'n', 'o'))) {
                $choice = $dialog->ask($output, '    > /!\ Fixtures file already exists ! Do you want to override these fixtures ? (all resource files will be DELETED and uploaded again) [y/n][n] : ', 'n');
            }

            if ('n' == strtolower($choice)) {
                $this->writeSection($output, '    # Fixtures were not generated.');
                return;
            }
        }

        // Deleting files
        $finder = new Finder();
        $files = $finder->files()->in($dir);
        foreach ($files as $path => $doc) {
            unlink($path);
        }

		$dumper = new YamlDataDumper($this->getContainer(), $input, $output, $dialog);
		$dumper->dump($file, $conName);
    }
}