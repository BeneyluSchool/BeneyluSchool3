<?php

namespace BNS\App\ResourceBundle;

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Utils\Crypt;
use BNS\App\ResourceBundle\Model\Resource;
use BNS\App\ResourceBundle\Model\ResourceInternetSearch;
use BNS\App\ResourceBundle\Model\ResourceLabelGroup;
use BNS\App\ResourceBundle\Model\ResourceLabelUser;
use BNS\App\ResourceBundle\Model\ResourceLinkGroupQuery;
use BNS\App\ResourceBundle\Model\ResourceLinkUserQuery;
use BNS\App\ResourceBundle\Model\ResourceQuery;
use BNS\App\ResourceBundle\Model\ResourceWhiteListQuery;

/**
 * @author Eymeric Taelman
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 *
 * Classe permettant la gestion des Ressources
 */
class BNSResourceManager
{
	// Status de suppression

	const STATUS_ACTIVE = '1';
	const STATUS_GARBAGED = '0';
	const STATUS_DELETED = '-1';

    const OVH_PCS_TYPE = 'ovh_pcs';

	// Durée en secondes de l'accessibilité temporaire
	const TEMP_DOWNLOADABLE_DURATION = '120';

	//Types de ressources supportés et leur paramètrage
	public $types = array(
		'IMAGE' => array(
			'template'		=> 'image',
			'thumbnailable' => true,
			'sizeable'		=> true
		),
		'VIDEO' => array(
			'template'		 => 'video',
			'thumbnailable'	 => false,
			'sizeable'		 => true
		),
		'DOCUMENT' => array(
			'template'		=> 'file',
			'thumbnailable' => false,
			'sizeable'		=> true
		),
		'AUDIO' => array(
			'template'		=> 'audio',
			'thumbnailable' => false,
			'sizeable'		=> true
		),
		'LINK' => array(
			'template'		=> 'link',
			'thumbnailable' => true,
			'sizeable'		=> false
		),
		'EMBEDDED_VIDEO' => array(
			'template'		=> 'embeddedVideo',
			'thumbnailable' => true,
			'sizeable'		=> false
		),
		'FILE' => array(
			'template'		=> 'file',
			'thumbnailable' => false,
			'sizeable'		=> true
		),
        'ATELIER_DOCUMENT' => array(
			'template'		=> 'atelier_document',
			'thumbnailable' => false,
			'sizeable'		=> false
		),
        'PROVIDER_RESOURCE' => array(
			'template'		=> 'provider_resource',
			'thumbnailable' => true,
			'sizeable'		=> false
		)
	);

	protected $user;
	protected $user_manager;
	protected $group_manager;
	protected $file_system_manager;
	protected $resource_file_dir;
	protected $resource_deleted_dir;
	protected $encode_key;
	protected $timeToLive;

	public function __construct($tools_dir, $user_manager, $group_manager, $file_system_manager, $resource_file_dir, $resource_deleted_dir, $encode_key, $timeToLive)
	{
		$this->tools_dir = $tools_dir;
		$this->user_manager = $user_manager;
		$this->group_manager = $group_manager;
		$this->file_system_manager = $file_system_manager;
		$this->resource_file_dir = $resource_file_dir;
		$this->resource_deleted_dir = $resource_deleted_dir;
		$this->encode_key = $encode_key;
		$this->timeToLive = $timeToLive;
	}

	//////////////////////     Fonctions racourcies     \\\\\\\\\\\\\\\\\\\\\\\\

	/*
	 * Renvoie le filesystem de l'application (s3 ou local ... ou autre !)
	 * @return FileSystem
	 */
	public function getFileSystem()
	{
		return $this->file_system_manager->getFileSystem();
	}

	/*
	 * renvoie le temp dir du fileSystem (répertoire local)
	 * @return $path
	 */
	public function getTempDir()
	{
		return $this->file_system_manager->getTempDir();
	}

    /*
     * Renvoie la méthode de stockage : local, s3, ovh_pcs
     */
    public function getResourceStorageType()
    {
        return $this->file_system_manager->getResourceStorageType();
    }

	//Utilisateur (User)

	/*
	 * Set du User (auteur)
	 * @param $user User
	 */
	public function setUser($user)
	{
		$this->user = $user;
	}

