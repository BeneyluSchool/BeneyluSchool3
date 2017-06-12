<?php

namespace BNS\App\CoreBundle\Cleaner;

use Symfony\Component\HttpKernel\Event\PostResponseEvent;

/**
 * Class FileCleaner
 */
class FileCleaner
{

    /**
     * Array of file paths to be cleaned
     *
     * @var array
     */
    protected $paths = array();

    /**
     * Adds the given path to the list of files to clean.
     *
     * @param string $path
     */
    public function add($path)
    {
        $this->paths[] = $path;
    }

    /**
     * Cleans the list of files.
     */
    public function clean()
    {
        foreach ($this->paths as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
        $this->paths = array();
    }

    public function onKernelTerminate(PostResponseEvent $event)
    {
        $this->clean();
    }

}
