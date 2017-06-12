<?php

namespace BNS\App\ResourceBundle\Model;

use \BNS\App\CoreBundle\Access\BNSAccess;
use \BNS\App\CoreBundle\Model\GroupQuery;
use \BNS\App\CoreBundle\Model\UserQuery;
use \BNS\App\ResourceBundle\BNSResourceManager;
use \BNS\App\ResourceBundle\Model\om\BaseResource;
use \BNS\App\ResourceBundle\Model\ResourceFavorites;
use \BNS\App\ResourceBundle\Model\ResourceFavoritesQuery;
use \BNS\App\ResourceBundle\Model\ResourceLinkGroupQuery;
use \BNS\App\ResourceBundle\Model\ResourceLinkUser;

class Resource extends BaseResource
{
	const DELETION_STATUS_DELETED = -1;
	const DELETION_STATUS_GARBAGE = 0;
	const DELETION_STATUS_ACTIVE  = 1;

	/**
	 * @var ResourceLabel
	 */
	private $strongLink;

	/**
	 * @var array<ResourceLabelUser> all user labels
	 */
	private $userLabels;

	/**
	 * @var array<ResourceLabelGroup> all group labels
	 */
	private $groupLabels;


	public function isImage()
	{
		return $this->getTypeUniqueName() == "IMAGE";
	}

	public function isEmbeddedVideo()
	{
		return $this->getTypeUniqueName() == "EMBEDDED_VIDEO";
	}

	public function isLink()
	{
		return $this->getTypeUniqueName() == "LINK";
	}
    
	public function isProviderResource()
	{
		return $this->getTypeUniqueName() == 'PROVIDER_RESOURCE';
	}

	/*
	 * Linkage ressource / label_group || label_user
	 */
	public function linkLabel($type = 'group', $label_id, $is_strong = false)
	{
        if ($type != 'group' && $type != 'user') {
            throw new \InvalidArgumentException('Unknown resource label type for ' . $type);
        }
        
		if ($type == 'group') {
			$link = new ResourceLinkGroup();
			$link->setResourceLabelGroupId($label_id);
		}
        elseif ($type == 'user') {
			$link = new ResourceLinkUser();
			$link->setResourceLabelUserId($label_id);
		}
        
        // Strong_link correspond aux attachements forts (pour la taille notammenet et les droits)
        $link->setIsStrongLink($is_strong);
        $link->setResourceId($this->getId());
        $link->save();
        
		return $link;
	}

	/*
	 * Unlinkage ressource / label_group || label_user
	 */
	public function unlinkLabel($type = 'group',$label_id){
		if($type == 'group'){
			$labelQuery = ResourceLinkGroupQuery::create();
			$labelQuery	->filterByResourceLabelGroupId($label_id);
		}elseif($type == 'user'){
			$labelQuery = ResourceLinkUserQuery::create();
			$labelQuery	->filterByResourceLabelUserId($label_id);
		}
		$labelQuery->filterByResourceId($this->getId());
		$labelQuery->delete();
	}

	/**
	 * @return ResourceLabelGroup
	 */
	public function getStrongLinkedGroup()
	{
		if (!isset($this->strongLink)) {
			$this->strongLink = GroupQuery::create()
				->useResourceLabelGroupQuery()
					->useResourceLinkGroupQuery()
						->filterByIsStrongLink(true)
						->filterByResourceId($this->getId())
					->endUse()
				->endUse()
			->findOne();
		}

		return $this->strongLink;
	}

	/**
	 * @return ResourceLabelUser
	 */
	public function getStrongLinkedUser()
	{
		if (!isset($this->strongLink)) {
			$this->strongLink = UserQuery::create()
				->useResourceLabelUserQuery()
					->useResourceLinkUserQuery()
						->filterByIsStrongLink(true)
						->filterByResourceId($this->getId())
					->endUse()
				->endUse()
			->findOne();
		}

		return $this->strongLink;
	}

	/**
	 * FIXME rename the method, it retreives the first linked label
	 *
	 * @return ResourceLabel
	 */
	public function getStrongLinkedLabel()
	{
		if (!isset($this->strongLink)) {
			$this->strongLink = ResourceLabelUserQuery::create('rlau')
				->join('rlau.ResourceLinkUser rlu')
				->where('rlu.ResourceId = ?', $this->getId())
				->where('rlau.UserId = ?', $this->getUserId())
			->findOne();

			if (null == $this->strongLink) {
				$this->strongLink = ResourceLabelGroupQuery::create('rlag')
					->join('rlag.ResourceLinkGroup rlg')
					->where('rlg.ResourceId = ?', $this->getId())
					->where('rlag.GroupId IN ?', BNSAccess::getContainer()->get('bns.right_manager')->getGroupIdsWherePermission('RESOURCE_ACCESS'))
				->findOne();
			}
		}

		return $this->strongLink;
	}