	/*
	 * Get du User (auteur)
	 * @return User
	 */
	public function getUser()
	{
		if(!isset($this->user)){
			$this->setUser($this->getRightManager()->getUserSession());
		}
		return $this->user;
	}

	/*
	 * Check du User
	 */
	public function checkUser()
	{
		if(!isset($this->user)){
			throw new Exception('Please provide an User to use ressources');
		}
	}

	public function getUserManager()
	{
		return $this->user_manager;
	}

	public function getGroupManager()
	{
		return $this->group_manager;
	}

	public function getResourceRightManager()
	{
		return BNSAccess::getContainer()->get('bns.resource_right_manager');
	}

	public function getRightManager()
	{
		return BNSAccess::getContainer()->get('bns.right_manager');
	}

	//Object (Resource)

	/*
	 * Set de l'Object (Resource)
	 * @param $object Object
	 */
	public function setObject($object)
	{
		$this->object = $object;

        return $this;
	}

	/*
	 * Set de l'Object (Resource) depuis son Id
	 * @param $object_id Integer
	 */
	public function setObjectFromId($object_id)
	{
		$resource = ResourceQuery::create()->findOneById($object_id);
		if(!$resource)
			throw new \Exception('Resource does not exist');
		$this->setObject($resource);
	}

	/*
	 * Get de l'Object (Resource)
	 * @return $object Object
	 */
	public function getObject()
	{
		if(!isset($this->object))
			throw new \Exception('Object is not set');
		return $this->object;
	}

	/*
	 * Check de l'object
	 */
	public function checkObject()
	{
		if(!isset($this->object)){
			throw new \Exception('Please provide an Object');
		}
	}

	//Types

	public function getTypes()
	{
		return array_keys($this->types);
	}

	public function getTemplateName()
	{
		return $this->types[$this->getObject()->getTypeUniqueName()]['template'];
	}

	//////////////////////     Fonctions de lecture     \\\\\\\\\\\\\\\\\\\\\\\\


	/*
	 * Lit un fichier depuis le fileSystem
	 * Le télécharge de S3 si fileSystem == s3
	 * @param $size : taille si image ou contenu ayant une image
	 * @param $encoded : pour les images, renvoyer le contenu 'base64' pour affichage sans requettage
	 */
	public function read($size = null, $encoded = false)
	{
		if (!$encoded) {
			$path = $this->getObject()->getFilePath($size);
		} else {
			$path = $this->getObject()->getEncodedContentPath($size);
		}
		if ($this->getFileSystem()->has($path)) {
			return $this->getFileSystem()->read($path);
		} elseif (null !== $size && 'original' !== $size && $this->isThumbnailable($this->getObject())) {
		    // On créé à la volé les miniature
	        // TODO remove this dirty BNSAccess call
	        BNSAccess::getContainer()->get('bns.resource_creator')->createThumb($size);
	        if ($this->getFileSystem()->has($path)) {
	            return $this->getFileSystem()->read($path);
	        }
		}
		return false;
	}

	/**
	 * @return string
	 */
	public function getTempUrl()
	{
		$validity = date('U') + self::TEMP_DOWNLOADABLE_DURATION;

		return BNSAccess::getContainer()->get('router')->generate('resource_file_download_temporary', array(
				'validity'		 => $validity,
				'resource_slug'	 => $this->getObject()->getSlug(),
				'key'			 => Crypt::encode($validity . $this->getObject()->getSlug() . $this->encode_key)
			),true
		);
	}

	/**
	 * @param type $key
	 * @param type $validity
	 *
	 * @return string
	 */
	public function checkTempUrlKey($key, $validity)
	{
		return Crypt::encode($validity . $this->getObject()->getSlug() . $this->encode_key) == $key;
	}

	/**
	 * Retour une image selon le type de ressource
	 * @return string path
	 */
	public function getFileTypeImageUrl($size)
	{
		return BNSAccess::getContainer()->get('templating.helper.assets')->getUrl('/medias/images/resource/filetype/' . $size . '/' . strtolower($this->getObject()->getTypeUniqueName()) . '.png');
	}

	/*
	 * Renvoie le chemin local du fichier : on lit avant le fichier pour être certain de sa présence en local
	 * @return $path
	 */
	public function getAbsoluteFilePath($size = null)
	{
		$path = $this->resource_file_dir . '/' . $this->getObject()->getFilePath($size);
		if(!is_file($path)){
			//On rappatrie ainsi le fichier en local
			$this->read($size);
		}

		return $this->resource_file_dir . '/' . $this->getObject()->getFilePath($size);
	}


