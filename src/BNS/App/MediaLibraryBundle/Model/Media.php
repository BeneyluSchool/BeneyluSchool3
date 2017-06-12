<?php

namespace BNS\App\MediaLibraryBundle\Model;

use BNS\App\MediaLibraryBundle\Manager\MediaManager;
use BNS\App\MediaLibraryBundle\Model\om\BaseMedia;
use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\WorkshopBundle\Model\WorkshopContent;
use BNS\App\WorkshopBundle\Model\WorkshopDocumentContribution;
use BNS\App\WorkshopBundle\Model\WorkshopDocumentContributionQuery;
use Doctrine\Common\Inflector\Inflector;

class Media extends BaseMedia
{



    public $imageThumbnailUrl;
    public $imageMediumUrl;
    public $provider;
    public $paasId;

    public $searchStatus;

    protected $original;

    public function getType()
    {
        return 'MEDIA';
    }

    public function isImage()
    {
        return $this->getTypeUniqueName() == "IMAGE";
    }

    public function isDocument()
    {
        return $this->getTypeUniqueName() == "DOCUMENT";
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

    public function isWorkshopDocument()
    {
        return $this->getTypeUniqueName() == 'ATELIER_DOCUMENT';
    }

    public function isWorkshopAudio()
    {
        return $this->getTypeUniqueName() == 'ATELIER_AUDIO';
    }

    public function isFromWorkshop()
    {
        return $this->isWorkshopDocument() || $this->isWorkshopAudio();
    }

    public function getMediaFolder()
    {
        switch($this->getMediaFolderType())
        {
            case 'GROUP':
                $query = MediaFolderGroupQuery::create();
                break;
            case 'USER':
                $query = MediaFolderUserQuery::create();
                break;
            default:
                return null;
        }
        return $query->findOneById($this->getMediaFolderId());
    }

    /**
     * @inheritDoc
     * @deprecated
     * @see isFromPaas
     */
    public function getFromPaas()
    {
        return $this->isFromPaas();
    }

    /**
     * @inheritDoc
     * @deprecated
     * @see setExternalSource
     */
    public function setFromPaas($v)
    {
        if ($v) {
            $this->setExternalSource(MediaPeer::EXTERNAL_SOURCE_PAAS);
        } else {
            $this->setExternalSource(null);
        }
    }

    /**
     * Checks if media is from PAAS
     *
     * @return bool
     */
    public function isFromPaas()
    {
        return MediaPeer::EXTERNAL_SOURCE_PAAS === $this->getExternalSource();
    }

    /**
     * @inheritDoc
     */
    public function setExternalData($v)
    {
        return parent::setExternalData(json_encode($v));
    }

    /**
     * @inheritDoc
     */
    public function getExternalData()
    {
        return json_decode(parent::getExternalData(), true);
    }

    public function getProvider()
    {
        return $this->doGetExternalData('provider');
    }

    public function setProvider($value)
    {
        return $this->doSetExternalData('provider', $value);
    }

    public function setDownloadUrl($value)
    {
        return $this->doSetExternalData('download_url', $value);
    }

    public function setHtmlBase($value)
    {
        return $this->doSetExternalData('html_base', $value);
    }

    public function setImageThumbnailUrl($value)
    {
        return $this->doSetExternalData('image_thumbnail_url', $value);
    }

    public function getImageThumbnailUrl()
    {
        return $this->doGetExternalData('image_thumbnail_url');
    }

    public function setImageMediumUrl($value)
    {
        return $this->doSetExternalData('image_medium_url', $value);
    }

    public function getImageMediumUrl()
    {
        return $this->doGetExternalData('image_medium_url');
    }

    public function getIsDownloadable()
    {
        return false !== $this->doGetExternalData('downloadable');
    }

    public function setIsDownloadable($value)
    {
        return $this->doSetExternalData('downloadable', (boolean) $value);
    }

    /**
     * Checks whether this media has expired.
     *
     * @return bool
     */
    public function hasExpired()
    {
        if (!$this->getExpiresAt()) {
            return false;
        }

        return $this->getExpiresAt() < new \DateTime();
    }

    /**
     * Gets the original media that this one is a copy of.
     *
     * @return Media
     */
    public function getOriginal()
    {
        if (!$this->getCopyFromId()) {
            return null;
        }

        if (!isset($this->original)) {
            $this->original = MediaQuery::create()->findPk($this->getCopyFromId());
        }

        return $this->original;
    }

    /**
     * Gets all copies of this media.
     *
     * @return array|\PropelObjectCollection|Media[]
     */
    public function getCopies()
    {
        return MediaQuery::create()
            ->filterByCopyFromId($this->getId())
            ->find()
        ;
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

    /**
     * @return boolean
     */
    public function isActive()
    {
        return $this->getStatusDeletion() == MediaManager::STATUS_ACTIVE;
    }

    /**
     * @return boolean
     */
    public function isGarbaged()
    {
        return $this->getStatusDeletion() == MediaManager::STATUS_GARBAGED || $this->getStatusDeletion() == MediaManager::STATUS_GARBAGED_PARENT;
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
        {
            return $pattern . $this->getFilename();
        }
        return $pattern . $size . '/' . $this->getFilename();
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

    public function getHtml5AudioMimeType()
    {
        $mimeType = $this->getFileMimeType();
        switch ($mimeType) {
            case 'application/ogg': return 'audio/ogg';
            case 'audio/x-wav': return 'audio/wav';
        }

        return 'audio/mp3';
    }

    public function getHtml5VideoMimeType()
    {
        $mimeType = $this->getFileMimeType();
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

    public function getEmbeddedVideoCode($width = null)
    {
        if ($this->getTypeUniqueName() == "EMBEDDED_VIDEO") {
            $value = unserialize($this->getValue());
            $type = $value['type'];
            $id = $value['value'];

            $width = $width != null ? $width : '560';

            switch ($type) {
                case "youtube":
                    return '<iframe width="'.$width.'" height="315" src="https://www.youtube.com/embed/'. $id .'?rel=0&amp;showinfo=0" frameborder="0" webkitAllowFullScreen mozallowfullscreen allowFullScreen></iframe>';
                    break;
                case "dailymotion":
                    return '<iframe width="'.$width.'" height="315" src="https://www.dailymotion.com/embed/video/'. $id .'" frameborder="0" webkitAllowFullScreen mozallowfullscreen allowFullScreen></iframe>';
                    break;
                case "vimeo":
                    return '<iframe width="'.$width.'" height="315" src="https://player.vimeo.com/video/' . $id . '" frameborder="0" webkitAllowFullScreen mozallowfullscreen allowFullScreen></iframe>';
                    break;
            }
        }
        else {
            throw new \Exception("This is not an Embedded video");
        }
    }

    public function getValueFromVideoId($type,$id)
    {
        switch($type)
        {
            case 'vimeo':
                return 'https://vimeo.com/' . $id;
                break;
            case 'youtube':
                return 'https://www.youtube.com/watch?v=' . $id;
            /**
             * TODO : faire autres providers de vidéos
             */
        }
    }

    public function getVideoProvider()
    {
        $value = unserialize($this->getValue());
        return $value['type'];
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

    public function getDownloadUrl($fallback = true)
    {
        $original = $this->getOriginal();
        if ($original && $original->getExternalSource()) {
            return $original->getDownloadUrl();
        }

        if ($this->hasExpired()) {
            return null;
        }

        $data = $this->getExternalData();
        if (isset($data['download_url'])) {
            return $data['download_url'];
        }

        if (!$fallback) {
            return null;
        }

        return BNSAccess::getContainer()->get('bns.media.download_manager')->getDownloadUrl($this);
    }

    public function getImageUrl($size = null)
    {
        $original = $this->getOriginal();
        if ($original && $original->getExternalSource()) {
            return $original->getImageUrl($size);
        }

        return BNSAccess::getContainer()->get('bns.media.download_manager')->getImageDownloadUrl($this, $size);
    }

    public function getMediaValue()
    {
        switch($this->getTypeUniqueName())
        {
            case 'LINK':
                if($this->getFromPaasId() == null)
                {
                return $this->getValue();
                }else{
                    return BNSAccess::getContainer()->get('bns.paas_manager')->getUrlFromPaasId($this->getFromPaasId());
                }
                break;
            case 'EMBEDDED_VIDEO':
                return $this->getEmbeddedVideoCode();
                break;
            case 'HTML':
                $value = unserialize($this->getValue());
                return $value['content'];
                break;
            case 'BASE_HTML':
                $value = unserialize($this->getValue());
                return $value['content'];
                break;
        }
        return false;
    }

    public function getUrlInfos()
    {
        return $this->getFile();
    }

    public function getUniqueKey()
    {
        if(!$this->getFromPaas())
        {
            return 'media' . $this->getId();
        }else{
            return 'paas' . $this->getId();
        }

    }

    public function getMediaLibraryRightManager()
    {
        return BNSAccess::getContainer()->get('bns.media_library_right.manager');
    }

    public function isReadable()
    {
        return $this->getMediaLibraryRightManager()->isReadable($this);
    }

    public function isWritable()
    {
        return $this->getMediaLibraryRightManager()->isWritable($this);
    }

    public function isManageable()
    {
        return $this->getMediaLibraryRightManager()->isManageable($this);
    }

    public function isFavorite($userId = null)
    {
        if($userId == null)
        {
            $userId = BNSAccess::getContainer()->get('bns.right_manager')->getUserSessionId();
        }
        $manager =  BNSAccess::getContainer()->get('bns.media.manager');
        $manager->setMediaObject($this);
        return $manager->isFavorite($userId);
    }

    public function getHtmlBase()
    {
        $data = $this->getExternalData();
        if (isset($data['html_base'])) {
            return $data['html_base'] . '?' . BNSAccess::getContainer()->get('bns.paas_manager')->generateQueryString(array('id' => $this->getExternalId()));
        }
        if($this->getFromPaasId() != null)
        {
            $value = unserialize($this->getValue());
            if (!isset($value['url_pattern'])) {
                return null;
            }
            $urlPattern = $value['url_pattern'];
            return BNSAccess::getContainer()->get('bns.paas_manager')->getHtmlBaseUrlFromPaasId($this->getFromPaasId(), $urlPattern);
        }
        return null;
    }

    public function getIsSystem()
    {
        // return $this->getFromPaas();
        return false;
    }

    /**
     * @inheritDoc
     */
    public function copy($deepCopy = false)
    {
        $copy = parent::copy($deepCopy);
        $copy->setCopyFromId($this->getId());

        return $copy;
    }

    public function createSlug()
    {
        if(!$this->isNew())
        {
            $key = $this->getId();
        }else{
            $key = 'key-' . rand(999999999, min(9999999999, PHP_INT_MAX));
        }
        return 'media-' . $key;
    }

    public function getWorkshopDocumentId()
    {
        if ($this->isWorkshopDocument()) {
            $content = $this->getWorkshopContents()->getFirst();
            if ($content) {
                return $content->getId();
            }
        }

        return null;
    }

    /**
     * @return array|\PropelObjectCollection|WorkshopDocumentContribution[]
     */
    public function getContributions()
    {
        if ($this->isWorkshopDocument()) {
            $content = $this->getWorkshopContent();

            // fail if for some reason the media has no associated content
            if (!$content) {
                BNSAccess::getContainer()->get('logger')->error('Media for WorkshopDocument without WorkshopContent', array(
                    'media_id' => $this->getId(),
                ));

                return array();
            }

            return WorkshopDocumentContributionQuery::create()
                ->filterByWorkshopDocumentId($content->getId())
                ->joinWith('User')
                ->find();
        }

        return array();
    }

    /**
     * @return WorkshopContent
     */
    public function getWorkshopContent()
    {
        if ($this->isFromWorkshop()) {
            return $this->getWorkshopContents()->getFirst();
        }

        return null;
    }

    protected function doSetExternalData($path, $value)
    {
        $data = $this->getExternalData() ?: [];
        $data[$path] = $value;

        return $this->setExternalData($data);
    }

    protected function doGetExternalData($path)
    {
        $data = $this->getExternalData();
        if (isset($data[$path])) {
            return $data[$path];
        }

        // check if data has been set in a dynamic class attr
        $camelPath = Inflector::camelize($path);
        if (isset($this->$camelPath)) {
            return $this->$camelPath;
        }

        return null;
    }

}
