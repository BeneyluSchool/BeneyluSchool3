<?php

namespace BNS\App\ResourceBundle;

use \Exception;
use \Criteria;

use BNS\App\ResourceBundle\Model\ResourceQuery,
	BNS\App\ResourceBundle\Model\ResourceWhiteListQuery,
	BNS\App\CoreBundle\Model\GroupQuery,
	BNS\App\ResourceBundle\Model\ResourceInternetSearch,
    BNS\App\CoreBundle\Access\BNSAccess;



/**
 * @author Eymeric Taelman
 * Classe permettant la gestion des Ressources
 */

class BNSResourceManager
{	
	// Status de suppression
	 
	const STATUS_ACTIVE = '1';
	const STATUS_GARBAGED = '0';
	const STATUS_DELETED = '-1';
	
	//Types de ressources supportés et leur paramètrage
	public $types = array(
		"IMAGE" =>
			array(
				'template'		=> 'image',
				'thumbnailable' => true,
				'sizeable'		=> true
			),
		//Pas encore
		/*"VIDEO" =>
			array(
				'template' => 'file',
				'thumbnailable' => false,
				'sizeable'		=> true
			),*/
		"DOCUMENT" =>
			array(
				'template' => 'file',
				'thumbnailable' => false,
				'sizeable'		=> true
			),
		"AUDIO" =>
			array(
				'template' => 'audio',
				'thumbnailable' => false,
				'sizeable'		=> true
			),
		"LINK" =>
			array(
				'template' => 'link',
				'thumbnailable' => true,
				'sizeable'		=> false
			),
		"EMBEDDED_VIDEO" =>
			array(
				'template' => 'embeddedVideo',
				'thumbnailable' => true,
				'sizeable'		=> false
			),
		"FILE" =>
			array(
				'template' => 'file',
				'thumbnailable' => false,
				'sizeable'		=> true
			)
	);
	
	protected $user;
	protected $user_manager;
	protected $group_manager;
	protected $file_system_manager;
	protected $resource_file_dir;
		
	public function __construct($tools_dir,$user_manager,$group_manager,$file_system_manager,$resource_file_dir)
	{
		$this->tools_dir = $tools_dir;
		$this->user_manager = $user_manager;
		$this->group_manager = $group_manager;
		$this->file_system_manager = $file_system_manager;
		$this->resource_file_dir = $resource_file_dir;
				
		require $this->tools_dir . "simple_html_dom.php";
		require $this->tools_dir . "videopian.php";
		require $this->tools_dir . "/url_to_absolute/url_to_absolute.php";
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
	}
	
	/*
	 * Set de l'Object (Resource) depuis son Id
	 * @param $object_id Integer
	 */	
	public function setObjectFromId($object_id)
	{
		$resource = ResourceQuery::create()->findOneByPk($object_id);
		if(!$resource)
			throw new Exception('Resource does not exist');
		$this->setObject($resource);
	}
	
	/*
	 * Get de l'Object (Resource)
	 * @return $object Object
	 */
	public function getObject()
	{
		if(!isset($this->object))
			throw new Exception('Object is not set');
		return $this->object;
	}
	