	/*
	 * Renvoie "l'url" en data-64 pour les images
	 * @return $path
	 */
	public function getDataUrl($size = null)
	{
		//On rappatrie ainsi le fichier en local
		$resource = $this->getObject();

        //si OVH PCS on bypass car on met l'url en direct
        if($this->getResourceStorageType() == self::OVH_PCS_TYPE)
        {
            //return $resource->getOvhPcsTempUrl($size);
        }

		if ($this->getFileSystem()->has($this->getObject()->getFilePath($size))) {
			return 'data:' . $resource->getFileMimeType() . ';base64,' . $this->getEncodedImageContent($size);
		} else if ($this->read($size)) {
		    return $this->getDataUrl($size);
		} else {
			return $this->getFileTypeImageUrl($size);
		}
	}

	public function getEncodedImageContent($size = null)
	{
		if(!$this->getFileSystem()->has($this->getObject()->getEncodedContentPath($size))){
			$this->getFileSystem()->write($this->getObject()->getEncodedContentPath($size), base64_encode($this->read($size)));
		}
		return $this->read($size, true);
	}

	//////////////////////     Fonctions de suppression     \\\\\\\\\\\\\\\\\\\\\\\\

	/**
	 * @param Resource $resource
	 * @param int      $userId
	 * @param string   $labelType user|group
	 * @param int	   $labelId
	 * @param boolean  $isLastOccurrence
	 */
	public function delete($resource, $userId, $labelType, $labelId, $isLastOccurrence)
	{
		if ($labelType == 'group') {
			$link = ResourceLinkGroupQuery::create('rl')
				->where('rl.ResourceId = ?', $resource->getId())
				->where('rl.ResourceLabelGroupId = ?', $labelId)
			->findOne();
		}
		// ResourceLabelUser
		else {
			$link = ResourceLinkUserQuery::create('rl')
				->join('rl.ResourceLabelUser rla')
				->where('rl.ResourceId = ?', $resource->getId())
				->where('rl.ResourceLabelUserId = ?', $labelId)
				->where('rla.UserId = ?', $userId)
			->findOne();
		}

		// Last occurrence ? Put resource to garbage to disable copies
		if ($isLastOccurrence) {
			$resource->setStatusDeletion(Resource::DELETION_STATUS_GARBAGE);
			$resource->save();
		}

		// Soft delete
		$link->setStatus(0);
		$link->save();
	}

	/**
	 * Renvoie une image pour les ressources supprimées
	 */
	public function getDeletedImage($resource = null)
	{
		if (null == $resource) {
			$type = 'document';
		}
		else {
			$type = strtolower($resource->getTypeUniqueName());
		}

		return $this->resource_deleted_dir . '/' . $type . '.jpg';
	}

	/*
	 * Restauration d'une ressource : les droits ont été vérifiés dans le controller
	 */

	public function restore($resource)
	{
		//On a le droit
		//2 niveaux de suppression
		switch ($resource->getStatusDeletion()) {
			case self::STATUS_GARBAGED:
				$resource->setStatusDeletion(self::STATUS_ACTIVE);
				$resource->save();
			break;
			case self::STATUS_DELETED:
				$resource->setStatusDeletion(self::STATUS_ACTIVE);
				$resource->save();
				/**
				 * Mise à jour des espace de stockage
				 */
				$this->addSize($resource);
			break;
		}
	}


	//////////////   Fonctions liées à la taille des ressources   \\\\\\\\\\\\\\\

	/*
	 * Ajoute la taille de la ressource à l'espace "strong"
	 * @param $resource Resource
	 * @param $size String la taille
	 */
	public function addSize($resource, $size = null, $label = null)
	{
		if ($size == null) {
			$size = $this->getSize($resource);
		}

		if (!$label) {
		    $label = $resource->getStrongLinkedLabel();
		}
		if ($label) {
			if ($label->getType() == 'user') {
				$label->getUser()->addResourceSize($size);
			}

			if ($label->getType() == 'group') {
				$label->getGroup()->addResourceSize($size);
			}
		}
	}

	/*
	 * Enlève la taille de la ressource à l'espace "strong"
	 * @param $resource Resource
	 * @param $size String la taille
	 */

