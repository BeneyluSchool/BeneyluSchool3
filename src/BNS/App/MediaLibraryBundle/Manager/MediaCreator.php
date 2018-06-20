<?php

namespace BNS\App\MediaLibraryBundle\Manager;

use BNS\App\CoreBundle\Antivirus\Clamav;
use BNS\App\MediaLibraryBundle\FileSystem\BNSFileSystemManager;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaFolderGroup;
use BNS\App\NotificationBundle\Notification\MediaLibraryBundle\MediaLibraryNewMediaNotification;
use BNS\App\ResourceBundle\Model\ResourceLabelGroupQuery;
use BNS\App\ResourceBundle\Model\ResourceProvider;
use BNS\App\ResourceBundle\ProviderResource\ProviderResource;
use Buzz\Browser;
use Buzz\Message\Response;
use Exception;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Audio\Mp3;
use Gaufrette\Adapter;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use BNS\App\CoreBundle\Access\BNSAccess;
use Imagine\Image\Palette\RGB;
use Imagine\Image\ImageInterface;
use Imagine\Image\Metadata\ExifMetadataReader;
use Imagine\Image\Point;
use Imagine\Filter\Basic\Autorotate;
use Sensio\Bundle\BuzzBundle\SensioBuzzBundle;
use Psr\Log\LoggerInterface;
use Shaarli\NetscapeBookmarkParser\NetscapeBookmarkParser;
use stdClass;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Validator\Constraints\Ip;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\ValidatorBuilder;

/**
 * @author Eymeric Taelman
 * Classe permettant la gestion des medias
 */
class MediaCreator
{
	/*
	 * Tailles des tumbnails générés automatiquement
	 */
	public $thumbnails = array(
        'micro'		     		=> array(30,30),
		'thumbnail'				=> array(60,60),
        'board'                 => array(100, 100),
		'small'					=> array(180,180),
		'favorite'				=> array(300,140),
		'medium'				=> array(600,400),
		'large'					=> array(1200,800),
		'banner_minisite_front'	=> array(1150, 200),
		'banner_minisite_back'	=> array(1150, 200),
        'portal_banner'     	=> array(770, 190)
	);


	// Status de création

	const STATUS_FINISHED = '1';
	const STATUS_IN_CREATION = '0';

    const ERROR_NOT_ENOUGH_SPACE_GROUP = "ERROR_NOT_ENOUGH_SPACE_GROUP";
    const ERROR_NOT_ENOUGH_SPACE_USER = "ERROR_NOT_ENOUGH_SPACE_USER";
    const ERROR_DURING_WRITE = "ERROR_DURING_WRITE";
    const ERROR_NO_ALLOWED_SPACE = "ERROR_NO_ALLOWED_SPACE";

	protected $resource_storage;
	protected $resourceDirectory;
    /** @var  MediaManager $mediaManager */
	protected $mediaManager;
    protected $imageGenerator;
    /** @var  Browser $buzz */
    protected $buzz;

    protected $uploadedFileDir;
    protected $uploadedFileTempPath;

    protected $logger;
    protected $clamavAntivirus;

    /** @var FFMpeg */
    protected $ffmpeg;
    protected $libreofficeDirectory;

    /** @var $mediaFolderManager */
    protected $mediaFolderManager;

    protected $logDirectory;

	public function __construct($mediaManager, LoggerInterface $logger, $imageGenerator, $buzz, $cacheDir, $resourceDirectory, FFMpeg $ffmpeg, MediaFolderManager $mediaFolderManager, Clamav $clamavAntivirus, $libreofficeDirectory, $logDirectory)
	{
		$this->mediaManager = $mediaManager;
		$this->image_generator = $imageGenerator;
        $this->buzz = $buzz;
		$this->uploaded_file_dir = $cacheDir;
        $this->logger = $logger;
        $this->resourceDirectory = $resourceDirectory;
        $this->ffmpeg = $ffmpeg;
        $this->mediaFolderManager = $mediaFolderManager;
        $this->clamavAntivirus = $clamavAntivirus;
        $this->libreofficeDirectory = $libreofficeDirectory;
        $this->logDirectory = $logDirectory;
	}

    public function getUploadFileDir()
    {
        return $this->uploadedFileDir;
    }

    protected function setUploadedFileTempPath($path)
    {
        $this->uploadedFileTempPath = $path;
    }

    protected function getUploadedFileTempPath()
    {
        return $this->uploadedFileTempPath;
    }
	/*
	 * Shorcuts des methodes du media_manager
	 */
	public function setObject($object)
	{
		$this->mediaManager->setMediaObject($object);
	}

	public function getObject()
	{
		return $this->mediaManager->getMediaObject();
	}

	public function getFileSystem()
	{
		return $this->mediaManager->getFileSystem();
	}

    public function getMediaManager()
    {
        return $this->mediaManager;
    }

    public function getUserManager()
    {
        return $this->getMediaManager()->getUserManager();
    }

    public function getGroupManager()
    {
        return $this->getMediaManager()->getGroupManager();
    }

	public function getTempDir()
	{
		return $this->mediaManager->getTempDir();
	}

    /**
     * Creates Medias from request files
     * @param $mediaFolder
     * @param $userId
     * @param Request $request
     * @param bool $convert
     * @param bool $notify
     * @return array|Media[]
     * @throws Exception
     */
    public function createFromRequest($mediaFolder, $userId, Request $request, $convert = false, $notify = true)
    {
        // Tableau des medias créées, pour l'instant un à la fois
        $return = array();

        // récupération des informations du fichier
        /** @var UploadedFile $uploadedFile */
        foreach($request->files as $uploadedFile) {
            // TODO : use symfony File validator to return more error message

            // Check upload was OK
            if (!$uploadedFile->isValid()) {
                throw new FileException($this->getErrorMessage($uploadedFile->getError()));
            }
            // Check uploaded file is really there
            $realPath = $uploadedFile->getRealPath();
            if (!is_file($realPath)) {
                throw new FileException(self::ERROR_DURING_WRITE);
            }
            // give right so the file can be analyzed by clamav
            chmod($realPath, 0644);
            $clamav = $this->clamavAntivirus->isFileSafe($realPath, true);
            if (!$clamav) {
                throw new BadRequestHttpException();
            }

            $size = $uploadedFile->getSize();

            $filename = $uploadedFile->getClientOriginalName();
            $extension = $uploadedFile->getClientOriginalExtension();
            $mimeType = $this->getRealMimeType($uploadedFile);
            if (!$extension) {
                if ($mimeType) {
                    $split = explode('/', $mimeType);
                    if (isset($split[1])) {
                        $extension = $split[1];
                    }
                }
            }
            $this->logger->debug('Convert file ?', [
                'filename' => $filename,
                'convert' => $convert,
                'exstension' => $extension,
                'mimeType' => $mimeType
            ]);
            if ($extension === 'html' && strpos(file_get_contents($realPath), 'NETSCAPE')) {
                $parser = new NetscapeBookmarkParser(true, [], '0', $this->logDirectory);
                $bookmarks = $parser->parseFile($realPath);
                if (count($bookmarks)) {
                    $folder = $this->mediaFolderManager->create('Dossier de signets ' . date('d-m-Y', strtotime('now')), $mediaFolder->getId(), $mediaFolder->getType());
                    foreach ($bookmarks as $bookmark) {
                        $link = $this->createFromUrl($folder, $userId, $bookmark['uri']);
                        $return[] = $link;
                    }
                }
                continue;
            }
            // convert ogg files to mp3
            if ($convert && 'ogg' === $extension) {
                $src = $realPath;
                $dest = $src.'.mp3';
                $audio = $this->ffmpeg->open($src);
                $format = new Mp3();
                $format->setAudioKiloBitrate(64);
                $audio->save($format, $dest);

                // update future media info
                $realPath = $dest;
                $filename = str_replace('.ogg', '.mp3', $filename);
                $extension = 'mp3';
                $mimeType = 'audio/mp3';
                $size = filesize($realPath);
            }

            $informations = array(
                'label' => $filename,
                'user_id' => $userId,
                'media_folder' => $mediaFolder,
                'description' => $request->get('description'),
                'type' => $this->getModelTypeFromMimeType($mimeType),
                'mime_type' => $mimeType,
                'size' => $size,
                'filename' => $this->cleanupFileName(pathinfo($filename, PATHINFO_FILENAME)) . '.' . $extension
            );

            // Avant de créer les données nous vérifions qu'il a assez d'espace disque
            $this->checkSize($mediaFolder, $size, $userId);

            $media = $this->createModelDatas($informations);

            if ($media->isImage() && !in_array($extension, ['png', 'gif'])) {
                //On applique une rotation avec Imagine pour cadrer toujours dans le bon sens
                $transformation = new Autorotate();
                $imagine = new Imagine();
                $imagine->setMetadataReader(new ExifMetadataReader());
                $transformation = $transformation->apply($imagine->open($realPath));
                //Imagine ne sait pas sauvegarder sur du .tmp
                $realPath .= '.jpg';
                $transformation->save($realPath);
            }

            $this->writeFile($media->getFilePath(), file_get_contents($realPath));
            if ($media->isDocument() && $media->getFileMimeType() !== 'application/pdf') {
                $this->convertToPDF($realPath, $media->getFilePathPattern(), $media->getFilename(), true);
            }
            // remove tmp file
            @unlink($realPath);

            // notify teachers + stats
            if ($notify) {
                $um = $this->getUserManager();
                $um->setUserById($userId);

                if($um->isChild())
                {
                    $finalUsers = array();
                    foreach($um->getGroupsUserBelong('CLASSROOM') as $classroom)
                    {
                        $gm = $this->getGroupManager();
                        $gm->setGroup($classroom);
                        foreach($gm->getUsersByRoleUniqueName('TEACHER',true) as $teacher)
                        {
                            $finalUsers[] = $teacher;
                        }
                    }
                    if(count($finalUsers) > 0)
                    {
                        BNSAccess::getContainer()->get('notification_manager')->send($finalUsers, new MediaLibraryNewMediaNotification(BNSAccess::getContainer(), $media->getId()));
                    }
                }

                BNSAccess::getContainer()->get("stat.media_library")->newFile();
            }

            $return[] = $media;
        }

        return $return;
    }

    public function convertToPDF($pathToCopy, $directory, $filename, $rename = true)
    {
        $process = new Process($this->libreofficeDirectory . ' --headless --convert-to pdf "' . $pathToCopy . '" -env:UserInstallation=file:///tmp/test --outdir /tmp/');
        $process->setTimeout(10);
        $process->run();
        if ($process->isSuccessful()) {
            if ($rename) {
                $extension = pathinfo($filename, PATHINFO_EXTENSION);
                $file = $pathToCopy . '.pdf';
            } else {
                $extension = pathinfo($pathToCopy, PATHINFO_EXTENSION);
                $file = '/tmp/' . str_replace( $extension, 'pdf', $filename);
            }
            $this->writeFile( $directory . str_replace( $extension, 'pdf', $filename), file_get_contents($file));
            @unlink($file);
        }
        if (!$process->isSuccessful()) {
            return false;
        }
    }

