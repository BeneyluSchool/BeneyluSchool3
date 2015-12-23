<?php

namespace BNS\App\ScolomBundle\Command;

use \Propel\PropelBundle\Command\AbstractCommand;
use \Symfony\Component\Console\Input\InputInterface;
use \Symfony\Component\Console\Input\InputOption;
use \Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class ParseVocabularyCommand extends AbstractCommand
{
	protected function configure()
    {
        $this
            ->setName('scolom:parse')
            ->setDescription('Parse vocabulaire ScoLOM-FR file')
			->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'Connection to use')
			->setHelp('This command has been developped only to build more quickly the scolom model')
        ;
    }
	
	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output 
	 */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $file = __DIR__ . DIRECTORY_SEPARATOR . 'file.xml';
        if (!is_file($file)) {
            throw new \RuntimeException('The file is not found !');
        }

        $xmlData = file_get_contents($file);
        $xml = new \SimpleXMLElement($xmlData);
        $choices = array();
        
        foreach ($xml->TERME as $terme) {
            $choices[$terme->ID->__toString()] = array(
                'i18n' => array(
                    'fr' => array(
                        'label'       => $terme->INTITULE->__toString(),
                    )
                )
            );

            if ('' != $terme->NA->__toString()) {
                $tmpChoice = $choices;
                foreach ($tmpChoice[$terme->ID->__toString()]['i18n'] as $lang => $i18n) {
                    $choices[$terme->ID->__toString()]['i18n'][$lang]['description'] = $terme->NA->__toString();
                }
            }
        }

        $yaml = \Spyc::YAMLDump($choices, '2');
        file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'file.yml', $yaml);
	}
}