	public function deleteSize($resource, $size = null)
	{
		if ($size == null) $size = $this->getSize($resource);
		$label = $resource->getStrongLinkedLabel();
		if ($label) {
			if ($label->getType() == 'user') {
				$label->getUser()->deleteResourceSize($size);
			}
			if ($label->getType() == 'group') {
				$label->getGroup()->deleteResourceSize($size);
			}
		}
	}

	/**
	 * @param int $type group or user
	 * @param int $id groupId or userId
	 */
	public function recalculateQuota($type, $id, $object = null)
	{
		if ($type == 'user') {
			$links = ResourceLinkUserQuery::create('rlu')
				->joinWith('rlu.Resource r')
				->join('rlu.ResourceLabelUser rlau')
				->where('rlau.UserId = ?', $id)
				->where('r.TypeUniqueName IN ?', array('IMAGE', 'VIDEO', 'DOCUMENT', 'AUDIO', 'FILE'))
				->groupBy('rlu.ResourceId')
			->find();
		}
		elseif ($type == 'group') {
			$links = ResourceLinkGroupQuery::create('rlg')
				->joinWith('rlg.Resource r')
				->join('rlg.ResourceLabelGroup rlag')
				->where('rlag.GroupId = ?', $id)
				->where('r.TypeUniqueName IN ?', array('IMAGE', 'VIDEO', 'DOCUMENT', 'AUDIO', 'FILE'))
				->groupBy('rlg.ResourceId')
			->find();
		}
		else {
			throw new \RuntimeException('recalculateQuota() : type is unknown');
		}

		$totalSize = 0.;
		foreach ($links as $link) {
			$totalSize += $link->getResource()->getSize(false);
		}

		if (null == $object && $type == 'user') {
			$object = UserQuery::create('u')->findPk($id);
		}
		elseif (null == $object && $type == 'group') {
			$object = GroupQuery::create('g')->findPk($id);
		}

		$object->setResourceUsedSize($totalSize);
		$object->save();
	}

	/*
	 * La resource est elle "sizeable" (typiquement un fichier l'est, un lien ne l'est pas)
	 * @param $resource Resource
	 * @return boolean
	 */
	public function isSizeable($resource)
	{
		return $this->types[$resource->getTypeUniqueName()]['sizeable'];
	}

	/*
	 * Renvoie la taille
	 */
	public function getSize($resource){
		$this->setObject($resource);
		if($this->isSizeable($resource)){
			return filesize($this->getAbsoluteFilePath());
		}
		return 0;
	}

	public function isThumbnailable($resource)
	{
		return $this->types[$resource->getTypeUniqueName()]['thumbnailable'];
	}



	/**
	 * @param string $string_query la recherche
	 * @param int $user_id : utilisateur faisant la recherche
	 * @param array $group_ids Groupes dans lesquels l'utilisateur peut faire la recherche
	 * @param array paramètres de la recherche
	 */
	public function search($string_query = null, $user_id = null, $group_ids = null, $params = array())
	{
		$query = ResourceQuery::create('r');
		if ($string_query != null) {
			$query
				->where('r.Label LIKE ?', '%' . $string_query . '%')
				->orWhere('r.Filename LIKE ?', '%' . $string_query . '%')
				->orWhere('r.Description LIKE ?', '%' . $string_query . '%')
				->where('r.StatusDeletion = ?', self::STATUS_ACTIVE)
			;
		}

		if (isset($params['types'])) {
			$query->filterByTypeUniqueName($params['types']);
		}

		if ($group_ids) {
			$query
				->joinWith('r.ResourceLinkGroup rlinkg', \Criteria::LEFT_JOIN)
				->joinWith('rlinkg.ResourceLabelGroup rlg', \Criteria::LEFT_JOIN)
				->where('rlg.GroupId IN ?', $group_ids)
			;
		}

		if ($user_id) {
			$query
				->joinWith('r.ResourceLinkUser rlinku', \Criteria::LEFT_JOIN)
				->joinWith('rlinku.ResourceLabelUser rlu', \Criteria::LEFT_JOIN)
			;

			if (null != $group_ids) {
				$query->orWhere('rlu.UserId = ?', $user_id);
			}
			else {
				$query->where('rlu.UserId = ?', $user_id);
			}
		}

		return $query->find();
	}

