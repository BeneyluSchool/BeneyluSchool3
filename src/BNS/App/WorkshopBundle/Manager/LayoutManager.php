<?php

namespace BNS\App\WorkshopBundle\Manager;

/**
 * Class LayoutManager
 *
 * @package BNS\App\WorkshopBundle\Manager
 */
class LayoutManager extends ConfigurationManager
{

    /**
     * Cached list of layouts
     *
     * @var array
     */
    private $list = null;

    /**
     * Cache for valid layout codes
     *
     * @var array
     */
    private $validLayoutCodes;

    /**
     * Gets the list of layout types, embedding their layouts
     *
     * @return array
     */
    public function getListByType()
    {
        if (null === $this->data) {
            $this->load();
        }

        // layouts are already grouped by type in the source file, so simply
        // return the parsed data
        return $this->data['types'];
    }

    /**
     * Gets the list of layouts
     *
     * @return array
     */
    public function getList()
    {
        if (null === $this->data) {
            $this->load();
        }

        if (null === $this->list) {
            $this->list = array();
            // layouts are grouped by type
            foreach ($this->data['types'] as $typeCode => $type) {
                foreach ($type['layouts'] as $layout) {
                    $layout['type'] = array(
                        'code' => $typeCode,
                        'label' => $type['label'],
                    );
                    $this->list[] = $layout;
                }
            }
        }

        return $this->list;
    }

    /**
     * Tries to get the layout of the given object
     *
     * @param $object
     * @return array|null
     */
    public function getForObject($object)
    {
        // maybe the object has a layout code
        if (method_exists($object, 'getLayoutCode')) {
            $code = $object->getLayoutCode();

            return $this->findOneBy('code', $code);
        }

        return null;
    }

    /**
     * Gets the list of valid layout codes
     *
     * @return array
     */
    public function getValidLayoutCodes()
    {
        if (null === $this->validLayoutCodes) {
            $this->validLayoutCodes = array();

            // consider that all the defined codes are valid
            foreach ($this->getList() as $layout) {
                $this->validLayoutCodes[] = $layout['code'];
            }
        }

        return $this->validLayoutCodes;
    }

}
