<?php

namespace BNS\App\ResourceBundle\FileSystem;

use Gaufrette\Filesystem,
	Gaufrette\Adapter\Cache as CacheAdapter,
	Gaufrette\Adapter\Local as LocalAdapter,
	Gaufrette\Adapter\S3    as S3Adapter;

/**
 * @author Eymeric Taelman
 * Classe La création et la gestion du FileSystem Gaufrette
 */
class BNSFileSystemManager
{	
	
	protected $localAdapter;
	protected $s3Adapter;
	protected $adapter;
	protected $fileSystem;
	protected $tempDir;
			
	public function __construct($resourceStorage,$localAdapter,$s3Adapter,$tempDir)
	{
		//Selon $resourceStorage on adapate l'adapter (hahaha !)

		//Attention : on partu du postulat (pour la manipulation des fichiers notamment) que l'on aura TOUJOURS un fallback en local
		if($resourceStorage == "local"){
			$adapter = $localAdapter;
		}elseif($resourceStorage == "s3"){
			//Pour S3 cache en local
			$adapter = new CacheAdapter($s3Adapter,$localAdapter,3600);
		}
		$this->adapter = $adapter;
		$this->fileSystem = new Filesystem($adapter);
		$this->tempDir = $tempDir;
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
	 * @return adapter
	 */
	public function getAdapter()
	{
		return $this->adapter;
	}
	
	public function getTempDir()
	{
		return $this->tempDir;
	}
	
		
}