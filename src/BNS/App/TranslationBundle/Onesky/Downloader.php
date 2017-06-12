<?php
namespace BNS\App\TranslationBundle\Onesky;

use Onesky\Api\Client;
use Symfony\Component\Console\Output\OutputInterface;

class Downloader
{
    /** @var Client */
    private $client;

    /** @var int */
    private $projectId;

    /** @var int */
    private $name;

    /** @var Mapping[] */
    private $mappings = array();

    /**
     * @param Client $client
     * @param int    $project
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @param Mapping $mapping
     *
     * @return $this
     */
    public function addMapping(Mapping $mapping)
    {
        $this->mappings[] = $mapping;
        return $this;
    }

    /**
     * @param int $projectId
     * @param string $name
     * @param OutputInterface $output
     * @return $this
     */
    public function download($projectId, $name, OutputInterface $output = null)
    {
        $this->projectId = $projectId;
        $this->name = $name;

        if ($output) {
            $output->write(sprintf('<info>%30s</info>', $this->name));
        }

        $sources = $this->getAllSources();
        $locales = $this->getAllLocales();

        if ($output) {
            foreach ($locales as $locale) {
                $output->write(sprintf('  <comment>%5s</comment>', $locale));
            }
            $output->write('', true);
        }
        foreach ($sources as $source) {
            if ($output) {
                $output->write(sprintf('<comment>%30s</comment>', $source));
            }
            foreach ($locales as $locale) {
                if ($output) {
                    $output->write('     * ');
                }
                $this->dump($source, $locale);
            }
            if ($output) {
                $output->write('', true);
            }
        }

        return $this;
    }


    /**
     * @return array
     */
    private function getAllLocales()
    {
        $raw = $this->client->projects('languages', array('project_id' => $this->projectId));
        $response = json_decode($raw, true);
        $data = $response['data'];

        try {
            return array_map(function ($item) {
                if ($item['region'] != "") {
                    return $item['locale'].'_'.$item['region'];
                } else {
                    return $item['locale'];
                }
            }, $data);
        } catch (\Exception $e) {
            var_dump($raw);

            throw $e;
        }
    }

    /**
     * @return array
     */
    private function getAllSources()
    {
        $raw = $this->client->files('list', array('project_id' => $this->projectId, 'per_page' => 100));
        $response = json_decode($raw, true);

        if ($response['meta']['status'] >= 400) {
            throw new \RuntimeException($response['meta']['message']);
        }

        $data = $response['data'];

        try {
            return array_map(function ($item) {
                return $item['file_name'];
            }, $data);
        } catch (\Exception $e) {
            var_dump($raw);

            throw $e;
        }
    }

    /**
     * @param string $source
     * @param string $locale
     *
     * @return $this
     */
    private function dump($source, $locale)
    {
        $content = null;
        foreach ($this->mappings as $mapping) {
            if (!$mapping->useLocale($locale)) {
                continue;
            }
            if ($content === null) {
                $content = $this->fetch($source, $locale);
            }
            $this->write(($mapping->getOutputFilename($source, $locale, $this->name)), $content);
        }

        return $this;
    }
    /**
     * @param string $source
     * @param string $locale
     *
     * @return mixed
     */
    private function fetch($source, $locale)
    {
        $content = $this->client->translations(
            'export',
            array(
                'project_id' => $this->projectId,
                'locale' => $locale,
                'source_file_name' => $source,
            )
        );

        return $content;
    }
    /**
     * @param $file
     * @param $content
     *
     * @return $this
     */
    private function write($file, $content)
    {
        $this->createFilePath($file);
        file_put_contents($file, $content);

        return $this;
    }
    /**
     * @param string $filename
     *
     * @throws \Exception
     */
    private function createFilePath($filename)
    {
        if (file_exists($filename)) {
            if (!is_writable($filename)) {
                throw new \Exception(sprintf('File path "%s" is not writable', $filename));
            }
            return;
        }
        $dir = dirname($filename);
        if (is_dir($dir)) {
            if (!is_writable($dir)) {
                throw new \Exception(sprintf('Directory "%s" is not writable', $dir));
            }
            return;
        }
        if (!mkdir($dir, 0777, true)) {
            throw new \Exception(sprintf('Unable to create directory "%s"', $dir));
        }
    }
}