	/**
	 * @param int $currentGroupId Used to retreive all group label for current user
	 *
	 * @return array|boolean
	 */
	public function getStrongLabelPath($currentGroupId)
	{
		$labels = $this->getAllLabels(true);
		$strongLabel = null;

		foreach ($labels as $label) {
			foreach ($label->getResourceLinks() as $link) {
				if ($link->isStrongLink()) {
					$strongLabel = $label;
					break 2;
				}
			}
		}

		if (null != $strongLabel) {
			if ($strongLabel->isRoot()) {
				return array($strongLabel);
			}

			return $this->getLabelPathFromLabel($strongLabel, $currentGroupId);
		}

		return false;
	}

	/**
	 * @param ResourceLabel $targetLabel
	 * @param int			$currentGroupId Used to retreive all group label for current user
	 *
	 * @return array<ResourceLabel>
	 */
	public function getLabelPathFromLabel($targetLabel, $currentGroupId)
	{
		$count = 0;
		$path[] = $targetLabel;

		if ($targetLabel instanceof ResourceLabelUser) {
			$labels = $this->getUserLabels();
		}
		else {
			$labels = $this->getGroupLabels($currentGroupId);
		}

		while (count($path) != $count) {
			$count = count($path);
			$child = $path[$count - 1];

			foreach ($labels as $label) {
				if ($child->getLeftValue() > $label->getLeftValue() &&
					$child->getRightValue() < $label->getRightValue() &&
					$child->getLevel() - 1 == $label->getLevel())
				{
					$path[] = $label;
					break;
				}
			}
		}

		return array_reverse($path);
	}

	/**
	 * Simple shortcute
	 *
	 * @return boolean
	 */
	public function isPrivate()
	{
		return $this->getIsPrivate();
	}

	/*
	 * Si stockage local : Date (jour) + user Id + object Id
	 */

	public function getFilePathPattern()
	{
		return $this->getCreatedAt('Y_m_d') . '/' . $this->getUserId() . '/' . $this->getId() . '/';
	}

	public function getFilePath($size = null)
	{
		$pattern = $this->getFilePathPattern();
		if($size == null || $size == "original")
			return $pattern . $this->getFilename();
		if($this->isImage() || $this->isEmbeddedVideo() || $this->isLink() || $this->isProviderResource())
			return $pattern . $size . '/' . $this->getFilename();
        return $pattern . $this->getFilename();
	}

	public function getEncodedContentPath($size)
	{
		return str_replace($this->getFileName(),'base_64_' . $this->getFilename(),$this->getFilePath($size));
	}

	public function isValueable(){
		return in_array($this->getTypeUniqueName(),array('LINK'));
	}

    /**
     * @return string
     *
     * @throws \RuntimeException
     */
	public function printType()
    {
		switch ($this->getTypeUniqueName()) {
            case "EMBEDDED_VIDEO":
			case "VIDEO": return 'Vidéo';
                
			case "IMAGE": return 'Image';
			case "FILE": return 'Fichier';
			case "LINK": return 'Lien';
			case "AUDIO": return 'Son';
            case 'PROVIDER_RESOURCE': return 'Ressource pédagogique';
            case 'DOCUMENT': return 'Document';
            case 'ATELIER_DOCUMENT': return "Document de l'atelier";

        }

        throw new \RuntimeException('Unknown resource type for : ' . $this->getTypeUniqueName());
	}


	public function getGender(){
		$gender = "m";
		switch($this->getTypeUniqueName()){
			case "IMAGE":
				$gender = "f";
			break;
			case "EMBEDDED_VIDEO":
				$gender = "f";
			break;
			case "VIDEO":
				$gender = "f";
			break;
		}
		return $gender;
	}

	public function getEmbeddedVideoCode()
	{
		if ($this->getTypeUniqueName() == "EMBEDDED_VIDEO") {
			$value = unserialize($this->getValue());
			$type = $value['type'];
			$id = $value['value'];

			switch ($type) {
				case "youtube":
					return '<iframe width="560" height="315" src="https://www.youtube.com/embed/'. $id .'" frameborder="0" webkitAllowFullScreen mozallowfullscreen allowFullScreen></iframe>';
				break;
				case "dailymotion":
					return '<iframe width="560" height="315" src="https://www.dailymotion.com/embed/video/'. $id .'" frameborder="0" webkitAllowFullScreen mozallowfullscreen allowFullScreen></iframe>';
				break;
				case "vimeo":
					return '<iframe width="560" height="315" src="https://player.vimeo.com/video/' . $id . '" frameborder="0" webkitAllowFullScreen mozallowfullscreen allowFullScreen></iframe>';
				break;
			}
		}
		else {
			throw new \Exception("This is not an Embedded video");
		}
	}

