<?php

namespace BNS\App\ResourceBundle\ProviderResource;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class ProviderResource
{
    /**
     * @var string 
     */
    private $uai;

    /**
     * @var int 
     */
    private $providerId;

    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $url;

    /**
     * @var string
     */
    private $label;

    /**
     * @var string
     */
    private $description;

    /**
     * @var string
     */
    private $imageUrl;

    /**
     * @var string
     */
    private $type;


    /**
     * @param int   $providerId
     * @param array $resource
     */
    public function __construct($uai, array $resource)
    {
        $this->uai         = $uai;
        $this->providerId  = $resource['provider_id'];
        $this->id          = $resource['id'];
        $this->url         = $resource['resource_url'];
        $this->label       = $resource['title'];
        $this->description = $resource['description'];
        $this->imageUrl    = $resource['image_url'];
        $this->type        = $resource['type'];
    }

    /**
     * @return string
     */
    public function getUai()
    {
        return $this->uai;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @return string
     */
    public function getImageUrl()
    {
        return $this->imageUrl;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @return int
     */
    public function getProviderId()
    {
        return $this->providerId;
    }
}