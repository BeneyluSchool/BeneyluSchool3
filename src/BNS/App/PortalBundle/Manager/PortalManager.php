<?php

namespace BNS\App\PortalBundle\Manager;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\PortalBundle\Model\Portal;

/**
 * Class PortalManager
 *
 * @package BNS\App\PortalBundle\Manager
 */
class PortalManager
{
    protected $translator;

    public static $fonts = array(
        'rambla' => "Rambla",
        'bree' => "Bree",
        'ubuntu' => "Ubuntu",
        'chivo' => "Chivo",
        'josefin' => "Josefin",
    );

    public static $colors = array(
        'blue' => 'BLUE',
        'green' => 'GREEN',
        'red' => 'RED',
        'purple' => 'PURPLE'
    );

    public function __construct($translator)
    {
        $this->translator = $translator;
    }

    /**
     * Création du portail à partir du groupe lié : lazyCréation, pas d'initialisation brute
     * @param Group $group
     * @return Portal
     * @throws \Exception
     * @throws \PropelException
     */
    public function create(Group $group)
    {
        $portal = new Portal();
        $portal->setGroup($group);
        $portal->setLabel($this->translator->trans('LABEL_PORTAL_OF', array(), 'PORTAL') . ' ' . $group->getLabel());
        $portal->setSlug($group->getSlug());
        $portal->save();
        return $portal;
    }

}