	public function downloadImage($url,$name = null){

		if($url == null || $url == ""|| $url == false)
			return false;
		$exp = explode('/',$url);

		if($name == null)
			$name = array_pop($exp);

		$parsed_url = parse_url($url);

		$serv = $parsed_url['scheme'] . '://' . $parsed_url['host'];

		$xcontext = stream_context_create(array("http"=>array("header"=>"Referer: ".$serv."\r\n")));
		return file_get_contents($url,false,$xcontext);
	}

	//////////////  Fonctions liées à la gestion de Whitelist  \\\\\\\\\\\

	public function getWhiteList($groupId)
	{
		$wl = ResourceWhiteListQuery::create()->setFormatter('PropelArrayFormatter')->findByGroupId($groupId);
		$return = array();

		foreach($wl as $link){
			$return[] = $link['ResourceId'];
		}
		return $return;
	}

	public function getWhiteListObjects($groupId)
	{
		$wl = $this->getWhiteList($groupId);
		return ResourceQuery::create()->findById($wl);
	}

	public function updateUniqueKey($groupId)
	{
		$group = GroupQuery::create()->findOneById($groupId);
		$group->setAttribute('WHITE_LIST_UNIQUE_KEY',md5($groupId . date('U')));
	}

	public function toggleWhiteList($resourceId,$groupId)
	{

		//Mise à jour du unique Key du groupe
		$this->updateUniqueKey($groupId);

		$query = ResourceWhiteListQuery::create()->filterByGroupId($groupId)->filterByResourceId($resourceId);
		if($query->findOne()){
			$query->delete();
			return false;
		}else{
			$query->findOneOrCreate()->save();
			return true;
		}
	}

	//////////////  Fonctions liées aux pièces jointes    \\\\\\\\\\\\\

	public function bindAttachments($object,$request){
		if(!$object || !$request){
			throw new Exception('Attachements can not be binded : please provide request and object');
		}

		$object->attachments = null;

		$attachements = $request->get('resource-joined');
		if(is_array($attachements)){
			$resourceRightManager = $this->getResourceRightManager();
			$resourceRightManager->setUser($this->getUser());
			$resources = ResourceQuery::create()->findById($attachements);
			if($resources){
				foreach($resources as $resource){
					if($resourceRightManager->canReadResource($resource)){
						$object->attachments[] = $resource;
					}
				}
			}
		}
	}

	public function saveAttachments($object,$request,$links = null){
		if(!$object || !$request){
			throw new Exception('Attachements can not be saved : please provide request and object');
		}

		$object->deleteAllResourceAttachments();

		$attachements = $request->get('resource-joined');
		if(is_array($attachements)){
			$resourceRightManager = $this->getResourceRightManager();
			$resourceRightManager->setUser($this->getUser());
			$resources = ResourceQuery::create()->findById($attachements);
			if($resources){
				foreach($resources as $resource){
					if($resourceRightManager->canReadResource($resource)){
						$attachment = $object->addResourceAttachment($resource->getId());
						if($links){
							if(!is_array($links)){
								$links = array($links);
							}
							foreach($links as $link){
								if($link->getClassName() == 'User'){
									$object->addResourceAttachmentsLinkUsers($attachment->getId(),$link->getId());
								}elseif($link->getClassName() == 'Group'){
									$object->addResourceAttachmentsLinkGroups($attachment->getId(),$link->getId());
								}
							}
						}
					}
				}
			}
		}
	}

	public function getAttachmentsId($request){

		$attachementsId = $request->get('resource-joined');

		return $attachementsId;
	}

	//////////////  Fonctions liées au moteur de recherche Google  \\\\\\\\\\\\\\\\

	/**
	 * Ajout d'une recherche pour historique
	 * @param type $label le terme recherché
	 * @param type $userId l'utilisateur faisant la recherche
	 */
	public function addSearchInternet($label,$userId)
	{
		$search = new ResourceInternetSearch();
		$search->setUserId($userId);
		$search->setLabel($label);
		$search->save();
	}

