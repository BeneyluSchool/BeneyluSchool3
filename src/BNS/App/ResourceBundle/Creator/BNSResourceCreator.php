<?php

namespace BNS\App\ResourceBundle\Creator;
use \Exception;
use \stdClass;

use BNS\App\ResourceBundle\Model\Resource;
use BNS\App\ResourceBundle\Model\ResourceLabelGroupQuery;
use BNS\App\ResourceBundle\Model\ResourceLabelUserQuery;
use BNS\App\CoreBundle\Utils\String;
use Imagine\Gd\Imagine,
	Imagine\Image\Box,
	Imagine\Image\ImageInterface;

/**
 * @author Eymeric Taelman
 * Classe permettant la gestion des Ressources
 */
class BNSResourceCreator
{	
	/*
	 * Tailles des tumbnails générés automatiquement
	 */
	private $thumbnails = array(
		'thumbnail'				=> array(60,60),
		'small'					=> array(180,180),
		'favorite'				=> array(300,140),
		'medium'				=> array(600,400),
		'large'					=> array(1200,800),
		'banner_minisite_front'	=> array(1150, 200),
		'banner_minisite_back'	=> array(1150, 200)
	);
	
	
	// Status de création
	 
	const STATUS_FINISHED = '1';
	const STATUS_IN_CREATION = '0';
	
	protected $tools_dir;
	protected $resource_storage;
	protected $resource_manager;
	protected $uploaded_file_dir;	
	
	public function __construct($resource_manager,$image_generator,$uploaded_file_dir)
	{
        $this->resource_manager = $resource_manager;
		$this->image_generator = $image_generator;
		$this->uploaded_file_dir = $uploaded_file_dir;
    }
	
	/*
	 * Shorcuts des methodes du resource_manager
	 */
	public function setObject($object){
		$this->resource_manager->setObject($object);
	}
	public function getObject(){
		return $this->resource_manager->getObject();
	}
	public function getFileSystem(){
		return $this->resource_manager->getFileSystem();
	}
	public function getTempDir(){
		return $this->resource_manager->getTempDir();
	}
	public function getUser(){
		return $this->resource_manager->getUser();
	}
	public function setUser($user){
		$this->resource_manager->setUser($user);
	}
	public function getId(){
		return $this->resource_manager->getId();
	}
	public function getResourceRightManager(){
		return $this->resource_manager->getResourceRightManager();
	}
		
	/*
	 * Ajout des données BDD indispensabes
	 */
	public function createModelDatas($datas){
				
		if(!isset($datas['label']) || !isset($datas['mime_type']) || !isset($datas['type'])){
			throw new Exception("Please provide name, user et type for resources");
		}	
		
		$resource = new Resource();
		
		$resource->setUserId($this->getUser()->getId());
		
		$resource->setLabel($datas['label']);
		if(isset($datas['description']))
			$resource->setDescription($datas['description']);
		
		if(isset($datas['temp']))
			$resource->setStatusCreation(self::STATUS_IN_CREATION);
		else
			$resource->setStatusCreation(self::STATUS_FINISHED);
		
		if(isset($datas['filename']))
			$resource->setFilename($datas['filename']);
		
		$resource->setFileMimeType($datas['mime_type']);
		
		$resource->setTypeUniqueName($datas['type']);
                
                if($this->getUser() != null)
                {
                    $resource->setLang($this->getUser()->getLang());
                }
                    
		$resource->save();
				
		if(isset($datas['destination'])){
			if(isset($datas['destination']['type']) && isset($datas['destination']['object_id']) && isset($datas['destination']['label_id'])){
				$resource->linkLabel($datas['destination']['type'],$datas['destination']['object_id'],$datas['destination']['label_id'],true);
			}
		}
		
		return $resource;
	}
        