    /**
     * Retourne la valeur stockée, déserialisée si nécessaire
     *
     * @return mixed|string
     */
    public function getValueForApi()
    {
        $value = @unserialize($this->getValue());
        if (false === $value) {
            $value = $this->getValue();
        }

        return $value;
    }

	/**
	 * @return
	 */
	public function getAllLabels($isJoined = false)
	{
		if ($isJoined) {
			$users_links = $this->getResourceLinkUsers();
			$groups_links = $this->getResourceLinkGroups();
		}
		else {
			$users_links = $this->getResourceLinkUsersJoinResourceLabelUser();
			$groups_links = $this->getResourceLinkGroupsJoinResourceLabelGroup();
		}

		$labels = array();
		foreach ($users_links as $link) {
			$labels[] = $link->getResourceLabelUser();
		}

		foreach ($groups_links as $link) {
			$labels[] = $link->getResourceLabelGroup();
		}

		return $labels;
	}

	/**
	 * @return array
	 */
	public function getAllLinks($canManage, $isEnabled = true)
	{
		$users_links = ResourceLinkUserQuery::create('rlu')
			->joinWith('rlu.ResourceLabelUser rlau')
			->where('rlau.UserId = ?', $this->getUserId())
			->where('rlu.ResourceId = ?', $this->getId())
			->where('rlu.Status = ?', $isEnabled)
		->find();

		if (!$canManage) {
			return $users_links;
		}

		$groups_links = ResourceLinkGroupQuery::create('rlg')
			->joinWith('rlg.ResourceLabelGroup rlag')
			->where('rlg.ResourceId = ?', $this->getId())
			->where('rlg.Status = ?', $isEnabled)
		->find();

		$links = array();
		foreach ($users_links as $link) {
			$links[] = $link;
		}

		foreach ($groups_links as $link) {
			$links[] = $link;
		}

		return $links;
	}



	/*
	 * Alterne favori / pas favori pour l'utilisateur
	 * Si forced value => on impose la nouvelle valeur
	 */
	public function toggleFavorite($user_id,$forced_value = null)
	{
		$exists = ResourceFavoritesQuery::create()->filterByUserId($user_id)->filterByResourceId($this->getId())->findOne();

		if(($exists == true && $forced_value != true) || $forced_value === false){
			if($exists)
				$exists->delete();
			return false;
		}else{
			$favorite = new ResourceFavorites();
			$favorite->setUserId($user_id);
			$favorite->setResourceId($this->getId());
			$favorite->save();
			return true;
		}
	}

	/**
	 * @param int $user_id
	 *
	 * @return boolean
	 */
	public function isFavorite($user_id)
	{
		if (ResourceFavoritesQuery::create()->filterByUserId($user_id)->filterByResourceId($this->getId())->findOne()) {
			return true;
		}

		return false;
	}

	/**
	 * @return boolean
	 */
	public function isActive()
	{
		return $this->getStatusDeletion() == BNSResourceManager::STATUS_ACTIVE;
	}

	/**
	 * @return boolean
	 */
	public function isGarbaged()
	{
		return $this->getStatusDeletion() == BNSResourceManager::STATUS_GARBAGED;
	}

	/**
	 * @return boolean
	 */
	public function isDeletedForever()
	{
		return $this->getStatusDeletion() == BNSResourceManager::STATUS_DELETED;
	}

	/**
	 * Copie une resource dans un label
	 *
	 * @param ResourceLabel $to
	 */
	public function copyTo($to, $isStrongLink = false)
	{
		// Création du to
		$toLink = null;
		if ($to->getType() == 'user') {
			$toLink = ResourceLinkUserQuery::create('rlu')
				->where('rlu.ResourceLabelUserId = ?', $to->getId())
				->where('rlu.ResourceId = ?', $this->getId())
			->findOne();

			if (null == $toLink) {
				$toLink = new ResourceLinkUser();
				$toLink->setResourceLabelUserId($to->getId());
			}
		}
		elseif ($to->getType() == 'group') {
			$toLink = ResourceLinkGroupQuery::create('rlu')
				->where('rlu.ResourceLabelGroupId = ?', $to->getId())
				->where('rlu.ResourceId = ?', $this->getId())
			->findOne();

			if (null == $toLink) {
				$toLink = new ResourceLinkGroup();
				$toLink->setResourceLabelGroupId($to->getId());
			}
		}

		$toLink->setResourceId($this->getId());
		if ($toLink->isNew() || $isStrongLink && !$toLink->getIsStrongLink()) {
			$toLink->setIsStrongLink($isStrongLink);
		}

		$toLink->setStatus(1);
		$toLink->save();

		return $toLink;
	}