	/**
	 * @param Resource $resource
	 * @param int	   $userId
	 * @param int	   $labelId
	 * @param boolean  $canManage
	 *
	 * @return boolean
	 */
	public function isLastOccurrence(Resource $resource, $userId, $labelId, $canManage)
	{
		$isUsedByUser = ResourceLinkUserQuery::create('rlu')
			->join('rlu.ResourceLabelUser rlau')
			->where('rlu.ResourceId = ?', $resource->getId())
			->where('rlau.UserId = ?', $userId)
			->where('rlau.Id != ?', $labelId)
		->findOne();

		if (null != $isUsedByUser) {
			return false;
		}

		if ($canManage) {
			$isUsedByGroup = ResourceLinkGroupQuery::create('rlg')
				->where('rlg.ResourceId = ?', $resource->getId())
				->where('rlg.ResourceLabelGroupId != ?', $labelId)
			->findOne();

			if (null != $isUsedByGroup) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param Resource $resource
	 * @param int	   $userId
	 * @param boolean  $canManage
	 *
	 * @return boolean
	 */
	public function hasForeignOccurrence(Resource $resource, $userId, $canManage)
	{
		$isUsedByUser = ResourceLinkUserQuery::create('rlu')
			->join('rlu.ResourceLabelUser rlau')
			->where('rlu.ResourceId = ?', $resource->getId())
			->where('rlau.UserId != ?', $userId)
		->findOne();

		if (null != $isUsedByUser) {
			return true;
		}

		if (!$canManage) {
			$isUsedByGroup = ResourceLinkGroupQuery::create('rlg')
				->where('rlg.ResourceId = ?', $resource->getId())
			->findOne();

			if (null != $isUsedByGroup) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return string
	 */
	public function getHtml5VideoMimeType()
	{
		$mimeType = $this->getObject()->getFileMimeType();
		switch ($mimeType) {
			case 'application/ogg':
				return 'video/ogg';
			case 'video/x-ms-wmv':
			case 'video/x-ms-wm':
			case 'video/x-ms-wmx':
				return 'video/wmv';
            case 'video/x-flv':
                return 'video/x-flv';
		}

		return 'video/mp4';
	}

	/**
	 * @return string
	 */
	public function getHtml5AudioMimeType()
	{
		$mimeType = $this->getObject()->getFileMimeType();
		switch ($mimeType) {
			case 'application/ogg': return 'audio/ogg';
			case 'audio/x-wav': return 'audio/wav';
		}

		return 'audio/mp3';
	}

	/**
	 * Retourne les groupes auquels appartiennent le document
	 * Si c'est un document déposé dans un dossier utilisateur, je retourne le groupe de l'utilisateur
	 * Si c'est un document déposé dans un dossier du groupe, je retourne le groupe lié au dossier
	 *
	 * @param Resource $resource
	 *
	 * @return array<Integer> Les ids des groupes
	 */
    public function getResourceGroupIds($resource)
    {
        // The resource must be joinWith links & labels before
        $labels = $resource->getAllLabels(true);
        $groupIds = array();
        $user = $this->user_manager->getUser();

        foreach ($labels as $label) {
            if ($label instanceof ResourceLabelGroup) {
                $groupIds[] = $label->getGroupId();
            }
            elseif ($label instanceof ResourceLabelUser) {
                $groupIds += $this->user_manager->setUser($label->getUser())->getGroupIdsWherePermission('RESOURCE_ACCESS');
            }
            else {
                throw new \RuntimeException('Unkwown label class for resource #' . $resource->getId() . ', ' . get_class($label));
            }
        }
        $this->user_manager->setUser($user);

        return $groupIds;
    }

	/**
	 * @see BNSResourceManager::getResourceGroupIds()
	 *
	 * @param Resource $resource
	 *
	 * @return array<Group>
	 */
	public function getResourceGroups($resource)
	{
		$groupIds = $this->getResourceGroupIds($resource);

		return GroupQuery::create('g')
			->where('g.Id IN ?', $groupIds)
		->find();
	}

	/**
	 * @param Resource $resource
	 *
	 * @return string
	 */
	public function generatePublicHash($resource)
	{
		$params = array(
			$resource->getId(),
			$resource->getUserId(),
			time() + $this->timeToLive
		);

		return urlencode(Crypt::encrypt(join('___', $params), $this->encode_key));
	}

	/**
	 * @param Resource $resource
	 *
	 * @return string
	 */
	public function generateVisualizeHash($resource)
	{
		$params = array(
			$resource->getId(),
			$resource->getUserId(),
			$resource->getSlug()
		);

		return urlencode(Crypt::encrypt(join('___', $params), $this->encode_key));
	}
}