	/**
	 * Créer une resource à partir d'un fichier existant
	 * Utilisé par la messagerie "real" pour les pièces jointes
	 */
	public function createResourceFromFile($completePath, $name, $extension, $label) {
        
        $mime_type = $this->extensionToContentType($extension);
        $model_type = $this->getModelTypeFromMimeType($mime_type);
        
        $file = new stdClass();
        $file->name = $name;
        $file->size = intval(filesize($completePath));
        $file->type = $mime_type;
       
        //Checke des données BDD tout d'abord
		
        $datas['label'] = $label;
        $datas['mime_type'] = $mime_type;
        $datas['filename'] = $name;
        $datas['type'] = $model_type;
        
        $resource = $this->createModelDatas($datas);
        $this->setObject($resource);

        if($this->resource_manager->isSizeable($resource)){
			$this->resource_manager->addSize($resource,$file->size);
        }

        $this->setObject($resource);

        //Fin ajouts BDD, ajout fichier
		
        if($file->name)
        {
            //Filepath == clé dans le filesystem
            $file_path = $this->getObject()->getFilePath();
            $this->writeFile($file_path,file_get_contents($completePath));
        }
        
        if($this->resource_manager->isThumbnailable($resource))
        {
            $this->createThumbs();
        }

        
        unlink($completePath);
        
        return $resource;
    }
	
	//////////////   FONCTIONS LIEES A L'ENVOI DE FICHIERS   \\\\\\\\\\\\\\\\\\\
	
	
	public function initFileUploader()
	{	
		$this->upload_options = array(
            'param_name' => 'files',
            'max_file_size' => 20 * 1024 * 1024,
            'min_file_size' => 1,
            'accept_file_types' => '/.+$/i',
            'max_number_of_files' => null,
            'discard_aborted_uploads' => true,
            'orient_image' => false,
            'image_versions' 
        );
	}
    
    protected function get_file_object($file_name) {
        $file_path = $this->upload_options['upload_dir'].$file_name;
        if (is_file($file_path) && $file_name[0] !== '.') {
            $file = new stdClass();
            $file->name = $file_name;
            $file->size = filesize($file_path);
            $file->url = $this->upload_options['upload_url'].rawurlencode($file->name);
            foreach($this->upload_options['image_versions'] as $version => $options) {
                if (is_file($options['upload_dir'].$file_name)) {
                    $file->{$version.'_url'} = $options['upload_url']
                        .rawurlencode($file->name);
                }
            }
            return $file;
        }
        return null;
    }
	
	public function getModelTypeFromMimeType($mime_type){
		
		$type= "";
		
		if(strpos($mime_type,"image/") !== FALSE)
			$type = "IMAGE";
		elseif(strpos($mime_type,"video/") !== FALSE)
			$type = "VIDEO";
		elseif(strpos($mime_type,"audio/") !== FALSE)
			$type = "AUDIO";
		elseif(in_array($mime_type,array(
			'application/pdf',
			'application/vnd.ms-powerpoint',
			'application/vnd.oasis.opendocument.presentation',
			'application/vnd.oasis.opendocument.text',
			'application/vnd.openxmlformats-officedocument.presentationml.presentation',
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'application/msword'
		)))
			$type = "DOCUMENT";
		
		if($type == "")
			$type = "FILE";
		
		
		return $type;	
	}
	
