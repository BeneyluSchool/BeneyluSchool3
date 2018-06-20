<?php

namespace BNS\App\MediaLibraryBundle\FileSystem;

use Gaufrette\Adapter;
use Gaufrette\Filesystem,
	Gaufrette\Adapter\Cache as CacheAdapter,
	Gaufrette\Adapter\Local as LocalAdapter,
	Gaufrette\Adapter\S3    as S3Adapter,
	BNS\App\MediaLibraryBundle\FileSystem\BNSAdapter;
use Gaufrette\StreamWrapper;
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @author Eymeric Taelman
 * Classe La création et la gestion du FileSystem Gaufrette
 */
class BNSFileSystemManager extends ContainerAware
{

	protected $localAdapter;
	protected $s3Adapter;
	protected $adapter;
	protected $fileSystem;
	protected $tempDir;
    public    $resourceStorageType;

    /**
     * BNSFileSystemManager constructor.
     * @param ContainerInterface $container
     * @param Adapter $adapter
     */
	public function __construct($container, $adapter)
	{
		//Attention : on part du postulat (pour la manipulation des fichiers notamment) que l'on aura TOUJOURS un fallback en local
		$this->adapter = $adapter;
		$this->fileSystem = new Filesystem(new BNSAdapter($adapter, $container->get("bns.local.adapter")));
		$this->tempDir = $container->getParameter('kernel.cache_dir');
        // TODO use bns_media_storage
        $this->resourceStorageType = $container->getParameter('bns_media_storage');

        // allow to use gaufrette:// filesystem
        $filesystem = new Filesystem($adapter);
        $map = StreamWrapper::getFilesystemMap();
        $map->set('bns_filesystem', $filesystem);

        StreamWrapper::register();
    }

	/*
	 * @return FileSystem
	 */
	public function getFileSystem()
	{
		return $this->fileSystem;
	}

	/*
	 * @param $adapter
	 */
	public function setAdapter($adapter)
	{
		$this->adapter = $adapter;
	}

	/*
	 * @return Adapter adapter
	 */
	public function getAdapter()
	{
		return $this->adapter;
	}

	public function getTempDir()
	{
		return $this->tempDir;
	}

    public function getResourceStorageType()
    {
        return $this->resourceStorageType;
    }


}