    public function getRealMimeType(UploadedFile $uploadedFile)
    {
        $filename = $uploadedFile->getClientOriginalName();
        $mimeType = $uploadedFile->getMimeType();
        if (!$mimeType) {
            $mimeType = $uploadedFile->getClientMimeType();
        }
        $mimeTypeModel = $this->getModelTypeFromMimeType($mimeType);
        if (empty($mimeTypeModel) || $mimeTypeModel === 'FILE') {
            if (pathinfo($filename, PATHINFO_EXTENSION) === 'pdf') {
                $mimeType = 'application/pdf';
            }
            if (in_array(pathinfo($filename, PATHINFO_EXTENSION), ['ogg', 'mp3'])) {
                $mimeType = 'audio/mp3';
            }
        }
        if ($mimeTypeModel === 'AUDIO' && in_array(pathinfo($filename, PATHINFO_EXTENSION), ['mp4', 'MP4'])) {
            $mimeType = 'video/mp4';
            $mimeTypeModel = 'VIDEO';
        }

        return $mimeType;
    }

    /**
     * @param $mediaFolder
     * @param Request $request
     */
    public function createFromUrl($mediaFolder, $userId, $url, $force = false, $forcedFilename = null)
    {
        // impersonate a real browser, for subsequent file_get_contents calls on urls
        ini_set('user_agent', 'Mozilla/5.0 (Windows NT 6.3; rv:36.0) Gecko/20100101 Firefox/36.0');

        /** @var MediaFolderGroup $mediaFolder  */

        if(strpos($url,'https://') === false && strpos($url,'http://') === false)
        {
            $url = 'http://' . $url;
        }

        if (!$this->isValidURL($url)) {
            throw new HttpException(\Symfony\Component\HttpFoundation\Response::HTTP_BAD_REQUEST, 'Url invalide');
        }

        //On détermine le type depuis l'Url
        $buzz = $this->buzz;
        /** @var Response $return */
        $return = $buzz->get($url, [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 6.3; rv:36.0) Gecko/20100101 Firefox/36.0'
        ]);
        $contentTypeHeader = $return->getHeader('Content-Type');
        //Prise en compte des content_type avec ';'
        if (strpos($contentTypeHeader, ';')) {
            $contentType = strstr($contentTypeHeader, ';', true);
        } else {
            $contentType = $contentTypeHeader;
        }

        if (!$return->isSuccessful()) {
            throw new Exception('Url invalide');
        }
        $type = $this->getModelTypeFromMimeType($contentType);
        $value = null;


        if (in_array($contentType, ['text/html', 'application/xhtml+xml'])) {
            $type = 'LINK';
            $is_video = false;
            $filename = null;
            $size = 0;
            //test di c'est un service de vidéos
            $videopian = new \Videopian();
            try {
                $video = $videopian->get($url);
                $type = "EMBEDDED_VIDEO";
                $is_video = true;
                // Données
                $label = $video->title;
                $description = $video->description;
                $imageUrl = $video->thumbnails[0]->url;
                $ext = strrchr($video->thumbnails[0]->url,'.');

                if(!in_array($ext,array('jpeg','jpg',"png",'gif'))){
                    $ext = 'jpeg';
                }

                switch($videopian->getSite()){
                    case "youtube":
                        $value = serialize(array('type' => "youtube","value" => $video->id));
                        break;
                    case "dailymotion":
                        $value = serialize(array('type' => "dailymotion","value" => $video->id));
                        break;
                    case "vimeo":
                        $value = serialize(array('type' => "vimeo","value" => $video->id));
                        break;
                }
            }
            catch (\Videopian_Exception $exception) {
                // On ne fait rien, c'est un lien
            }

            if (!$is_video) {
                // Pour l'instant, c'est un site Internet
                // On récupère informations de base : titre + description
                $html_parser = new \simple_html_dom();
                // Try/catch si Url pas bonne
                try {
                    $value = $url;
                    $html_parser->load_file($url);
                    $titleDom = $html_parser->find('title', 0);

                    if (null == $titleDom) {
                        throw new \Exception('Element not found');
                    }

                    $label = $titleDom->innertext();
                    $description = $html_parser->find('meta[name="description"]', 0);

                    if (null != $description) {
                        $description = utf8_encode(html_entity_decode($description->getAttribute('content')));
                    }
                }
                catch (\Exception $exception) {
                    $label = $url;
                    $description = "";
                }

                try {
                    $imageUrl = $this->getImageUrlFromLinkUrl($url);
                }
                catch (\Exception $exception) {
                    $imageUrl = null;
                }
            }

        } else {
            //C'est un fichier
            $description = null;
            if (!$forcedFilename) {
                $filename = substr(strrchr($url, '/'),1);
                $parts = explode('?', $filename);
                $filename = $parts[0];
                $parts = explode('&', $filename);
                $filename = $parts[0];

                // fix for when URL file name does not contain extension
                $ext = strrchr($filename,'.');
                if (!$ext || strlen($ext) > 4) {
                    $ext = explode('/', $contentType);
                    $ext = $ext[1];
                    $filename .= '.'.$ext;
                }
            } else {
                $filename = $forcedFilename;
            }

            $label = $filename;
            $size = $return->getHeader('Content-Length');
            $value = null;
            if(!$force)
            {
                $this->checkSize($mediaFolder, $size, $userId);
            }
            $fileUrl = $url;
        }



        //Tableau des medias créées, pour l'instant un à la fois

        /** @var UploadedFile $uploadedFile */
        $informations = array(
            'label' => $label,
            'user_id' => $userId,
            'media_folder' => $mediaFolder,
            'type' => $type,
            'mime_type' => $contentType,
            'size' => $size,
            'value' => $value,
            'description' => $description,
            'filename' => $filename
        );

        $media = $this->createModelDatas($informations, $force);

        if(isset($imageUrl))
        {
            $this->writeFile($media->getFilePath() . '/' . $media->getSlug() . '.jpg',file_get_contents($imageUrl));
            $media->setFilename($media->getSlug() . '.jpg');
            $media->save();
        }elseif(isset($fileUrl)){
            $this->writeFile($media->getFilePath(),file_get_contents($fileUrl));
        }

