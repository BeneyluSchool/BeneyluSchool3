<?php
namespace BNS\App\CommandBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TranslationGenerateJsonFilesCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('bns:translations:generate-json-files')
            ->setDescription('generate translations files')
            ->setHelp('Execute all cron jobs')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $translator = $container->get('translator');

        if ($container->hasParameter('dual_translation_domains')) {
            $dualTranslationDomains = $container->getParameter('dual_translation_domains');
        } else {
            $dualTranslationDomains = [];
        }

        if (!$container->hasParameter('available_languages')) {
            $locales = array('fr', 'en', 'fr_FR', 'en_US');
        } else {
            $locales = $container->getParameter('available_languages');
        }
        $output->writeln('Generate translations files :');

        foreach ($locales as $locale) {
            $output->write(sprintf('Language : %s', $locale));

            $res = array();
            $messages = $translator->getMessages($locale);

            // get messages of both JS and TWIG domains
            foreach ($messages as $domain => $domainMessages) {
                if (in_array($domain, $dualTranslationDomains)) {
                    $res[$domain] = $domainMessages;
                }
            }

            foreach ($messages as $domain => $domainMessages) {
                // Filter only JS domain
                if (0 === strpos($domain, 'JS_')) {
                    $domain = substr($domain, strlen('JS_'));
                    if (isset($res[$domain])) {
                        // messages from dual file already here: merge
                        $res[$domain] = array_merge($res[$domain], $domainMessages);
                    } else {
                        $res[$domain] = $domainMessages;
                    }
                }
            }

            $output->writeln(sprintf(' with %s domaines', count($res)));

            $filepath = $this->getContainer()->getParameter('kernel.root_dir') . '/../web/js/translations/' . $locale . '.json';
            $this->getContainer()->get('filesystem')->mkdir(dirname($filepath));

            file_put_contents($filepath, json_encode($res));
            $output->writeln(sprintf('Translations written to file "%s"', $filepath));
        }

        $output->writeln('done :)');
    }
}