	/*
	 * Check de l'object
	 */
	public function checkObject()
	{
		if(!isset($this->object)){
			throw new Exception('Please provide an Object');
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
	public function read($size = null,$encoded = false)
	{
		if(!$encoded)
			$path = $this->getObject()->getFilePath($size);
		else
			$path = $this->getObject()->getEncodedContentPath($size);
		
		if($this->getFileSystem()->has($path)){
			return $this->getFileSystem()->read($path);
		}else{
			return false;
		}
	}
	
	/**
	 * Retour une image selon le type de ressource
	 * @return string path
	 */
	public function getFileTypeImageUrl($size){
		return '/medias/images/resource/filetype/' . $size . '/' . strtolower($this->getObject()->getTypeUniqueName()) . '.png';
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
		return 'data:' . $resource->getFileMimeType() . ';base64,' . $this->getEncodedImageContent($size);
	}
	
	public function getEncodedImageContent($size = null)
	{
		if(!$this->getFileSystem()->has($this->getObject()->getEncodedContentPath($size))){
			$this->getFileSystem()->write($this->getObject()->getEncodedContentPath($size),base64_encode($this->read($size)));
		}
		return $this->read($size, true);
	}
	
	//////////////////////     Fonctions de suppression     \\\\\\\\\\\\\\\\\\\\\\\\
		
	/*
	 * Suppression d'une ressource : les droits ont été vérifiés dans le controller
	 */
	public function delete($resource){
		//On a le droit
		//2 niveaux de suppression
		switch($resource->getStatusDeletion()){
			case self::STATUS_ACTIVE:
				$resource->setStatusDeletion(self::STATUS_GARBAGED);
				$resource->save();
			break;
			case self::STATUS_GARBAGED:
				$resource->setStatusDeletion(self::STATUS_DELETED);
				$resource->save();
				/**
				 * Mise à jour des espace de stockage
				 */
				$this->deleteSize($resource);
			break;
		}
	}
	/**
	 * Renvoie une image pour les ressources supprimées
	 */
	public function getDeletedImage(){
		return $this->resource_file_dir . '/./deleted.png';
	}
	
	/*
	 * Restauration d'une ressource : les droits ont été vérifiés dans le controller
	 */
	public function restore($resource){
		//On a le droit
		//2 niveaux de suppression
		switch($resource->getStatusDeletion()){
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
	public function addSize($resource,$size = null)
	{
		if($size == null)
			$size = $this->getSize($resource);
		
		$label = $resource->getStrongLinkedLabel();
		if($label){
			if($label->getType() == 'user'){
				$label->getUser()->addResourceSize($size);
			}
			if($label->getType() == 'group'){
				$label->getGroup()->addResourceSize($size);
			}
		}
	}
	
	/*
	 * Enlève la taille de la ressource à l'espace "strong"
	 * @param $resource Resource
	 * @param $size String la taille
	 */
	public function deleteSize($resource,$size = null)
	{
		if($size == null)
			$size = $this->getSize($resource);
		$label = $resource->getStrongLinkedLabel();
		if($label){
			if($label->getType() == 'user'){
				$label->getUser()->deleteResourceSize($size);
			}
			if($label->getType() == 'group'){
				$label->getGroup()->deleteResourceSize($size);
			}
		}
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
	
	
	
	/*
	 * @param string $string_query la recherche
	 * @param int $user_id : utilisateur faisant la recherche
	 * @param array $group_ids Groupes dans lesquels l'utilisateur peut faire la recherche
	 * @param array paramètres de la recherche
	 */
	public function search($string_query = null,$user_id = null,$group_ids = null,$params = array())
	{
		
		$query = ResourceQuery::create();
		
		if($string_query != null){
			$query	
				->where("Resource.Label LIKE ?","%" . $string_query . "%")
				->orWhere("Resource.Filename LIKE ?","%" . $string_query . "%")
				->orWhere("Resource.Description LIKE ?","%" . $string_query . "%");
		}
		
		if(isset($params['types'])){
			$query->filterByTypeUniqueName($params['types']);
		}
		
		if($group_ids){	
			$query
				->useResourceLinkGroupQuery()
					->useResourceLabelGroupQuery()
						->filterByGroupId($group_ids)
					->endUse()
				->endUse();
		}
		
		if($user_id){	
			$query
				->useResourceLinkUserQuery(null,Criteria::LEFT_JOIN)
					->useResourceLabelUserQuery(null,Criteria::LEFT_JOIN)
						->filterByUserId($user_id)
					->endUse()
				->endUse()
				->useResourceLinkGroupQuery(null,Criteria::LEFT_JOIN)
					->useResourceLabelGroupQuery(null,Criteria::LEFT_JOIN)
						->filterByGroupId($group_ids)
					->endUse()
				->endUse();			
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
}