        return $media;
    }

	/*
	 * Données à insérer en base de données
	 * @param $datas : tableau des données à fournir, * = indispensable
	 *
	 *  - label* = Nom du media
	 *  - media_folder* = mediaFolder de destination (Objet Propel)
	 *  - mime_type = MimeType
	 *  - type = type parmis les type de médias gérés
	 *  - user_id = User créateur du média
	 *  - size = taille en octets du document
	 *  - description = description (BDD) du document
	 *  - temp = si setté, le média est en cours de fabrication( pour les vidéos par exemple)
	 *  - filename = nom du fichier informatique
	 *  - value = utilisé pour les urls par exemple
	 *
	 */
	public function createModelDatas($datas, $force = false)
	{
        if (!isset($datas['label']) || !isset($datas['media_folder'])) {
            throw new Exception("Erreur : il faut fournir au moins le nom du document et sa destination");
        }

        $media = new Media();
        $media->setLabel($datas['label']);
        /** @var MediaFolderGroup $mediaFolder */
        $mediaFolder = $datas['media_folder'];
        $media->setMediaFolderId($mediaFolder->getId());
        $media->setMediaFolderType($mediaFolder->getType());

        switch($mediaFolder->getType())
        {
            case "USER":
                $media->setIsPrivate(true);
                break;
            case "GROUP":
                $media->setIsPrivate(false);
                break;
        }

        if (isset($datas['filename'])) {
            $media->setFilename($datas['filename']);
        }

        if(isset($datas['user_id']))
        {
            $media->setUserId($datas['user_id']);
        }

        if(isset($datas['mime_type']))
        {
            if($datas['mime_type'] == "application/octet-stream")
            {
                if(strrchr($media->getFilename() ,'.') == '.flv')
                {
                    $datas['mime_type'] = 'video/x-flv';
                    $datas['type'] = 'VIDEO';
                }
            }

            $media->setFileMimeType($datas['mime_type']);
            //Si type n'est pas donné on le devinne depuis le mime_type
            if (!isset($datas['type'])) {
                $media->setTypeUniqueName($this->getModelTypeFromMimeType($media->getFileMimeType()));
            }
        }

        if(isset($datas['type']))
        {
            $media->setTypeUniqueName($datas['type']);
        }

        if(isset($datas['size'])) {
            $media->setSize($datas['size']);
        }

        if (isset($datas['description'])) {
            $media->setDescription($datas['description']);
        }

        if (isset($datas['value'])) {
            $media->setValue($datas['value']);
        }

        if (isset($datas['temp'])) {
            $media->setStatusCreation(self::STATUS_IN_CREATION);
        }
        else {
            $media->setStatusCreation(self::STATUS_FINISHED);
        }



        $media->setLang('fr');

        $media->save();

        $this->mediaManager->setMediaObject($media);
        $this->mediaManager->move($mediaFolder, true, $force);

        return $media;
	}

    /**
     * Fonction pour ajouter de l'utilisation disque à un folder
     * @param $mediaFolder
     * @param $size en octets
     */
    public function addSize($mediaFolder, $size)
    {
        switch($mediaFolder->getType())
        {
            case 'USER':
                $mediaFolder->getUser()->addResourceSize($size);
                break;
            case 'GROUP':
                $mediaFolder->getGroup()->addResourceSize($size);
                break;
        }
    }

    /**
     * Fonction pour supprimer de l'utilisation disque à un folder
     * @param $mediaFolder
     * @param $size en octets
     */
    public function removeSize($mediaFolder, $size)
    {
        switch($mediaFolder->getType())
        {
            case 'USER':
                $mediaFolder->getUser()->deleteResourceSize($size);
                break;
            case 'GROUP':
                $mediaFolder->getGroup()->deleteResourceSize($size);
                break;
        }
    }

    /**
     * Vérifie qe l'on peut déposer le document en question
     * @param $mediaFolder
     * @param $size en octets
     * @param $userId
     * @throws \Symfony\Component\HttpFoundation\File\Exception\FileException
     */
    public function checkSize($mediaFolder, $size, $userId)
    {
        switch($mediaFolder->getType())
        {
            case 'USER':
                $this->getUserManager()->setUserById($mediaFolder->getUserId());
                if($this->getUserManager()->getAvailableSize() < $size)
                {
                    if (0 == $this->getUserManager()->getRessourceAllowedSize()) {
                        throw new FileException(self::ERROR_NO_ALLOWED_SPACE);
                    }
                    throw new FileException(self::ERROR_NOT_ENOUGH_SPACE_USER);
                }
                break;
            case 'GROUP':
                $this->getGroupManager()->setGroupById($mediaFolder->getGroupId());
                if($this->getGroupManager()->getAvailableSize() < $size)
                {
                    throw new FileException(self::ERROR_NOT_ENOUGH_SPACE_GROUP);
                }
                break;
        }
    }

    /**
     * OK - vérifié
     * Renvoie le type de document BDD depuis un mime type
     * @param $mimeType
     * @return string
     *
     */
    public function getModelTypeFromMimeType($mimeType)
    {
        $type = "";
        if (strpos($mimeType, "image/") !== FALSE) {
            $type = "IMAGE";
            if(strpos($mimeType, "bmp") !== FALSE || strpos($mimeType, "tif") !== FALSE ){
                $type = "FILE";
            }
        }
        elseif (strpos($mimeType, "video/") !== FALSE) {
            $type = "VIDEO";
        }
        elseif (strpos($mimeType, "audio/") !== FALSE) {
            $type = "AUDIO";
        }
        elseif (in_array($mimeType, array(
            'application/pdf',
            'application/vnd.ms-powerpoint',
            'application/vnd.oasis.opendocument.presentation',
            'application/vnd.oasis.opendocument.text',
            'application/vnd.oasis.opendocument.spreadsheet',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/msword',
            'application/vnd.ms-excel',
        ))) {
            $type = "DOCUMENT";
        }
        if ($type == "") {
            $type = "FILE";
        }
        return $type;
    }

    /**
     * Génération d'une image depuis une url, l'image est stockée dans /uploads
     * @param string $url
     * @param boolean $preview
     * @return string l'url de l'image
     */
    public function getImageUrlFromLinkUrl($url){
        $name = md5($url . date('U')) . '.jpg';
        $image_path = $this->uploaded_file_dir . '/' . $name;
        $this->image_generator->generate($url,$image_path,array('height' => 768));
        return $this->uploaded_file_dir . '/' . $name;
    }


    public function writeFile($key, $content)
    {
        try{
            $this->getFileSystem()->write($key, $content);
        }catch (\Exception $e){

            throw new FileException(self::ERROR_DURING_WRITE, 0, $e);
        }
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

        $imageInfo = @getimagesize($this->mediaManager->getAbsoluteFilePath());
        if (false === $imageInfo) {
            //L'image n'est pas présente dans le FileSystem Local, on prend l'uploadedFilePath
            $imageInfo = @getimagesize($this->getUploadedFileTempPath());
            if (false === $imageInfo) {
                return false;
            }else{
                $imagePath = $this->getUploadedFileTempPath();
            }
        }else{
            $imagePath = $this->mediaManager->getAbsoluteFilePath();
        }

        $largeur = $imageInfo[0]; // largeur de l'image
        $hauteur = $imageInfo[1]; // hauteur de l'image
        $ratio = $largeur / $hauteur;

        $palette = new RGB();
        $white = $palette->color(array(255, 255, 255));

        //Si l'image est strictement moins large ET moins haute que le thumbnail demandé alors
        //On crée un thumbnail blanc avec l'image au milieu non redimmensionnée
        if($params[$size][0] > $largeur && $params[$size][1] > $hauteur)
        {
            //Remplir de blanc le thumbnail
            $squared_size = new Box($params[$size][0],$params[$size][1]);
            $color = $white;

            //Création du rendu final
            $final = $imagine->create($squared_size, $color);

            //On ouvre l'image
            try {
                $image = $imagine->open($imagePath);
            }
            catch (\Exception $e) {
                return $this->logger->error($e->getMessage());
            }

            //On la colle au milieu du rendu final
            $x = ($params[$size][0] / 2) - ($largeur / 2);
            $y = ($params[$size][1] / 2) - ($hauteur / 2);

            try {
                $final->paste($image, new Point($x, $y));

                // Puis on sauvegarde
                $tempPath = $this->getTempDir() . '/' . $size .'_' . $this->getObject()->getId() . '.jpeg';
                // Création des thumbs en local (repertoire temporaire)
                $final->save($tempPath);
            } catch(Exception $exception) {
                return $this->logger->error($exception->getMessage());
            }
        }
        //Si l'image est strictement moins large OU moins haute que le thumbnail demandé alors
        //On doit redimmensionner en pourcentage pour que l'image rentre correctement dans le bandeau
        else if($params[$size][0] > $largeur || $params[$size][1] > $hauteur)
        {
            //Pourcentage de resize
            $percent_resize = 100;
            if($params[$size][0] < $largeur)
            {
                $percent_resize = $params[$size][0] / $largeur;
            }
            else
            {
                $percent_resize = $params[$size][1] / $hauteur;
            }

            //Remplir de blanc
            $squared_size = new Box($params[$size][0],$params[$size][1]);
            $color = $white;

            //Création du rendu final
            $final = $imagine->create($squared_size, $color);

            //On ouvre l'image
            try {
                $image = $imagine->open($imagePath);
            }
            catch (\Exception $e) {
                return $this->logger->error($e->getMessage());
            }

            $mode = ImageInterface::THUMBNAIL_OUTBOUND;
            //Taille de l'image redimmensionnée proportionnellement
            $resized_image_size = new Box($largeur*$percent_resize, $hauteur*$percent_resize);

            //On colle l'image au milieu
            $x = ($params[$size][0] / 2) - ($largeur*$percent_resize / 2);
            $y = ($params[$size][1] / 2) - ($hauteur*$percent_resize / 2);

            // Corrige un bug image outofbound problème de précision
            if ($x < 0) {
                $x = 0;
            }
            if ($y < 0) {
                $y = 0;
            }

            try {
                // Coller l'image redimmensionnée
                $final->paste($image->thumbnail($resized_image_size, $mode), new Point($x, $y));
                $tempPath = $this->getTempDir() . '/' . $size .'_' . $this->getObject()->getId() . '.jpeg';

                // Création des thumbs en local (repertoire temporaire)
                $final->save($tempPath);
            }
            catch(Exception $exception) {
                return $this->logger->error($exception->getMessage());
            }
        }
        //Si l'image est strictement plus large ET plus haute que le thumbnail demandé alors
        //On fait un resize et le rendu peut dépasser
        else
        {
            $boxWidth = $params[$size][0];
            $boxHeight = $params[$size][1];
            $ratio = $largeur / $hauteur;
            if($size == "medium" && $ratio < 1)
            {
                $tmp = $boxHeight;
                $boxHeight = $boxWidth;
                $boxWidth = $tmp;
            }

            $squared_size = new Box($boxWidth,$boxHeight);
            $mode = ImageInterface::THUMBNAIL_OUTBOUND;
            $path = $imagePath;
            //Création des thumbs en local (repertoire temporaire)
            $tempPath = $this->getTempDir() . '/' . $size .'_' . $this->getObject()->getId() . '.jpeg';
            try{
                $imagine->open($path)->thumbnail($squared_size, $mode)->resize($squared_size)->save($tempPath);
            }catch(Exception $exception){

            }
        }

        //Quelque soit le type d'image
        try{
            //Enregistrement
            $this->getFileSystem()->write($this->getObject()->getFilePath($size),  file_get_contents($tempPath));
            //Suppression du fichier temporaire
            unlink($tempPath);
        }catch(Exception $exception){
            return $this->logger->error($exception->getMessage());
        }
    }

    //////////   FONCTIONS UTILITAIRES   \\\\\\\\

    /**
     * Retourne un booleen déterminant l'exactitude d'une URL
     * @param $url
     * @return bool
     */
    public function isValidURL($url)
    {
        $validator = Validation::createValidator();
        $violations = $validator->validate($url, [
            new Url(['checkDNS' => true]),
        ]);

        if (0 !== count($violations)) {
            // invalid url
            return false;
        }

        // Check ip of the domaine
        $ips = gethostbynamel(parse_url($url, PHP_URL_HOST));
        if (false === $ips) {
            // can't resolve dns
            return false;
        }

        foreach ($ips as $ip) {
            if (0 !== count($validator->validate($ip, [new Ip(['version' => Ip::ALL_NO_RES])]))) {
                // ip not in the good range
                return false;
            }
        }
        $port = parse_url($url, PHP_URL_PORT);
        if (!in_array($port, [null, 80, 443], true)) {
            // port not default one
            return false;
        }

        return true;
    }


    //Sceptique sur le flip, car plusieurs valeurs identiques
    public function extensionToContentType($value,$revert = false)
    {
        $value = strtolower($value);
        $extensionToType = array(
            '123'=>'application/vnd.lotus-1-2-3',
            '3dml'=>'text/vnd.in3d.3dml',
            '3ds'=>'image/x-3ds',
            '3g2'=>'video/3gpp2',
            '3gp'=>'video/3gpp',
            '7z'=>'application/x-7z-compressed',
            'aab'=>'application/x-authorware-bin',
            'aac'=>'audio/x-aac',
            'aam'=>'application/x-authorware-map',
            'aas'=>'application/x-authorware-seg',
            'abw'=>'application/x-abiword',
            'ac'=>'application/pkix-attr-cert',
            'acc'=>'application/vnd.americandynamics.acc',
            'ace'=>'application/x-ace-compressed',
            'acu'=>'application/vnd.acucobol',
            'acutc'=>'application/vnd.acucorp',
            'adp'=>'audio/adpcm',
            'aep'=>'application/vnd.audiograph',
            'afm'=>'application/x-font-type1',
            'afp'=>'application/vnd.ibm.modcap',
            'ahead'=>'application/vnd.ahead.space',
            'ai'=>'application/postscript',
            'aif'=>'audio/x-aiff',
            'aifc'=>'audio/x-aiff',
            'aiff'=>'audio/x-aiff',
            'air'=>'application/vnd.adobe.air-application-installer-package+zip',
            'ait'=>'application/vnd.dvb.ait',
            'ami'=>'application/vnd.amiga.ami',
            'anx'=>'application/annodex',
            'apk'=>'application/vnd.android.package-archive',
            'appcache'=>'text/cache-manifest',
            'application'=>'application/x-ms-application',
            'apr'=>'application/vnd.lotus-approach',
            'arc'=>'application/x-freearc',
            'asc'=>'application/pgp-signature',
            'asf'=>'video/x-ms-asf',
            'asm'=>'text/x-asm',
            'aso'=>'application/vnd.accpac.simply.aso',
            'asx'=>'video/x-ms-asf',
            'atc'=>'application/vnd.acucorp',
            'atom'=>'application/atom+xml',
            'atomcat'=>'application/atomcat+xml',
            'atomsvc'=>'application/atomsvc+xml',
            'atx'=>'application/vnd.antix.game-component',
            'au'=>'audio/basic',
            'avi'=>'video/x-msvideo',
            'aw'=>'application/applixware',
            'axa'=>'audio/annodex',
            'axv'=>'video/annodex',
            'azf'=>'application/vnd.airzip.filesecure.azf',
            'azs'=>'application/vnd.airzip.filesecure.azs',
            'azw'=>'application/vnd.amazon.ebook',
            'bat'=>'application/x-msdownload',
            'bcpio'=>'application/x-bcpio',
            'bdf'=>'application/x-font-bdf',
            'bdm'=>'application/vnd.syncml.dm+wbxml',
            'bed'=>'application/vnd.realvnc.bed',
            'bh2'=>'application/vnd.fujitsu.oasysprs',
            'bin'=>'application/octet-stream',
            'blb'=>'application/x-blorb',
            'blorb'=>'application/x-blorb',
            'bmi'=>'application/vnd.bmi',
            'bmp'=>'image/bmp',
            'book'=>'application/vnd.framemaker',
            'box'=>'application/vnd.previewsystems.box',
            'boz'=>'application/x-bzip2',
            'bpk'=>'application/octet-stream',
            'btif'=>'image/prs.btif',
            'bz'=>'application/x-bzip',
            'bz2'=>'application/x-bzip2',
            'c'=>'text/x-c',
            'c11amc'=>'application/vnd.cluetrust.cartomobile-config',
            'c11amz'=>'application/vnd.cluetrust.cartomobile-config-pkg',
            'c4d'=>'application/vnd.clonk.c4group',
            'c4f'=>'application/vnd.clonk.c4group',
            'c4g'=>'application/vnd.clonk.c4group',
            'c4p'=>'application/vnd.clonk.c4group',
            'c4u'=>'application/vnd.clonk.c4group',
            'cab'=>'application/vnd.ms-cab-compressed',
            'caf'=>'audio/x-caf',
            'cap'=>'application/vnd.tcpdump.pcap',
            'car'=>'application/vnd.curl.car',
            'cat'=>'application/vnd.ms-pki.seccat',
            'cb7'=>'application/x-cbr',
            'cba'=>'application/x-cbr',
            'cbr'=>'application/x-cbr',
            'cbt'=>'application/x-cbr',
            'cbz'=>'application/x-cbr',
            'cc'=>'text/x-c',
            'ccad'=>'application/clariscad',
            'cct'=>'application/x-director',
            'ccxml'=>'application/ccxml+xml',
            'cdbcmsg'=>'application/vnd.contact.cmsg',
            'cdf'=>'application/x-netcdf',
            'cdkey'=>'application/vnd.mediastation.cdkey',
            'cdmia'=>'application/cdmi-capability',
            'cdmic'=>'application/cdmi-container',
            'cdmid'=>'application/cdmi-domain',
            'cdmio'=>'application/cdmi-object',
            'cdmiq'=>'application/cdmi-queue',
            'cdx'=>'chemical/x-cdx',
            'cdxml'=>'application/vnd.chemdraw+xml',
            'cdy'=>'application/vnd.cinderella',
            'cer'=>'application/pkix-cert',
            'cfs'=>'application/x-cfs-compressed',
            'cgm'=>'image/cgm',
            'chat'=>'application/x-chat',
            'chm'=>'application/vnd.ms-htmlhelp',
            'chrt'=>'application/vnd.kde.kchart',
            'cif'=>'chemical/x-cif',
            'cii'=>'application/vnd.anser-web-certificate-issue-initiation',
            'cil'=>'application/vnd.ms-artgalry',
            'cla'=>'application/vnd.claymore',
            'class'=>'application/java-vm',
            'clkk'=>'application/vnd.crick.clicker.keyboard',
            'clkp'=>'application/vnd.crick.clicker.palette',
            'clkt'=>'application/vnd.crick.clicker.template',
            'clkw'=>'application/vnd.crick.clicker.wordbank',
            'clkx'=>'application/vnd.crick.clicker',
            'clp'=>'application/x-msclip',
            'cmc'=>'application/vnd.cosmocaller',
            'cmdf'=>'chemical/x-cmdf',
            'cml'=>'chemical/x-cml',
            'cmp'=>'application/vnd.yellowriver-custom-menu',
            'cmx'=>'image/x-cmx',
            'cod'=>'application/vnd.rim.cod',
            'com'=>'application/x-msdownload',
            'conf'=>'text/plain',
            'cpio'=>'application/x-cpio',
            'cpp'=>'text/x-c',
            'cpt'=>'application/mac-compactpro',
            'crd'=>'application/x-mscardfile',
            'crl'=>'application/pkix-crl',
            'crt'=>'application/x-x509-ca-cert',
            'cryptonote'=>'application/vnd.rig.cryptonote',
            'csh'=>'application/x-csh',
            'csml'=>'chemical/x-csml',
            'csp'=>'application/vnd.commonspace',
            'css'=>'text/css',
            'cst'=>'application/x-director',
            'csv'=>'text/csv',
            'cu'=>'application/cu-seeme',
            'curl'=>'text/vnd.curl',
            'cww'=>'application/prs.cww',
            'cxt'=>'application/x-director',
            'cxx'=>'text/x-c',
            'dae'=>'model/vnd.collada+xml',
            'daf'=>'application/vnd.mobius.daf',
            'dart'=>'application/vnd.dart',
            'dataless'=>'application/vnd.fdsn.seed',
            'davmount'=>'application/davmount+xml',
            'dbk'=>'application/docbook+xml',
            'dcr'=>'application/x-director',
            'dcurl'=>'text/vnd.curl.dcurl',
            'dd2'=>'application/vnd.oma.dd2+xml',
            'ddd'=>'application/vnd.fujixerox.ddd',
            'deb'=>'application/x-debian-package',
            'def'=>'text/plain',
            'deploy'=>'application/octet-stream',
            'der'=>'application/x-x509-ca-cert',
            'dfac'=>'application/vnd.dreamfactory',
            'dgc'=>'application/x-dgc-compressed',
            'dic'=>'text/x-c',
            'dir'=>'application/x-director',
            'dis'=>'application/vnd.mobius.dis',
            'dist'=>'application/octet-stream',
            'distz'=>'application/octet-stream',
            'djv'=>'image/vnd.djvu',
            'djvu'=>'image/vnd.djvu',
            'dll'=>'application/x-msdownload',
            'dmg'=>'application/x-apple-diskimage',
            'dmp'=>'application/vnd.tcpdump.pcap',
            'dms'=>'application/octet-stream',
            'dna'=>'application/vnd.dna',
            'doc'=>'application/msword',
            'docm'=>'application/vnd.ms-word.document.macroenabled.12',
            'docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'dot'=>'application/msword',
            'dotm'=>'application/vnd.ms-word.template.macroenabled.12',
            'dotx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
            'dp'=>'application/vnd.osgi.dp',
            'dpg'=>'application/vnd.dpgraph',
            'dra'=>'audio/vnd.dra',
            'drw'=>'application/drafting',
            'dsc'=>'text/prs.lines.tag',
            'dssc'=>'application/dssc+der',
            'dtb'=>'application/x-dtbook+xml',
            'dtd'=>'application/xml-dtd',
            'dts'=>'audio/vnd.dts',
            'dtshd'=>'audio/vnd.dts.hd',
            'dump'=>'application/octet-stream',
            'dvb'=>'video/vnd.dvb.file',
            'dvi'=>'application/x-dvi',
            'dwf'=>'model/vnd.dwf',
            'dwg'=>'image/vnd.dwg',
            'dxf'=>'image/vnd.dxf',
            'dxp'=>'application/vnd.spotfire.dxp',
            'dxr'=>'application/x-director',
            'ecelp4800'=>'audio/vnd.nuera.ecelp4800',
            'ecelp7470'=>'audio/vnd.nuera.ecelp7470',
            'ecelp9600'=>'audio/vnd.nuera.ecelp9600',
            'ecma'=>'application/ecmascript',
            'edm'=>'application/vnd.novadigm.edm',
            'edx'=>'application/vnd.novadigm.edx',
            'efif'=>'application/vnd.picsel',
            'ei6'=>'application/vnd.pg.osasli',
            'elc'=>'application/octet-stream',
            'emf'=>'application/x-msmetafile',
            'eml'=>'message/rfc822',
            'emma'=>'application/emma+xml',
            'emz'=>'application/x-msmetafile',
            'eol'=>'audio/vnd.digital-winds',
            'eot'=>'application/vnd.ms-fontobject',
            'eps'=>'application/postscript',
            'epub'=>'application/epub+zip',
            'es3'=>'application/vnd.eszigno3+xml',
            'esa'=>'application/vnd.osgi.subsystem',
            'esf'=>'application/vnd.epson.esf',
            'et3'=>'application/vnd.eszigno3+xml',
            'etx'=>'text/x-setext',
            'eva'=>'application/x-eva',
            'evy'=>'application/x-envoy',
            'exe'=>'application/x-msdownload',
            'exi'=>'application/exi',
            'ext'=>'application/vnd.novadigm.ext',
            'ez'=>'application/andrew-inset',
            'ez2'=>'application/vnd.ezpix-album',
            'ez3'=>'application/vnd.ezpix-package',
            'f'=>'text/x-fortran',
            'f4v'=>'video/x-f4v',
            'f77'=>'text/x-fortran',
            'f90'=>'text/x-fortran',
            'fbs'=>'image/vnd.fastbidsheet',
            'fcdt'=>'application/vnd.adobe.formscentral.fcdt',
            'fcs'=>'application/vnd.isac.fcs',
            'fdf'=>'application/vnd.fdf',
            'fe_launch'=>'application/vnd.denovo.fcselayout-link',
            'fg5'=>'application/vnd.fujitsu.oasysgp',
            'fgd'=>'application/x-director',
            'fh'=>'image/x-freehand',
            'fh4'=>'image/x-freehand',
            'fh5'=>'image/x-freehand',
            'fh7'=>'image/x-freehand',
            'fhc'=>'image/x-freehand',
            'fig'=>'application/x-xfig',
            'flac'=>'audio/x-flac',
            'fli'=>'video/x-fli',
            'flo'=>'application/vnd.micrografx.flo',
            'flv'=>'video/x-flv',
            'flw'=>'application/vnd.kde.kivio',
            'flx'=>'text/vnd.fmi.flexstor',
            'fly'=>'text/vnd.fly',
            'fm'=>'application/vnd.framemaker',
            'fnc'=>'application/vnd.frogans.fnc',
            'for'=>'text/x-fortran',
            'fpx'=>'image/vnd.fpx',
            'frame'=>'application/vnd.framemaker',
            'fsc'=>'application/vnd.fsc.weblaunch',
            'fst'=>'image/vnd.fst',
            'ftc'=>'application/vnd.fluxtime.clip',
            'fti'=>'application/vnd.anser-web-funds-transfer-initiation',
            'fvt'=>'video/vnd.fvt',
            'fxp'=>'application/vnd.adobe.fxp',
            'fxpl'=>'application/vnd.adobe.fxp',
            'fzs'=>'application/vnd.fuzzysheet',
            'g2w'=>'application/vnd.geoplan',
            'g3'=>'image/g3fax',
            'g3w'=>'application/vnd.geospace',
            'gac'=>'application/vnd.groove-account',
            'gam'=>'application/x-tads',
            'gbr'=>'application/rpki-ghostbusters',
            'gca'=>'application/x-gca-compressed',
            'gdl'=>'model/vnd.gdl',
            'geo'=>'application/vnd.dynageo',
            'gex'=>'application/vnd.geometry-explorer',
            'ggb'=>'application/vnd.geogebra.file',
            'ggt'=>'application/vnd.geogebra.tool',
            'ghf'=>'application/vnd.groove-help',
            'gif'=>'image/gif',
            'gim'=>'application/vnd.groove-identity-message',
            'gml'=>'application/gml+xml',
            'gmx'=>'application/vnd.gmx',
            'gnumeric'=>'application/x-gnumeric',
            'gph'=>'application/vnd.flographit',
            'gpx'=>'application/gpx+xml',
            'gqf'=>'application/vnd.grafeq',
            'gqs'=>'application/vnd.grafeq',
            'gram'=>'application/srgs',
            'gramps'=>'application/x-gramps-xml',
            'gre'=>'application/vnd.geometry-explorer',
            'grv'=>'application/vnd.groove-injector',
            'grxml'=>'application/srgs+xml',
            'gsf'=>'application/x-font-ghostscript',
            'gtar'=>'application/x-gtar',
            'gtm'=>'application/vnd.groove-tool-message',
            'gtw'=>'model/vnd.gtw',
            'gv'=>'text/vnd.graphviz',
            'gxf'=>'application/gxf',
            'gxt'=>'application/vnd.geonext',
            'gz'=>'application/x-gzip',
            'h'=>'text/x-c',
            'h261'=>'video/h261',
            'h263'=>'video/h263',
            'h264'=>'video/h264',
            'hal'=>'application/vnd.hal+xml',
            'hbci'=>'application/vnd.hbci',
            'hdf'=>'application/x-hdf',
            'hh'=>'text/x-c',
            'hlp'=>'application/winhlp',
            'hpgl'=>'application/vnd.hp-hpgl',
            'hpid'=>'application/vnd.hp-hpid',
            'hps'=>'application/vnd.hp-hps',
            'hqx'=>'application/mac-binhex40',
            'htke'=>'application/vnd.kenameaapp',
            'htm'=>'text/html',
            'html'=>'text/html',
            'hvd'=>'application/vnd.yamaha.hv-dic',
            'hvp'=>'application/vnd.yamaha.hv-voice',
            'hvs'=>'application/vnd.yamaha.hv-script',
            'i2g'=>'application/vnd.intergeo',
            'icc'=>'application/vnd.iccprofile',
            'ice'=>'x-conference/x-cooltalk',
            'icm'=>'application/vnd.iccprofile',
            'ico'=>'image/x-icon',
            'ics'=>'text/calendar',
            'ief'=>'image/ief',
            'ifb'=>'text/calendar',
            'ifm'=>'application/vnd.shana.informed.formdata',
            'iges'=>'model/iges',
            'igl'=>'application/vnd.igloader',
            'igm'=>'application/vnd.insors.igm',
            'igs'=>'model/iges',
            'igx'=>'application/vnd.micrografx.igx',
            'iif'=>'application/vnd.shana.informed.interchange',
            'imp'=>'application/vnd.accpac.simply.imp',
            'ims'=>'application/vnd.ms-ims',
            'in'=>'text/plain',
            'ink'=>'application/inkml+xml',
            'inkml'=>'application/inkml+xml',
            'install'=>'application/x-install-instructions',
            'iota'=>'application/vnd.astraea-software.iota',
            'ipfix'=>'application/ipfix',
            'ipk'=>'application/vnd.shana.informed.package',
            'ips'=>'application/x-ipscript',
            'ipx'=>'application/x-ipix',
            'irm'=>'application/vnd.ibm.rights-management',
            'irp'=>'application/vnd.irepository.package+xml',
            'iso'=>'application/x-iso9660-image',
            'itp'=>'application/vnd.shana.informed.formtemplate',
            'ivp'=>'application/vnd.immervision-ivp',
            'ivu'=>'application/vnd.immervision-ivu',
            'jad'=>'text/vnd.sun.j2me.app-descriptor',
            'jam'=>'application/vnd.jam',
            'jar'=>'application/java-archive',
            'java'=>'text/x-java-source',
            'jisp'=>'application/vnd.jisp',
            'jlt'=>'application/vnd.hp-jlyt',
            'jnlp'=>'application/x-java-jnlp-file',
            'joda'=>'application/vnd.joost.joda-archive',
            'jpe'=>'image/jpeg',
            'jpeg'=>'image/jpeg',
            'jpg'=>'image/jpeg',
            'jpgm'=>'video/jpm',
            'jpgv'=>'video/jpeg',
            'jpm'=>'video/jpm',
            'js'=>'application/javascript',
            'json'=>'application/json',
            'jsonml'=>'application/jsonml+json',
            'kar'=>'audio/midi',
            'karbon'=>'application/vnd.kde.karbon',
            'kfo'=>'application/vnd.kde.kformula',
            'kia'=>'application/vnd.kidspiration',
            'kml'=>'application/vnd.google-earth.kml+xml',
            'kmz'=>'application/vnd.google-earth.kmz',
            'kne'=>'application/vnd.kinar',
            'knp'=>'application/vnd.kinar',
            'kon'=>'application/vnd.kde.kontour',
            'kpr'=>'application/vnd.kde.kpresenter',
            'kpt'=>'application/vnd.kde.kpresenter',
            'kpxx'=>'application/vnd.ds-keypoint',
            'ksp'=>'application/vnd.kde.kspread',
            'ktr'=>'application/vnd.kahootz',
            'ktx'=>'image/ktx',
            'ktz'=>'application/vnd.kahootz',
            'kwd'=>'application/vnd.kde.kword',
            'kwt'=>'application/vnd.kde.kword',
            'lasxml'=>'application/vnd.las.las+xml',
            'latex'=>'application/x-latex',
            'lbd'=>'application/vnd.llamagraphics.life-balance.desktop',
            'lbe'=>'application/vnd.llamagraphics.life-balance.exchange+xml',
            'les'=>'application/vnd.hhe.lesson-player',
            'lha'=>'application/x-lzh-compressed',
            'link66'=>'application/vnd.route66.link66+xml',
            'list'=>'text/plain',
            'list3820'=>'application/vnd.ibm.modcap',
            'listafp'=>'application/vnd.ibm.modcap',
            'lnk'=>'application/x-ms-shortcut',
            'log'=>'text/plain',
            'lostxml'=>'application/lost+xml',
            'lrf'=>'application/octet-stream',
            'lrm'=>'application/vnd.ms-lrm',
            'lsp'=>'application/x-lisp',
            'ltf'=>'application/vnd.frogans.ltf',
            'lvp'=>'audio/vnd.lucent.voice',
            'lwp'=>'application/vnd.lotus-wordpro',
            'lzh'=>'application/x-lzh-compressed',
            'm'=>'text/plain',
            'm13'=>'application/x-msmediaview',
            'm14'=>'application/x-msmediaview',
            'm1v'=>'video/mpeg',
            'm21'=>'application/mp21',
            'm2a'=>'audio/mpeg',
            'm2v'=>'video/mpeg',
            'm3a'=>'audio/mpeg',
            'm3u'=>'audio/x-mpegurl',
            'm3u8'=>'application/vnd.apple.mpegurl',
            'm4u'=>'video/vnd.mpegurl',
            'm4v'=>'video/x-m4v',
            'ma'=>'application/mathematica',
            'mads'=>'application/mads+xml',
            'mag'=>'application/vnd.ecowin.chart',
            'maker'=>'application/vnd.framemaker',
            'man'=>'text/troff',
            'mar'=>'application/octet-stream',
            'mathml'=>'application/mathml+xml',
            'mb'=>'application/mathematica',
            'mbk'=>'application/vnd.mobius.mbk',
            'mbox'=>'application/mbox',
            'mc1'=>'application/vnd.medcalcdata',
            'mcd'=>'application/vnd.mcd',
            'mcurl'=>'text/vnd.curl.mcurl',
            'mdb'=>'application/x-msaccess',
            'mdi'=>'image/vnd.ms-modi',
            'me'=>'text/troff',
            'mesh'=>'model/mesh',
            'meta4'=>'application/metalink4+xml',
            'metalink'=>'application/metalink+xml',
            'mets'=>'application/mets+xml',
            'mfm'=>'application/vnd.mfmp',
            'mft'=>'application/rpki-manifest',
            'mgp'=>'application/vnd.osgeo.mapguide.package',
            'mgz'=>'application/vnd.proteus.magazine',
            'mid'=>'audio/midi',
            'midi'=>'audio/midi',
            'mie'=>'application/x-mie',
            'mif'=>'application/vnd.mif',
            'mime'=>'message/rfc822',
            'mj2'=>'video/mj2',
            'mjp2'=>'video/mj2',
            'mk3d'=>'video/x-matroska',
            'mka'=>'audio/x-matroska',
            'mks'=>'video/x-matroska',
            'mkv'=>'video/x-matroska',
            'mlp'=>'application/vnd.dolby.mlp',
            'mmd'=>'application/vnd.chipnuts.karaoke-mmd',
            'mmf'=>'application/vnd.smaf',
            'mmr'=>'image/vnd.fujixerox.edmics-mmr',
            'mng'=>'video/x-mng',
            'mny'=>'application/x-msmoney',
            'mobi'=>'application/x-mobipocket-ebook',
            'mods'=>'application/mods+xml',
            'mov'=>'video/quicktime',
            'movie'=>'video/x-sgi-movie',
            'mp2'=>'audio/mpeg',
            'mp21'=>'application/mp21',
            'mp2a'=>'audio/mpeg',
            'mp3'=>'audio/mpeg',
            'mp4'=>'video/mp4',
            'mp4a'=>'audio/mp4',
            'mp4s'=>'application/mp4',
            'mp4v'=>'video/mp4',
            'mpc'=>'application/vnd.mophun.certificate',
            'mpe'=>'video/mpeg',
            'mpeg'=>'video/mpeg',
            'mpg'=>'video/mpeg',
            'mpg4'=>'video/mp4',
            'mpga'=>'audio/mpeg',
            'mpkg'=>'application/vnd.apple.installer+xml',
            'mpm'=>'application/vnd.blueice.multipass',
            'mpn'=>'application/vnd.mophun.application',
            'mpp'=>'application/vnd.ms-project',
            'mpt'=>'application/vnd.ms-project',
            'mpy'=>'application/vnd.ibm.minipay',
            'mqy'=>'application/vnd.mobius.mqy',
            'mrc'=>'application/marc',
            'mrcx'=>'application/marcxml+xml',
            'ms'=>'text/troff',
            'mscml'=>'application/mediaservercontrol+xml',
            'mseed'=>'application/vnd.fdsn.mseed',
            'mseq'=>'application/vnd.mseq',
            'msf'=>'application/vnd.epson.msf',
            'msh'=>'model/mesh',
            'msi'=>'application/x-msdownload',
            'msl'=>'application/vnd.mobius.msl',
            'msty'=>'application/vnd.muvee.style',
            'mts'=>'model/vnd.mts',
            'mus'=>'application/vnd.musician',
            'musicxml'=>'application/vnd.recordare.musicxml+xml',
            'mvb'=>'application/x-msmediaview',
            'mwf'=>'application/vnd.mfer',
            'mxf'=>'application/mxf',
            'mxl'=>'application/vnd.recordare.musicxml',
            'mxml'=>'application/xv+xml',
            'mxs'=>'application/vnd.triscape.mxs',
            'mxu'=>'video/vnd.mpegurl',
            'n-gage'=>'application/vnd.nokia.n-gage.symbian.install',
            'n3'=>'text/n3',
            'nb'=>'application/mathematica',
            'nbp'=>'application/vnd.wolfram.player',
            'nc'=>'application/x-netcdf',
            'ncx'=>'application/x-dtbncx+xml',
            'nfo'=>'text/x-nfo',
            'ngdat'=>'application/vnd.nokia.n-gage.data',
            'nitf'=>'application/vnd.nitf',
            'nlu'=>'application/vnd.neurolanguage.nlu',
            'nml'=>'application/vnd.enliven',
            'nnd'=>'application/vnd.noblenet-directory',
            'nns'=>'application/vnd.noblenet-sealer',
            'nnw'=>'application/vnd.noblenet-web',
            'npx'=>'image/vnd.net-fpx',
            'nsc'=>'application/x-conference',
            'nsf'=>'application/vnd.lotus-notes',
            'ntf'=>'application/vnd.nitf',
            'nzb'=>'application/x-nzb',
            'oa2'=>'application/vnd.fujitsu.oasys2',
            'oa3'=>'application/vnd.fujitsu.oasys3',
            'oas'=>'application/vnd.fujitsu.oasys',
            'obd'=>'application/x-msbinder',
            'obj'=>'application/x-tgif',
            'oda'=>'application/oda',
            'odb'=>'application/vnd.oasis.opendocument.database',
            'odc'=>'application/vnd.oasis.opendocument.chart',
            'odf'=>'application/vnd.oasis.opendocument.formula',
            'odft'=>'application/vnd.oasis.opendocument.formula-template',
            'odg'=>'application/vnd.oasis.opendocument.graphics',
            'odi'=>'application/vnd.oasis.opendocument.image',
            'odm'=>'application/vnd.oasis.opendocument.text-master',
            'odp'=>'application/vnd.oasis.opendocument.presentation',
            'ods'=>'application/vnd.oasis.opendocument.spreadsheet',
            'odt'=>'application/vnd.oasis.opendocument.text',
            'oga'=>'audio/ogg',
            'ogg'=>'audio/ogg',
            'ogv'=>'video/ogg',
            'ogx'=>'application/ogg',
            'omdoc'=>'application/omdoc+xml',
            'onepkg'=>'application/onenote',
            'onetmp'=>'application/onenote',
            'onetoc'=>'application/onenote',
            'onetoc2'=>'application/onenote',
            'opf'=>'application/oebps-package+xml',
            'opml'=>'text/x-opml',
            'oprc'=>'application/vnd.palm',
            'org'=>'application/vnd.lotus-organizer',
            'osf'=>'application/vnd.yamaha.openscoreformat',
            'osfpvg'=>'application/vnd.yamaha.openscoreformat.osfpvg+xml',
            'otc'=>'application/vnd.oasis.opendocument.chart-template',
            'otf'=>'application/x-font-otf',
            'otg'=>'application/vnd.oasis.opendocument.graphics-template',
            'oth'=>'application/vnd.oasis.opendocument.text-web',
            'oti'=>'application/vnd.oasis.opendocument.image-template',
            'otp'=>'application/vnd.oasis.opendocument.presentation-template',
            'ots'=>'application/vnd.oasis.opendocument.spreadsheet-template',
            'ott'=>'application/vnd.oasis.opendocument.text-template',
            'oxps'=>'application/oxps',
            'oxt'=>'application/vnd.openofficeorg.extension',
            'p'=>'text/x-pascal',
            'p10'=>'application/pkcs10',
            'p12'=>'application/x-pkcs12',
            'p7b'=>'application/x-pkcs7-certificates',
            'p7c'=>'application/pkcs7-mime',
            'p7m'=>'application/pkcs7-mime',
            'p7r'=>'application/x-pkcs7-certreqresp',
            'p7s'=>'application/pkcs7-signature',
            'p8'=>'application/pkcs8',
            'pas'=>'text/x-pascal',
            'paw'=>'application/vnd.pawaafile',
            'pbd'=>'application/vnd.powerbuilder6',
            'pbm'=>'image/x-portable-bitmap',
            'pcap'=>'application/vnd.tcpdump.pcap',
            'pcf'=>'application/x-font-pcf',
            'pcl'=>'application/vnd.hp-pcl',
            'pclxl'=>'application/vnd.hp-pclxl',
            'pct'=>'image/x-pict',
            'pcurl'=>'application/vnd.curl.pcurl',
            'pcx'=>'image/x-pcx',
            'pdb'=>'application/vnd.palm',
            'pdf'=>'application/pdf',
            'pfa'=>'application/x-font-type1',
            'pfb'=>'application/x-font-type1',
            'pfm'=>'application/x-font-type1',
            'pfr'=>'application/font-tdpfr',
            'pfx'=>'application/x-pkcs12',
            'pgm'=>'image/x-portable-graymap',
            'pgn'=>'application/x-chess-pgn',
            'pgp'=>'application/pgp-encrypted',
            'pic'=>'image/x-pict',
            'pkg'=>'application/octet-stream',
            'pki'=>'application/pkixcmp',
            'pkipath'=>'application/pkix-pkipath',
            'plb'=>'application/vnd.3gpp.pic-bw-large',
            'plc'=>'application/vnd.mobius.plc',
            'plf'=>'application/vnd.pocketlearn',
            'pls'=>'application/pls+xml',
            'pml'=>'application/vnd.ctc-posml',
            'png'=>'image/png',
            'pnm'=>'image/x-portable-anymap',
            'portpkg'=>'application/vnd.macports.portpkg',
            'pot'=>'application/vnd.ms-powerpoint',
            'potm'=>'application/vnd.ms-powerpoint.template.macroenabled.12',
            'potx'=>'application/vnd.openxmlformats-officedocument.presentationml.template',
            'ppam'=>'application/vnd.ms-powerpoint.addin.macroenabled.12',
            'ppd'=>'application/vnd.cups-ppd',
            'ppm'=>'image/x-portable-pixmap',
            'pps'=>'application/vnd.ms-powerpoint',
            'ppsm'=>'application/vnd.ms-powerpoint.slideshow.macroenabled.12',
            'ppsx'=>'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
            'ppt'=>'application/vnd.ms-powerpoint',
            'pptm'=>'application/vnd.ms-powerpoint.presentation.macroenabled.12',
            'pptx'=>'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'ppz'=>'application/mspowerpoint',
            'pqa'=>'application/vnd.palm',
            'prc'=>'application/x-mobipocket-ebook',
            'pre'=>'application/vnd.lotus-freelance',
            'prf'=>'application/pics-rules',
            'prt'=>'application/pro_eng',
            'ps'=>'application/postscript',
            'psb'=>'application/vnd.3gpp.pic-bw-small',
            'psd'=>'image/vnd.adobe.photoshop',
            'psf'=>'application/x-font-linux-psf',
            'pskcxml'=>'application/pskc+xml',
            'ptid'=>'application/vnd.pvi.ptid1',
            'pub'=>'application/x-mspublisher',
            'pvb'=>'application/vnd.3gpp.pic-bw-var',
            'pwn'=>'application/vnd.3m.post-it-notes',
            'pya'=>'audio/vnd.ms-playready.media.pya',
            'pyv'=>'video/vnd.ms-playready.media.pyv',
            'qam'=>'application/vnd.epson.quickanime',
            'qbo'=>'application/vnd.intu.qbo',
            'qfx'=>'application/vnd.intu.qfx',
            'qps'=>'application/vnd.publishare-delta-tree',
            'qt'=>'video/quicktime',
            'qwd'=>'application/vnd.quark.quarkxpress',
            'qwt'=>'application/vnd.quark.quarkxpress',
            'qxb'=>'application/vnd.quark.quarkxpress',
            'qxd'=>'application/vnd.quark.quarkxpress',
            'qxl'=>'application/vnd.quark.quarkxpress',
            'qxt'=>'application/vnd.quark.quarkxpress',
            'ra'=>'audio/x-pn-realaudio',
            'ram'=>'audio/x-pn-realaudio',
            'rar'=>'application/x-rar-compressed',
            'ras'=>'image/x-cmu-raster',
            'rcprofile'=>'application/vnd.ipunplugged.rcprofile',
            'rdf'=>'application/rdf+xml',
            'rdz'=>'application/vnd.data-vision.rdz',
            'rep'=>'application/vnd.businessobjects',
            'res'=>'application/x-dtbresource+xml',
            'rgb'=>'image/x-rgb',
            'rif'=>'application/reginfo+xml',
            'rip'=>'audio/vnd.rip',
            'ris'=>'application/x-research-info-systems',
            'rl'=>'application/resource-lists+xml',
            'rlc'=>'image/vnd.fujixerox.edmics-rlc',
            'rld'=>'application/resource-lists-diff+xml',
            'rm'=>'application/vnd.rn-realmedia',
            'rmi'=>'audio/midi',
            'rmp'=>'audio/x-pn-realaudio-plugin',
            'rms'=>'application/vnd.jcp.javame.midlet-rms',
            'rmvb'=>'application/vnd.rn-realmedia-vbr',
            'rnc'=>'application/relax-ng-compact-syntax',
            'roa'=>'application/rpki-roa',
            'roff'=>'text/troff',
            'rp9'=>'application/vnd.cloanto.rp9',
            'rpm'=>'audio/x-pn-realaudio-plugin',
            'rpss'=>'application/vnd.nokia.radio-presets',
            'rpst'=>'application/vnd.nokia.radio-preset',
            'rq'=>'application/sparql-query',
            'rs'=>'application/rls-services+xml',
            'rsd'=>'application/rsd+xml',
            'rss'=>'application/rss+xml',
            'rtf'=>'application/rtf',
            'rtx'=>'text/richtext',
            's'=>'text/x-asm',
            's3m'=>'audio/s3m',
            'saf'=>'application/vnd.yamaha.smaf-audio',
            'sbml'=>'application/sbml+xml',
            'sc'=>'application/vnd.ibm.secure-container',
            'scd'=>'application/x-msschedule',
            'scm'=>'application/vnd.lotus-screencam',
            'scq'=>'application/scvp-cv-request',
            'scs'=>'application/scvp-cv-response',
            'scurl'=>'text/vnd.curl.scurl',
            'sda'=>'application/vnd.stardivision.draw',
            'sdc'=>'application/vnd.stardivision.calc',
            'sdd'=>'application/vnd.stardivision.impress',
            'sdkd'=>'application/vnd.solent.sdkm+xml',
            'sdkm'=>'application/vnd.solent.sdkm+xml',
            'sdp'=>'application/sdp',
            'sdw'=>'application/vnd.stardivision.writer',
            'see'=>'application/vnd.seemail',
            'seed'=>'application/vnd.fdsn.seed',
            'sema'=>'application/vnd.sema',
            'semd'=>'application/vnd.semd',
            'semf'=>'application/vnd.semf',
            'ser'=>'application/java-serialized-object',
            'set'=>'application/set',
            'setpay'=>'application/set-payment-initiation',
            'setreg'=>'application/set-registration-initiation',
            'sfd-hdstx'=>'application/vnd.hydrostatix.sof-data',
            'sfs'=>'application/vnd.spotfire.sfs',
            'sfv'=>'text/x-sfv',
            'sgi'=>'image/sgi',
            'sgl'=>'application/vnd.stardivision.writer-global',
            'sgm'=>'text/sgml',
            'sgml'=>'text/sgml',
            'sh'=>'application/x-sh',
            'shar'=>'application/x-shar',
            'shf'=>'application/shf+xml',
            'sid'=>'image/x-mrsid-image',
            'sig'=>'application/pgp-signature',
            'sil'=>'audio/silk',
            'silo'=>'model/mesh',
            'sis'=>'application/vnd.symbian.install',
            'sisx'=>'application/vnd.symbian.install',
            'sit'=>'application/x-stuffit',
            'sitx'=>'application/x-stuffitx',
            'skd'=>'application/vnd.koan',
            'skm'=>'application/vnd.koan',
            'skp'=>'application/vnd.koan',
            'skt'=>'application/vnd.koan',
            'sldm'=>'application/vnd.ms-powerpoint.slide.macroenabled.12',
            'sldx'=>'application/vnd.openxmlformats-officedocument.presentationml.slide',
            'slt'=>'application/vnd.epson.salt',
            'sm'=>'application/vnd.stepmania.stepchart',
            'smf'=>'application/vnd.stardivision.math',
            'smi'=>'application/smil+xml',
            'smil'=>'application/smil+xml',
            'smv'=>'video/x-smv',
            'smzip'=>'application/vnd.stepmania.package',
            'snd'=>'audio/basic',
            'snf'=>'application/x-font-snf',
            'so'=>'application/octet-stream',
            'sol'=>'application/solids',
            'spc'=>'application/x-pkcs7-certificates',
            'spf'=>'application/vnd.yamaha.smaf-phrase',
            'spl'=>'application/x-futuresplash',
            'spot'=>'text/vnd.in3d.spot',
            'spp'=>'application/scvp-vp-response',
            'spq'=>'application/scvp-vp-request',
            'spx'=>'audio/ogg',
            'sql'=>'application/x-sql',
            'src'=>'application/x-wais-source',
            'srt'=>'application/x-subrip',
            'sru'=>'application/sru+xml',
            'srx'=>'application/sparql-results+xml',
            'ssdl'=>'application/ssdl+xml',
            'sse'=>'application/vnd.kodak-descriptor',
            'ssf'=>'application/vnd.epson.ssf',
            'ssml'=>'application/ssml+xml',
            'st'=>'application/vnd.sailingtracker.track',
            'stc'=>'application/vnd.sun.xml.calc.template',
            'std'=>'application/vnd.sun.xml.draw.template',
            'step'=>'application/STEP',
            'stf'=>'application/vnd.wt.stf',
            'sti'=>'application/vnd.sun.xml.impress.template',
            'stk'=>'application/hyperstudio',
            'stl'=>'application/vnd.ms-pki.stl',
            'stp'=>'application/STEP',
            'str'=>'application/vnd.pg.format',
            'stw'=>'application/vnd.sun.xml.writer.template',
            'sub'=>'text/vnd.dvb.subtitle',
            'sus'=>'application/vnd.sus-calendar',
            'susp'=>'application/vnd.sus-calendar',
            'sv4cpio'=>'application/x-sv4cpio',
            'sv4crc'=>'application/x-sv4crc',
            'svc'=>'application/vnd.dvb.service',
            'svd'=>'application/vnd.svd',
            'svg'=>'image/svg+xml',
            'svgz'=>'image/svg+xml',
            'swa'=>'application/x-director',
            'swf'=>'application/x-shockwave-flash',
            'swi'=>'application/vnd.aristanetworks.swi',
            'sxc'=>'application/vnd.sun.xml.calc',
            'sxd'=>'application/vnd.sun.xml.draw',
            'sxg'=>'application/vnd.sun.xml.writer.global',
            'sxi'=>'application/vnd.sun.xml.impress',
            'sxm'=>'application/vnd.sun.xml.math',
            'sxw'=>'application/vnd.sun.xml.writer',
            't'=>'text/troff',
            't3'=>'application/x-t3vm-image',
            'taglet'=>'application/vnd.mynfc',
            'tao'=>'application/vnd.tao.intent-module-archive',
            'tar'=>'application/x-tar',
            'tcap'=>'application/vnd.3gpp2.tcap',
            'tcl'=>'application/x-tcl',
            'teacher'=>'application/vnd.smart.teacher',
            'tei'=>'application/tei+xml',
            'teicorpus'=>'application/tei+xml',
            'tex'=>'application/x-tex',
            'texi'=>'application/x-texinfo',
            'texinfo'=>'application/x-texinfo',
            'text'=>'text/plain',
            'tfi'=>'application/thraud+xml',
            'tfm'=>'application/x-tex-tfm',
            'tga'=>'image/x-tga',
            'thmx'=>'application/vnd.ms-officetheme',
            'tif'=>'image/tiff',
            'tiff'=>'image/tiff',
            'tmo'=>'application/vnd.tmobile-livetv',
            'torrent'=>'application/x-bittorrent',
            'tpl'=>'application/vnd.groove-tool-template',
            'tpt'=>'application/vnd.trid.tpt',
            'tr'=>'text/troff',
            'tra'=>'application/vnd.trueapp',
            'trm'=>'application/x-msterminal',
            'tsd'=>'application/timestamped-data',
            'tsi'=>'audio/TSP-audio',
            'tsp'=>'application/dsptype',
            'tsv'=>'text/tab-separated-values',
            'ttc'=>'application/x-font-ttf',
            'ttf'=>'application/x-font-ttf',
            'ttl'=>'text/turtle',
            'twd'=>'application/vnd.simtech-mindmapper',
            'twds'=>'application/vnd.simtech-mindmapper',
            'txd'=>'application/vnd.genomatix.tuxedo',
            'txf'=>'application/vnd.mobius.txf',
            'txt'=>'text/plain',
            'u32'=>'application/x-authorware-bin',
            'udeb'=>'application/x-debian-package',
            'ufd'=>'application/vnd.ufdl',
            'ufdl'=>'application/vnd.ufdl',
            'ulx'=>'application/x-glulx',
            'umj'=>'application/vnd.umajin',
            'unityweb'=>'application/vnd.unity',
            'unv'=>'application/i-deas',
            'uoml'=>'application/vnd.uoml+xml',
            'uri'=>'text/uri-list',
            'uris'=>'text/uri-list',
            'urls'=>'text/uri-list',
            'ustar'=>'application/x-ustar',
            'utz'=>'application/vnd.uiq.theme',
            'uu'=>'text/x-uuencode',
            'uva'=>'audio/vnd.dece.audio',
            'uvd'=>'application/vnd.dece.data',
            'uvf'=>'application/vnd.dece.data',
            'uvg'=>'image/vnd.dece.graphic',
            'uvh'=>'video/vnd.dece.hd',
            'uvi'=>'image/vnd.dece.graphic',
            'uvm'=>'video/vnd.dece.mobile',
            'uvp'=>'video/vnd.dece.pd',
            'uvs'=>'video/vnd.dece.sd',
            'uvt'=>'application/vnd.dece.ttml+xml',
            'uvu'=>'video/vnd.uvvu.mp4',
            'uvv'=>'video/vnd.dece.video',
            'uvva'=>'audio/vnd.dece.audio',
            'uvvd'=>'application/vnd.dece.data',
            'uvvf'=>'application/vnd.dece.data',
            'uvvg'=>'image/vnd.dece.graphic',
            'uvvh'=>'video/vnd.dece.hd',
            'uvvi'=>'image/vnd.dece.graphic',
            'uvvm'=>'video/vnd.dece.mobile',
            'uvvp'=>'video/vnd.dece.pd',
            'uvvs'=>'video/vnd.dece.sd',
            'uvvt'=>'application/vnd.dece.ttml+xml',
            'uvvu'=>'video/vnd.uvvu.mp4',
            'uvvv'=>'video/vnd.dece.video',
            'uvvx'=>'application/vnd.dece.unspecified',
            'uvvz'=>'application/vnd.dece.zip',
            'uvx'=>'application/vnd.dece.unspecified',
            'uvz'=>'application/vnd.dece.zip',
            'vcard'=>'text/vcard',
            'vcd'=>'application/x-cdlink',
            'vcf'=>'text/x-vcard',
            'vcg'=>'application/vnd.groove-vcard',
            'vcs'=>'text/x-vcalendar',
            'vcx'=>'application/vnd.vcx',
            'vda'=>'application/vda',
            'vis'=>'application/vnd.visionary',
            'viv'=>'video/vnd.vivo',
            'vivo'=>'video/vnd.vivo',
            'vob'=>'video/x-ms-vob',
            'vor'=>'application/vnd.stardivision.writer',
            'vox'=>'application/x-authorware-bin',
            'vrml'=>'model/vrml',
            'vsd'=>'application/vnd.visio',
            'vsf'=>'application/vnd.vsf',
            'vss'=>'application/vnd.visio',
            'vst'=>'application/vnd.visio',
            'vsw'=>'application/vnd.visio',
            'vtu'=>'model/vnd.vtu',
            'vxml'=>'application/voicexml+xml',
            'w3d'=>'application/x-director',
            'wad'=>'application/x-doom',
            'wav'=>'audio/x-wav',
            'wax'=>'audio/x-ms-wax',
            'wbmp'=>'image/vnd.wap.wbmp',
            'wbs'=>'application/vnd.criticaltools.wbs+xml',
            'wbxml'=>'application/vnd.wap.wbxml',
            'wcm'=>'application/vnd.ms-works',
            'wdb'=>'application/vnd.ms-works',
            'wdp'=>'image/vnd.ms-photo',
            'weba'=>'audio/webm',
            'webm'=>'video/webm',
            'webp'=>'image/webp',
            'wg'=>'application/vnd.pmi.widget',
            'wgt'=>'application/widget',
            'wks'=>'application/vnd.ms-works',
            'wm'=>'video/x-ms-wm',
            'wma'=>'audio/x-ms-wma',
            'wmd'=>'application/x-ms-wmd',
            'wmf'=>'application/x-msmetafile',
            'wml'=>'text/vnd.wap.wml',
            'wmlc'=>'application/vnd.wap.wmlc',
            'wmls'=>'text/vnd.wap.wmlscript',
            'wmlsc'=>'application/vnd.wap.wmlscriptc',
            'wmv'=>'video/x-ms-wmv',
            'wmx'=>'video/x-ms-wmx',
            'wmz'=>'application/x-ms-wmz',
            'woff'=>'application/x-font-woff',
            'wpd'=>'application/vnd.wordperfect',
            'wpl'=>'application/vnd.ms-wpl',
            'wps'=>'application/vnd.ms-works',
            'wqd'=>'application/vnd.wqd',
            'wri'=>'application/x-mswrite',
            'wrl'=>'model/vrml',
            'wsdl'=>'application/wsdl+xml',
            'wspolicy'=>'application/wspolicy+xml',
            'wtb'=>'application/vnd.webturbo',
            'wvx'=>'video/x-ms-wvx',
            'x32'=>'application/x-authorware-bin',
            'x3d'=>'model/x3d+xml',
            'x3db'=>'model/x3d+binary',
            'x3dbz'=>'model/x3d+binary',
            'x3dv'=>'model/x3d+vrml',
            'x3dvz'=>'model/x3d+vrml',
            'x3dz'=>'model/x3d+xml',
            'xaml'=>'application/xaml+xml',
            'xap'=>'application/x-silverlight-app',
            'xar'=>'application/vnd.xara',
            'xbap'=>'application/x-ms-xbap',
            'xbd'=>'application/vnd.fujixerox.docuworks.binder',
            'xbm'=>'image/x-xbitmap',
            'xdf'=>'application/xcap-diff+xml',
            'xdm'=>'application/vnd.syncml.dm+xml',
            'xdp'=>'application/vnd.adobe.xdp+xml',
            'xdssc'=>'application/dssc+xml',
            'xdw'=>'application/vnd.fujixerox.docuworks',
            'xenc'=>'application/xenc+xml',
            'xer'=>'application/patch-ops-error+xml',
            'xfdf'=>'application/vnd.adobe.xfdf',
            'xfdl'=>'application/vnd.xfdl',
            'xht'=>'application/xhtml+xml',
            'xhtml'=>'application/xhtml+xml',
            'xhvml'=>'application/xv+xml',
            'xif'=>'image/vnd.xiff',
            'xla'=>'application/vnd.ms-excel',
            'xlam'=>'application/vnd.ms-excel.addin.macroenabled.12',
            'xlc'=>'application/vnd.ms-excel',
            'xlf'=>'application/x-xliff+xml',
            'xll'=>'application/vnd.ms-excel',
            'xlm'=>'application/vnd.ms-excel',
            'xls'=>'application/vnd.ms-excel',
            'xlsb'=>'application/vnd.ms-excel.sheet.binary.macroenabled.12',
            'xlsm'=>'application/vnd.ms-excel.sheet.macroenabled.12',
            'xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xlt'=>'application/vnd.ms-excel',
            'xltm'=>'application/vnd.ms-excel.template.macroenabled.12',
            'xltx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
            'xlw'=>'application/vnd.ms-excel',
            'xm'=>'audio/xm',
            'xml'=>'application/xml',
            'xo'=>'application/vnd.olpc-sugar',
            'xop'=>'application/xop+xml',
            'xpi'=>'application/x-xpinstall',
            'xpl'=>'application/xproc+xml',
            'xpm'=>'image/x-xpixmap',
            'xpr'=>'application/vnd.is-xpr',
            'xps'=>'application/vnd.ms-xpsdocument',
            'xpw'=>'application/vnd.intercon.formnet',
            'xpx'=>'application/vnd.intercon.formnet',
            'xsl'=>'application/xml',
            'xslt'=>'application/xslt+xml',
            'xsm'=>'application/vnd.syncml+xml',
            'xspf'=>'application/xspf+xml',
            'xul'=>'application/vnd.mozilla.xul+xml',
            'xvm'=>'application/xv+xml',
            'xvml'=>'application/xv+xml',
            'xwd'=>'image/x-xwindowdump',
            'xyz'=>'chemical/x-xyz',
            'xz'=>'application/x-xz',
            'yang'=>'application/yang',
            'yin'=>'application/yin+xml',
            'z1'=>'application/x-zmachine',
            'z2'=>'application/x-zmachine',
            'z3'=>'application/x-zmachine',
            'z4'=>'application/x-zmachine',
            'z5'=>'application/x-zmachine',
            'z6'=>'application/x-zmachine',
            'z7'=>'application/x-zmachine',
            'z8'=>'application/x-zmachine',
            'zaz'=>'application/vnd.zzazz.deck+xml',
            'zip'=>'application/zip',
            'zir'=>'application/vnd.zul',
            'zirz'=>'application/vnd.zul',
            'zmm'=>'application/vnd.handheld-entertainment+xml'
        );

        if(!$revert){
            if(!isset($extensionToType[$value]))
                throw new Exception('This extension ' . $value . '  is not known');
            return $extensionToType[$value];
        }else{
            $extensionToType = array_flip($extensionToType);
            if(!isset($extensionToType[$value]))
                throw new Exception('This Mime Type is not Know is not known');
            return $extensionToType[$value];
        }
    }


    /////   FONCTIONS CONSERVEES MAIS HORS SCOPE en 08-2014  \\\\\\\


    // Fonctions pour la gestion Import / Export des favoris \\

    /**
     * @param string $filePath
     *
     * @return boolean
     */
    public function isFavoriteFile($filePath)
    {
        if ('' == $filePath) {
            return false;
        }

        $file = fopen($filePath, 'r'); // read only
        $isFavorite = !feof($file) && preg_match('#<!DOCTYPE NETSCAPE-Bookmark-file-1>#', fgets($file, 4096));

        fclose($file);

        return $isFavorite;
    }

    public function writeFavoriteFile($filePath, $destination)
    {
        // Maximum execution time caused by thumbnail generation
        set_time_limit(0);

        $html = new \simple_html_dom();
        $html->load(file_get_contents($filePath), true);
        $links = $html->find('a');

//        TODO : mettre à jour ici
//
//        foreach ($links as $link) {
//            $this->createFromUrl(array(
//                'url'					=> $link->href,
//                'title'					=> preg_replace('/[^(\x20-\x7F)]*/','', $link->plaintext),
//                'destination'			=> $destination,
//                'type'					=> 'LINK',
//                'skip_validation_error' => true
//            ));
//        }
    }

    // GESTION pour les partenaires  \\

    /**
     * @param \BNS\App\ResourceBundle\ProviderResource\ProviderResource $resource
     * @param int $groupId
     * @param ResourceLabel $label
     *
     * @return Resource
     *
     * @throws Exception
     */
    public function createFromProviderResource(ProviderResource $providerResource, $groupId, $label = null)
    {
        $params = array();
        $params['mime_type'] = 'NONE';
        $params['label'] = $providerResource->getLabel();
        $params['value'] = $providerResource->getUrl();
        $params['type'] = 'PROVIDER_RESOURCE';
        $params['description'] = $providerResource->getDescription();

        if (null == $label) {
            $label = ResourceLabelGroupQuery::create('rlag')->findRoot($groupId);
            if (null == $label) {
                throw new \InvalidArgumentException('No root group label for group with id : ' . $groupId);
            }

            $params['destination']['type'] = 'group';
            $params['destination']['object_id'] = $groupId;
        }

        $params['destination']['type'] = $label->getType();
        $params['destination']['object_id'] = $label->getObjectLinkedId();
        $params['destination']['label_id'] = $label->getId();

        // Creating objects
        $resource = $this->createModelDatas($params);
        $resourceProvider = new ResourceProvider();
        $resourceProvider->setResourceId($resource->getId());
        $resourceProvider->setUai($providerResource->getUai());
        $resourceProvider->setReference($providerResource->getId());
        $resourceProvider->setProviderId($providerResource->getProviderId());
        $resourceProvider->save();

        $ext = 'jpg';
        $filePath = $resource->getFilePath() . $resource->getSlug() . '.' . $ext;
        $resource->setFileName($resource->getSlug() . '.' . $ext);

        try {
            $imageName = $this->saveImageFromUrl($providerResource->getImageUrl());
            $this->getFileSystem()->write($filePath,  file_get_contents($this->uploaded_file_dir . '/../' . $imageName));
        }
        catch(\Exception $e) {
            // On ne fait rien
        }

        $resource->setValue($providerResource->getUrl());
        $resource->save();

        return $resource;
    }


    /**
     * @return bytes
     */
    public function getMaxUploadSize()
    {
        $val = min($this->getBytesFromPhpIniValue('post_max_size'), $this->getBytesFromPhpIniValue('upload_max_filesize'));

        return $val;
    }

    protected function getErrorMessage($code) {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
                return 'UPLOAD_ERR_INI_SIZE';
            case UPLOAD_ERR_FORM_SIZE:
                return 'UPLOAD_ERR_FORM_SIZE';
            case UPLOAD_ERR_PARTIAL:
                return 'UPLOAD_ERR_PARTIAL';
            case UPLOAD_ERR_NO_FILE:
                return 'UPLOAD_ERR_NO_FILE';
            case UPLOAD_ERR_CANT_WRITE:
                return 'UPLOAD_ERR_CANT_WRITE';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'UPLOAD_ERR_NO_TMP_DIR';
            case UPLOAD_ERR_EXTENSION:
                return 'UPLOAD_ERR_EXTENSION';
        }

        return 'UPLOAD_ERR';
    }

    protected function getBytesFromPhpIniValue($phpini)
    {
        $val = ini_get($phpini);
        if (empty($val)) {
            return false;
        }

        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        switch($last) {
            // Le modifieur 'G' est disponible depuis PHP 5.1.0
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }

        return $val;
    }

    /**
     * @param $slug
     * @param string $replacement
     * @return string filename cleanup
     */
    protected function cleanupFileName($slug, $replacement = '-')
    {
        // transliterate
        if (function_exists('iconv')) {
            $slug = iconv('utf-8', 'us-ascii//TRANSLIT', $slug);
        }

        // lowercase
        if (function_exists('mb_strtolower')) {
            $slug = mb_strtolower($slug);
        } else {
            $slug = strtolower($slug);
        }

        // remove accents resulting from OSX's iconv
        $slug = str_replace(array('\'', '`', '^'), '', $slug);

        // replace non letter or digits with separator
        $slug = preg_replace('/\W+/', $replacement, $slug);

        // trim
        $slug = trim($slug, $replacement);

        if (empty($slug)) {
            return 'n-a';
        }

        return $slug;
    }
}
