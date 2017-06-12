<?php

namespace BNS\App\StarterKitBundle\Command;

use BNS\App\StarterKitBundle\StarterKit\AbstractStarterKitProvider;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Class StarterKitGenerateJsonFilesCommand
 *
 * @package BNS\App\StarterKitBundle\Command
 */
class StarterKitGenerateJsonFilesCommand extends ContainerAwareCommand
{

    /**
     * @var TranslatorInterface
     */
    private $translator;

    protected function configure()
    {
        $this->setName('bns:starter-kit:generate-json-files')
            ->setDescription('Generate starter kit configuration files, in all supported locales')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $formatter = $this->getHelper('formatter');
        $container = $this->getContainer();
        $this->translator = $container->get('translator');
        $filesystem = $container->get('filesystem');
        $manager = $container->get('bns.starter_kit_manager');
        $locales = $container->getParameter('available_languages');
        $basePath = $container->getParameter('kernel.root_dir') . '/../web/js/starter-kit/';

        $output->writeln('Generate starter kit configuration files :');


        /** @var AbstractStarterKitProvider $provider */
        foreach ($manager->getProviders() as $provider) {
            $output->writeln($formatter->formatBlock([
                '---------------------',
                '  ' . $provider->getName(),
                '---------------------'
            ], 'info'));

            foreach ($locales as $locale) {
                $steps = $provider->getSteps();

                // replace data skeleton with actual translated phrases
                foreach ($steps as $level => &$levelSteps) {
                    foreach ($levelSteps as &$step) {
                        if (isset($step['data'])) {
                            $step['data'] = $this->getTranslatedData($step['data'], $provider->getName(), $step['step'], $locale);
                        }
                    }
                }

                // dump the steps configurations as json
                $filepath = $basePath . '/' . $provider->getName() . '-' . $locale . '.json';
                $filesystem->mkdir(dirname($filepath));
                $filesystem->dumpFile($filepath, json_encode($steps));

                $output->writeln($formatter->formatSection(sprintf('%-5s', $locale), $filepath, 'comment'));
            }
        }

        $output->writeln('Done \o/');
    }

    /**
     * Recursively replaces values in the given array with translated values, corresponding to the given step and locale
     * translated within the given app name domain.
     *
     * @example
     * [ 'title', 'content' ] => [ 'title' => 'Step # title translated', 'content' => 'Step # content translated' ]
     *
     * @param array $data
     * @param string $name
     * @param string $step
     * @param string $locale
     * @return array
     */
    private function getTranslatedData($data, $name, $step, $locale) {
        $translated = [];
        $this->translator->setLocale($locale);

        // scalar given, do nothing
        if (!is_array($data)) {
            return $data;
        }

        foreach ($data as $i => $item) {
            if (is_string($item)) {
                $token = sprintf(
                    'STEP_%s_%s',
                    str_replace(['.','-'], '_', $step),
                    strtoupper($item)
                );
                $translated[$item] = /** @Ignore */ $this->translator->trans($token, [], 'SK_'.$name);
            } else if (is_array($item)) {
                foreach ($item as $key => $subitems) {
                    $translated[$key] = $this->getTranslatedData($subitems, $name, $step, $locale);
                }
            } else {
                // leave other data untouched
                $translated[$i] = $item;
            }
        }

        return $translated;
    }
}