	/**
	 * Déplace la resource vers un autre label
	 *
	 * @param ResourceLabel $from
	 * @param ResourceLabel $to
	 */
	public function move($from, $to)
	{
		// Recupération et destruction du from
		$fromLink = $this->getLinkFromLabel($from);

		// On copie
		$this->copyTo($to, $fromLink->getIsStrongLink());

		// Puis on supprime l'ancienne occurence
		$fromLink->delete();
	}

	/**
	 * @param ResourceLabel $label
	 *
	 * @return ResourceLink
	 */
	public function getLinkFromLabel($label)
	{
		if ($label->getType() == 'user') {
			$query = ResourceLinkUserQuery::create();
			$query->filterByResourceLabelUserId($label->getId());
		}
		elseif ($label->getType() == 'group') {
			$query = ResourceLinkGroupQuery::create();
			$query->filterByResourceLabelGroupId($label->getId());
		}

		$query->filterByResourceId($this->getId());
		$return = $query->findOne();

		return $return;
	}

	/**
	 * @return array<ResourceGroupLink>
	 */
	public function getActiveResourceLinkGroups()
	{
		return $this->getActiveResourceLinks($this->getResourceLinkGroups());
	}

	/**
	 * @return array<ResourceUserLink>
	 */
	public function getActiveResourceLinkUsers()
	{
		return $this->getActiveResourceLinks($this->getResourceLinkUsers());
	}

	/**
	 *
	 * @param array<ResourceLink> $links
	 *
	 * @return array<ResourceLink>
	 */
	private function getActiveResourceLinks($links)
	{
		$activeLinks = array();

		foreach ($links as $link) {
			if ($link->isActive()) {
				$activeLinks[] = $link;
			}
		}

		return $activeLinks;
	}

	/**
	 * @return array<ResourceUserLabel>
	 */
	public function getUserLabels()
	{
		if (!isset($this->userLabels)) {
			$this->userLabels = ResourceLabelUserQuery::create('rlau')
				->join('rlau.ResourceLinkUser rlu')
				->join('rlu.Resource r')
				->where('r.UserId = ?', $this->getUserId())
			->find();
		}

		return $this->userLabels;
	}

	/**
	 * @param int $currentGroupId
	 *
	 * @return array<ResourceGroupLabel>
	 */
	public function getGroupLabels($currentGroupId)
	{
		if (!isset($this->groupLabels)) {
			$this->groupLabels = ResourceLabelGroupQuery::create('rlag')
				->where('rlag.GroupId = ?', $currentGroupId)
			->find();
		}

		return $this->groupLabels;
	}

	/**
	 * Add one to the download count
	 */
	public function addDownloadCount()
	{
		$this->setDownloadCount($this->getDownloadCount() + 1);
	}

	public function getSize($isFormatted = true)
	{
		if (!$isFormatted) {
			return parent::getSize();
		}

		$size = parent::getSize();
		if ($size > 1048576) {
			return number_format($size / 1048576, 2) . ' Mo';
		}

		return number_format($size / 1024, 2) . ' Ko';
	}

	/**
     * Cleanup a string to make a slug of it
     * Removes special characters, replaces blanks with a separator, and trim it
     *
     * @param     string $slug        the text to slugify
     * @param     string $replacement the separator used by slug
     * @return    string               the slugified text
     */
    protected static function cleanupSlugPart($slug, $replacement = '-')
    {
        // transliterate
        if (function_exists('iconv')) {
            $slug = iconv('utf-8', 'us-ascii//TRANSLIT//IGNORE', $slug);
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
    
    /**
	 * @param array<WorkshopDocument> $documents
	 */
	public function replaceWorkshopDocuments($documents)
	{
		$this->collWorkshopDocuments = $documents;
	}

    public function getOvhPcsTempUrl($size)
    {
        $url  = 'https://lb1047.pcs.ovh.net/v1//beneylu-ent-test/' . urlencode($this->getFilePath($size));
        $path = urldecode(parse_url($url, PHP_URL_PATH));

        $exp = time() + 60;
        $hmac_body = "GET\n$exp\n$path";
        $hash = hash_hmac('sha1', $hmac_body, 'coucou');


        $temp_url = sprintf('%s?temp_url_sig=%s&temp_url_expires=%d', $url, $hash, $exp);

        return $temp_url;
    }
}