	/*
	 * Action appelée à l'envoi des fichiers
	 */
	public function createFromRequest()
	{
        if (isset($_REQUEST['_method']) && $_REQUEST['_method'] === 'DELETE') {
            return $this->delete();
        }
        $upload = isset($_FILES[$this->upload_options['param_name']]) ?
            $_FILES[$this->upload_options['param_name']] : null;
        $info = array();
        if ($upload && is_array($upload['tmp_name'])) {
            // param_name is an array identifier like "files[]",
            // $_FILES is a multi-dimensional array:
            foreach ($upload['tmp_name'] as $index => $value) {
				$info[] = $this->handle_file_upload(
                    $upload['tmp_name'][$index],
                    isset($_SERVER['HTTP_X_FILE_NAME']) ?
                        $_SERVER['HTTP_X_FILE_NAME'] : $upload['name'][$index],
                    isset($_SERVER['HTTP_X_FILE_SIZE']) ?
                        $_SERVER['HTTP_X_FILE_SIZE'] : $upload['size'][$index],
                    isset($_SERVER['HTTP_X_FILE_TYPE']) ?
                        $_SERVER['HTTP_X_FILE_TYPE'] : $upload['type'][$index],
                    $upload['error'][$index]
                );
            }
        } elseif ($upload || isset($_SERVER['HTTP_X_FILE_NAME'])) {
            // param_name is a single object identifier like "file",
            // $_FILES is a one-dimensional array:
            $info[] = $this->handle_file_upload(
                isset($upload['tmp_name']) ? $upload['tmp_name'] : null,
                isset($_SERVER['HTTP_X_FILE_NAME']) ?
                    $_SERVER['HTTP_X_FILE_NAME'] : (isset($upload['name']) ?
                        $upload['name'] : null),
                isset($_SERVER['HTTP_X_FILE_SIZE']) ?
                    $_SERVER['HTTP_X_FILE_SIZE'] : (isset($upload['size']) ?
                        $upload['size'] : null),
                isset($_SERVER['HTTP_X_FILE_TYPE']) ?
                    $_SERVER['HTTP_X_FILE_TYPE'] : (isset($upload['type']) ?
                        $upload['type'] : null),
                isset($upload['error']) ? $upload['error'] : null
            );
        }
        header('Vary: Accept');
        $json = json_encode($info);
        $redirect = isset($_REQUEST['redirect']) ?
            stripslashes($_REQUEST['redirect']) : null;
        if ($redirect) {
            header('Location: '.sprintf($redirect, rawurlencode($json)));
            return;
        }
        if (isset($_SERVER['HTTP_ACCEPT']) &&
            (strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
            header('Content-type: application/json');
        } else {
            header('Content-type: text/plain');
        }
        echo $json;
    }
	
	protected function handle_file_upload($uploaded_file, $name, $size, $type, $error) {
        $file = new stdClass();
        $file->name = $this->trim_file_name($name, $type);
        $file->size = intval($size);
        $file->type = $type;
        
		
		//Ajout Eymeric, liaison modèle
		//Checke des données BDD tout d'abord
		$label = $_POST['label'][0];
		$description = $_POST['description'][0];
		$destination = $_POST['destination'][0];
		
		$destination = explode('_',$destination);

		$destination_type = $destination[0];
		$destination_object_id = $destination[1];
		$destination_label_id = $destination[2];
		
		if($destination_type == 'group'){
			$destLabel = ResourceLabelGroupQuery::create()->findPk($destination_label_id);
		}elseif($destination_type == 'user'){
			$destLabel = ResourceLabelUserQuery::create()->findPk($destination_label_id);
		}
		//Vérification des erreurs / autorisations
		$error = $this->has_error($uploaded_file, $file, $error, $destLabel,$file->size);
		
		$datas['label'] = $label;
		$datas['description'] = $description;
		$datas['destination']['type'] = $destination_type;
		$datas['destination']['label_id'] = $destination_label_id;
		$datas['destination']['object_id'] = $destination_object_id;
		$datas['mime_type'] = $type;
		$datas['temp'] = true;
		$datas['filename'] = String::filterString($name,true);
		
		$datas['type'] = $this->getModelTypeFromMimeType($type);
		
		//Size du fichier $file->size
		
		$resource = $this->createModelDatas($datas);
		$this->setObject($resource);
		
		if($this->resource_manager->isSizeable($resource)){
			$this->resource_manager->addSize($resource,$file->size);
		}
				
		$this->setObject($resource);
		
		//Fin ajouts BDD, ajout fichier
		
        if(!$error && $file->name)
		{
			//Filepath == clé dans le filesystem
			$file_path = $this->getObject()->getFilePath();
			if ($uploaded_file && is_uploaded_file($uploaded_file))
			{
				$this->writeFile($file_path,file_get_contents($uploaded_file));
            }
			if($this->resource_manager->isThumbnailable($resource))
			{
				$file_size = $this->createThumbs();
			}
		} else {
			$file->error = $error;
		}
        return $file;
    }
	
	public function writeFile($key,$content)
	{
		$this->getFileSystem()->write($key,$content);
	}
	
	public function createThumbs()
	{
		foreach($this->thumbnails as $key => $size){
			$this->createThumb($key);
		}
	}
	
	//Création des thumbs carrés
	public function createThumb($size)
	{ 
		$imagine = new Imagine();
		$params = $this->thumbnails;
		$squared_size = new Box($params[$size][0],$params[$size][1]);
		$mode = ImageInterface::THUMBNAIL_OUTBOUND;
		$path = $this->resource_manager->getAbsoluteFilePath();
		//Création des thumbs en local (repertoire temporaire)
		$tempPath = $this->getTempDir() . '/' . $size .'_' . $this->getObject()->getId() . '.jpeg';
		try{
			$imagine->open($path)->thumbnail($squared_size, $mode)->resize($squared_size)->save($tempPath);
			$this->getFileSystem()->write($this->getObject()->getFilePath($size),  file_get_contents($tempPath));
			unlink($tempPath);
		}catch(Exception $exception){
			
		}
	}
	
	
	protected function has_error($uploaded_file, $file, $error, $destLabel = false, $size = 0) {
        if ($error) {
            return $error;
        }
        if (!preg_match($this->upload_options['accept_file_types'], $file->name)) {
            return 'acceptFileTypes';
        }
        if ($uploaded_file && is_uploaded_file($uploaded_file)) {
            $file_size = filesize($uploaded_file);
        } else {
            $file_size = $_SERVER['CONTENT_LENGTH'];
        }
        if ($this->upload_options['max_file_size'] && (
                $file_size > $this->upload_options['max_file_size'] ||
                $file->size > $this->upload_options['max_file_size'])
            ) {
            return 'maxFileSize';
        }
        if ($this->upload_options['min_file_size'] &&
            $file_size < $this->upload_options['min_file_size']) {
            return 'minFileSize';
        }
        if (is_int($this->upload_options['max_number_of_files']) && (
                count($this->get_file_objects()) >= $this->upload_options['max_number_of_files'])
            ) {
            return 'maxNumberOfFiles';
        }
		//Vérification de la taille autorisée
		if($destLabel){
			if(!$this->getResourceRightManager()->canUpload($destLabel,$size)){
				return 'notAllowed';
			}
		}
        return $error;
	}
	
    protected function get_file_objects() {
        return array_values(array_filter(array_map(
            array($this, 'get_file_object'),
            //TWEAK Eymeric scandir($this->upload_options['upload_dir'])
			array()
        )));
    }

    protected function trim_file_name($name, $type) {
        // Remove path information and dots around the filename, to prevent uploading
        // into different directories or replacing hidden system files.
        // Also remove control characters and spaces (\x00..\x20) around the filename:
        $file_name = trim(basename(stripslashes($name)), ".\x00..\x20");
        // Add missing file extension for known image types:
        if (strpos($file_name, '.') === false &&
            preg_match('/^image\/(gif|jpe?g|png)/', $type, $matches)) {
            $file_name .= '.'.$matches[1];
        }
        return $file_name;
    }

    public function get() {
        $file_name = isset($_REQUEST['file']) ?
            basename(stripslashes($_REQUEST['file'])) : null;
        if ($file_name) {
            $info = $this->get_file_object($file_name);
        } else {
            $info = $this->get_file_objects();
        }
        header('Content-type: application/json');
		//Tweak Eymeric
		$info = array();
        echo json_encode($info);
    }
    
	//Sceptique sur le flip, car plusieurs valeurs identiques
	public function extensionToContentType($value,$revert = false)
	{
		
		$extensionToType = array (
				'ez'        => 'application/andrew-inset',
				'atom'      => 'application/atom+xml',
				'jar'       => 'application/java-archive',
				'hqx'       => 'application/mac-binhex40',
				'cpt'       => 'application/mac-compactpro',
				'mathml'    => 'application/mathml+xml',
				'doc'       => 'application/msword',
				'dat'       => 'application/octet-stream',
				'oda'       => 'application/oda',
				'ogg'       => 'application/ogg',
				'pdf'       => 'application/pdf',
				'ai'        => 'application/postscript',
				'eps'       => 'application/postscript',
				'ps'        => 'application/postscript',
				'rdf'       => 'application/rdf+xml',
				'rss'       => 'application/rss+xml',
				'smi'       => 'application/smil',
				'smil'      => 'application/smil',
				'gram'      => 'application/srgs',
				'grxml'     => 'application/srgs+xml',
				'kml'       => 'application/vnd.google-earth.kml+xml',
				'kmz'       => 'application/vnd.google-earth.kmz',
				'mif'       => 'application/vnd.mif',
				'xul'       => 'application/vnd.mozilla.xul+xml',
				'xls'       => 'application/vnd.ms-excel',
				'xlb'       => 'application/vnd.ms-excel',
				'xlt'       => 'application/vnd.ms-excel',
				'xlam'      => 'application/vnd.ms-excel.addin.macroEnabled.12',
				'xlsb'      => 'application/vnd.ms-excel.sheet.binary.macroEnabled.12',
				'xlsm'      => 'application/vnd.ms-excel.sheet.macroEnabled.12',
				'xltm'      => 'application/vnd.ms-excel.template.macroEnabled.12',
				'docm'      => 'application/vnd.ms-word.document.macroEnabled.12',
				'dotm'      => 'application/vnd.ms-word.template.macroEnabled.12',
				'ppam'      => 'application/vnd.ms-powerpoint.addin.macroEnabled.12',
				'pptm'      => 'application/vnd.ms-powerpoint.presentation.macroEnabled.12',
				'ppsm'      => 'application/vnd.ms-powerpoint.slideshow.macroEnabled.12',
				'potm'      => 'application/vnd.ms-powerpoint.template.macroEnabled.12',
				'ppt'       => 'application/vnd.ms-powerpoint',
				'pps'       => 'application/vnd.ms-powerpoint',
				'odc'       => 'application/vnd.oasis.opendocument.chart',
				'odb'       => 'application/vnd.oasis.opendocument.database',
				'odf'       => 'application/vnd.oasis.opendocument.formula',
				'odg'       => 'application/vnd.oasis.opendocument.graphics',
				'otg'       => 'application/vnd.oasis.opendocument.graphics-template',
				'odi'       => 'application/vnd.oasis.opendocument.image',
				'odp'       => 'application/vnd.oasis.opendocument.presentation',
				'otp'       => 'application/vnd.oasis.opendocument.presentation-template',
				'ods'       => 'application/vnd.oasis.opendocument.spreadsheet',
				'ots'       => 'application/vnd.oasis.opendocument.spreadsheet-template',
				'odt'       => 'application/vnd.oasis.opendocument.text',
				'odm'       => 'application/vnd.oasis.opendocument.text-master',
				'ott'       => 'application/vnd.oasis.opendocument.text-template',
				'oth'       => 'application/vnd.oasis.opendocument.text-web',
				'potx'      => 'application/vnd.openxmlformats-officedocument.presentationml.template',
				'ppsx'      => 'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
				'pptx'      => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
				'xlsx'      => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
				'xltx'      => 'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
				'docx'      => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'dotx'      => 'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
				'vsd'       => 'application/vnd.visio',
				'wbxml'     => 'application/vnd.wap.wbxml',
				'wmlc'      => 'application/vnd.wap.wmlc',
				'wmlsc'     => 'application/vnd.wap.wmlscriptc',
				'vxml'      => 'application/voicexml+xml',
				'bcpio'     => 'application/x-bcpio',
				'vcd'       => 'application/x-cdlink',
				'pgn'       => 'application/x-chess-pgn',
				'cpio'      => 'application/x-cpio',
				'csh'       => 'application/x-csh',
				'dcr'       => 'application/x-director',
				'dir'       => 'application/x-director',
				'dxr'       => 'application/x-director',
				'dvi'       => 'application/x-dvi',
				'spl'       => 'application/x-futuresplash',
				'tgz'       => 'application/x-gtar',
				'gtar'      => 'application/x-gtar',
				'hdf'       => 'application/x-hdf',
				'js'        => 'application/x-javascript',
				'skp'       => 'application/x-koan',
				'skd'       => 'application/x-koan',
				'skt'       => 'application/x-koan',
				'skm'       => 'application/x-koan',
				'latex'     => 'application/x-latex',
				'nc'        => 'application/x-netcdf',
				'cdf'       => 'application/x-netcdf',
				'sh'        => 'application/x-sh',
				'shar'      => 'application/x-shar',
				'swf'       => 'application/x-shockwave-flash',
				'sit'       => 'application/x-stuffit',
				'sv4cpio'   => 'application/x-sv4cpio',
				'sv4crc'    => 'application/x-sv4crc',
				'tar'       => 'application/x-tar',
				'tcl'       => 'application/x-tcl',
				'tex'       => 'application/x-tex',
				'texinfo'   => 'application/x-texinfo',
				'texi'      => 'application/x-texinfo',
				't'         => 'application/x-troff',
				'tr'        => 'application/x-troff',
				'roff'      => 'application/x-troff',
				'man'       => 'application/x-troff-man',
				'me'        => 'application/x-troff-me',
				'ms'        => 'application/x-troff-ms',
				'ustar'     => 'application/x-ustar',
				'src'       => 'application/x-wais-source',
				'xhtml'     => 'application/xhtml+xml',
				'xht'       => 'application/xhtml+xml',
				'xslt'      => 'application/xslt+xml',
				'xml'       => 'application/xml',
				'xsl'       => 'application/xml',
				'dtd'       => 'application/xml-dtd',
				'zip'       => 'application/zip',
				'au'        => 'audio/basic',
				'snd'       => 'audio/basic',
				'mid'       => 'audio/midi',
				'midi'      => 'audio/midi',
				'kar'       => 'audio/midi',
				'mpga'      => 'audio/mpeg',
				'mp2'       => 'audio/mpeg',
				'mp3'       => 'audio/mpeg',
				'aif'       => 'audio/x-aiff',
				'aiff'      => 'audio/x-aiff',
				'aifc'      => 'audio/x-aiff',
				'm3u'       => 'audio/x-mpegurl',
				'wma'       => 'audio/x-ms-wma',
				'wax'       => 'audio/x-ms-wax',
				'ram'       => 'audio/x-pn-realaudio',
				'ra'        => 'audio/x-pn-realaudio',
				'rm'        => 'application/vnd.rn-realmedia',
				'wav'       => 'audio/x-wav',
				'pdb'       => 'chemical/x-pdb',
				'xyz'       => 'chemical/x-xyz',
				'bmp'       => 'image/bmp',
				'cgm'       => 'image/cgm',
				'gif'       => 'image/gif',
				'ief'       => 'image/ief',
				'jpeg'      => 'image/jpeg',
				'jpg'       => 'image/jpeg',
				'jpe'       => 'image/jpeg',
				'png'       => 'image/png',
				'svg'       => 'image/svg+xml',
				'tiff'      => 'image/tiff',
				'tif'       => 'image/tiff',
				'djvu'      => 'image/vnd.djvu',
				'djv'       => 'image/vnd.djvu',
				'wbmp'      => 'image/vnd.wap.wbmp',
				'ras'       => 'image/x-cmu-raster',
				'ico'       => 'image/x-icon',
				'pnm'       => 'image/x-portable-anymap',
				'pbm'       => 'image/x-portable-bitmap',
				'pgm'       => 'image/x-portable-graymap',
				'ppm'       => 'image/x-portable-pixmap',
				'rgb'       => 'image/x-rgb',
				'xbm'       => 'image/x-xbitmap',
				'psd'       => 'image/x-photoshop',
				'xpm'       => 'image/x-xpixmap',
				'xwd'       => 'image/x-xwindowdump',
				'eml'       => 'message/rfc822',
				'igs'       => 'model/iges',
				'iges'      => 'model/iges',
				'msh'       => 'model/mesh',
				'mesh'      => 'model/mesh',
				'silo'      => 'model/mesh',
				'wrl'       => 'model/vrml',
				'vrml'      => 'model/vrml',
				'ics'       => 'text/calendar',
				'ifb'       => 'text/calendar',
				'css'       => 'text/css',
				'csv'       => 'text/csv',
				'html'      => 'text/html',
				'htm'       => 'text/html',
				'txt'       => 'text/plain',
				'asc'       => 'text/plain',
				'rtx'       => 'text/richtext',
				'rtf'       => 'text/rtf',
				'sgml'      => 'text/sgml',
				'sgm'       => 'text/sgml',
				'tsv'       => 'text/tab-separated-values',
				'wml'       => 'text/vnd.wap.wml',
				'wmls'      => 'text/vnd.wap.wmlscript',
				'etx'       => 'text/x-setext',
				'mpeg'      => 'video/mpeg',
				'mpg'       => 'video/mpeg',
				'mpe'       => 'video/mpeg',
				'qt'        => 'video/quicktime',
				'mov'       => 'video/quicktime',
				'mxu'       => 'video/vnd.mpegurl',
				'm4u'       => 'video/vnd.mpegurl',
				'flv'       => 'video/x-flv',
				'asf'       => 'video/x-ms-asf',
				'asx'       => 'video/x-ms-asf',
				'wmv'       => 'video/x-ms-wmv',
				'wm'        => 'video/x-ms-wm',
				'wmx'       => 'video/x-ms-wmx',
				'avi'       => 'video/x-msvideo',
				'ogv'       => 'video/ogg',
				'movie'     => 'video/x-sgi-movie',
				'ice'       => 'x-conference/x-cooltalk',
			);
		
		if(!$revert){
			if(!isset($extensionToType[$value]))
				throw new Exception('This extension is not known');
			return $extensionToType[$value];
		}else{
			$extensionToType = array_flip($extensionToType);
			if(!isset($extensionToType[$value]))
				throw new Exception('This Mime Type is not Know is not known');
			return $extensionToType[$value];
		}
	}
	
	//Pour les resources depuis une URL
	
	public function isValidURL($url)
	{
		return preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $url);
	}

	/**
	 * Pour la prévisualisation
	 * @param string $url L'url a agfficher
	 * @return array données nécessaires
	 */
	public function initDatasFromUrl($url)
	{
		if(!$this->isValidURL($url)){
			return false;
		}
		//On match l'url avec le paser de vidéos
		//La classe renvoie une exception si url vidéo pas matché" => try catch
		$is_video = false;
		try{

			$videopian = new \Videopian();
			$video = $videopian->get($url);
			
			$is_video = true;
			
			//Données
			$title = $video->title;
			
			$description = $video->description;
			$image_url = $video->thumbnails[1]->url;
			
		}catch(Exception $exception){
			//On ne fait rien, c'est un lien
		}
		if(!$is_video){
			//Pour l'instant, c'est un site Internet
			//On récupère informations de base : titre + description
			
			$html_parser = new \simple_html_dom();
			//Try/catch si Url pas bonne
			try{
				$html_parser->load_file($url);
					
				$title = $html_parser->find('title',0)->innertext();
				$description = $html_parser->find('meta[name="description"]',0);
				if($description != null){
					$description = utf8_encode(html_entity_decode($description->getAttribute('content')));
				}

				$image_url = $this->getImageUrlFromLinkUrl($url,true);
			}catch(\Exception $exception){
				throw new Exception($exception->getMessage());
			}
		}
		
		if($is_video){
			$type = "EMBEDDED_VIDEO";
		}else{
			$type = "LINK";
		}
		
		return array('title' => $title,'description' => $description,'url' => $url, 'image_url' => $image_url, 'type' => $type);
	}
	
	
	/*
	 * Création d'une ressource à partir des données fournis dans $datas : url*, title*, description, destination*
	 * @param $datas array()
	 */
	public function createFromUrl($datas)
	{
		if(!isset($datas['url']) || !isset($datas['title']) || !isset($datas['destination']) || !isset($datas['type']) ){
			throw new Exception('Cannot create Ressource from Url, Please provide url, title, type and destination');
		}
		
		$url = $datas['url'];
		
		if(!$this->isValidURL($url)){
			throw new Exception('Cannot create Ressource from Url, this is not a valid URL');
		}
		
		$title = $datas['title'];
		
		if(trim($title) == ""){
			throw new Exception('Cannot create Ressource from Url : Title cannot be blank');
		}
		
		$destination = $datas['destination'];
		
		$destination = explode('_',$destination);
		
		if(count($destination) != 3){
			throw new Exception('Cannot create Ressource from Url : Destination is not correct');
		}
		
		if(isset($datas['description'])){
			$description = $datas['description'];
		}else{
			$description = "";
		}
		//On normalise // createModelDatas
		$cleaned_values = array();
		$cleaned_values['mime_type'] = 'NONE';
		$cleaned_values['label'] = $title;
		$cleaned_values['value'] = $url;
		$cleaned_values['type'] = $datas['type'];
		$cleaned_values['description'] = $description;
		$cleaned_values['destination']['type'] = $destination[0];
		$cleaned_values['destination']['object_id'] = $destination[1];
		$cleaned_values['destination']['label_id'] = $destination[2];
		
		$resource = $this->createModelDatas($cleaned_values);
		
		$this->setObject($resource);
				
		if($datas['type'] == "EMBEDDED_VIDEO"){
			
			$videopian = new \Videopian();
			$video = $videopian->get($url);
			
			$ext = strrchr($video->thumbnails[0]->url,'.');
			
			if(!in_array($ext,array('jpeg','jpg',"png",'gif'))){
				$ext = 'jpeg';
			}
			
			$filePath = $this->getObject()->getFilePath() . $this->getObject()->getSlug() . '.' . $ext;
			
			switch($videopian->getSite()){
				case "youtube":
					$this->getFileSystem()->write($filePath,$this->resource_manager->downloadImage($video->thumbnails[0]->url));
					$value = serialize(array('type' => "youtube","value" => $video->id));
				break;
				case "dailymotion":
					$this->getFileSystem()->write($filePath,$this->resource_manager->downloadImage($video->thumbnails[0]->url));
					$value = serialize(array('type' => "dailymotion","value" => $video->id));	
				break;
				case "vimeo":
					$this->getFileSystem()->write($filePath,$this->resource_manager->downloadImage($video->thumbnails[2]->url));
					$value = serialize(array('type' => "vimeo","value" => $video->id));	
				break;
			}
			
			$this->getObject()->setFileName($this->getObject()->getSlug() . '.' . $ext);
			
			$this->getObject()->setValue($value);
			$this->getObject()->save();
			
		}elseif($datas['type'] == "LINK"){
			$ext = 'jpg';
			$filePath = $this->getObject()->getFilePath() . $this->getObject()->getSlug() . '.' . $ext;
			$this->getObject()->setFileName($this->getObject()->getSlug() . '.' . $ext);
			$this->getFileSystem()->write($filePath,  file_get_contents($this->uploaded_file_dir . '/../' . $this->getImageUrlFromLinkUrl($url)));
			$this->getObject()->setValue($url);
			$this->getObject()->save();
		}
		
		$this->createThumbs();
		
	}
	/**
	 * Génération d'une image depuis une url, l'image est stockée dans /uploads
	 * @param string $url
	 * @param boolean $preview
	 * @return string l'url de l'image
	 */
	public function getImageUrlFromLinkUrl($url,$preview = false){
		$name = md5($url . date('U')) . '.jpg';
		$image_path = $this->uploaded_file_dir . '/' . $name;
		$this->image_generator->generate($url,$image_path,array('height' => 768));
		//Si c'est une preview on la resize
		if($preview){
			$imagine = new Imagine();
			$size = new Box(200,150);
			$mode = ImageInterface::THUMBNAIL_INSET;
			$path = $image_path;
			$imagine->open($path)->thumbnail($size, $mode)->save($path);
		}
		return '/uploads/' . $name;
	}
}