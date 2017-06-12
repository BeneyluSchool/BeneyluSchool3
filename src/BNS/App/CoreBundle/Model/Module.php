<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseModule;
use BNS\App\CoreBundle\Translation\TranslatorTrait;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Router;
use JMS\TranslationBundle\Annotation\Ignore;


/**
 * @package    propel.generator.Work/Beneyluschool3/src/BNS/App/CoreBundle/Model
 */
class Module extends BaseModule
{
    use TranslatorTrait;

    /**
     * @var string slug
     */
    private $slug;

    /** @var  string */
    protected $customLabel;

    protected $routeFront;

    // Needed for manage classroom module statement
    private $isActivatedForParent = false;
    private $isActivatedForPupil = false;

    // Needed for manage team module statement
    private $isActivatedForMember = false;
    private $isActivatedForTeacher = false;
    private $isActivatedForOther = false;


    // Inject values based on current user before render by the serialiser
    public $canOpen = false;
    public $isOpen = false;
    public $isPrivate = false;
    // old behavior = halfOpen
    public $isPartiallyOpen = false;
    public $isUninstallable = false;
    public $hasAccessFront = false;
    public $hasAccessBack = false;

    public $isFavorite = false;
    public $rank = null;

    public $counter = null;

    public $groupType = null;
    public $metaTitle = null;

    // Add module that support auto open
    protected $autoOpenList = [
        'SPACE_OPS',
        'TOUR'
    ];

    /**
     *
     * @var boolean Used for  <> Module reference
     * True if the module is activated (Module reference exist in the database for this module) for a  object, false otherwise
     */
    private $isActivated = false;

    public function __toString()
    {
        return $this->getUniqueName();
    }

//	/**
//	 * Permet de récupérer le slug du module
//	 *
//	 * @return string Renvoi une chaîne de caractère qui correspond au slug du module
//	 */
//	public function getSlug()
//	{
//		if (!isset($this->slug))
//			$this->slug = $this->getCurrentTranslation()->getSlug();
//
//		return $this->slug;
//	}

    public function activate()
    {
        $this->isActivated = true;
    }

    /**
     * @return boolean module's state
     */
    public function isActivated()
    {
        return $this->isActivated;
    }

    public function setRouteFront($route)
    {
        $this->routeFront = $route;

        return $this;
    }

    public function getRouteFront()
    {
        if ($this->routeFront) {
            return $this->routeFront;
        }

        if ('APP' !== $this->getType()) {
            return strtolower($this->getUniqueName()) . '_front';
        }

        return $this->getBundleName() . '_front';
    }

    public function getRouteBack()
    {
        if ('APP' !== $this->getType()) {
            return strtolower($this->getUniqueName()) . '_back';
        }

        return $this->getBundleName() . '_back';
    }

    public function hasRouteFront(Router $router)
    {
        try {
            // try to generate the url, this prevent a call to getRouteCollection
            $router->generate($this->getRouteFront());
        } catch (RouteNotFoundException $e) {
            return false;
        }

        return true;
    }

    public function hasRouteBack(Router $router)
    {
        try {
            // try to generate the url, this prevent a call to getRouteCollection
            $router->generate($this->getRouteBack());
        } catch (RouteNotFoundException $e) {
            return false;
        }

        return true;
    }

    public function activateForParent()
    {
        $this->isActivatedForParent = true;
    }

    public function isActivatedForParent()
    {
        return $this->isActivatedForParent;
    }

    public function activateForPupil()
    {
        $this->isActivatedForPupil = true;
    }

    public function isActivatedForPupil()
    {
        return $this->isActivatedForPupil;
    }

    public function activateForTeacher()
    {
        $this->isActivatedForTeacher = true;
    }

    public function isActivatedForTeacher()
    {
        return $this->isActivatedForTeacher;
    }

    public function activateForMember()
    {
        $this->isActivatedForMember = true;
    }

    public function isActivatedForMember()
    {
        return $this->isActivatedForMember;
    }

    public function activateForOther()
    {
        $this->isActivatedForOther = true;
    }

    public function isActivatedForOther()
    {
        return $this->isActivatedForOther;
    }

    /**
     * Simple shortcut
     *
     * @return boolean
     */
    public function isContextable()
    {
        return $this->getIsContextable();
    }

    /**
     * Simple shortcut
     *
     * @return boolean
     */
    public function isEnabled()
    {
        return $this->getIsEnabled();
    }

    /**
     * @deprecated
     * @return string Renvoie le libellé pour la dockbar, et par défaut le nom du module
     */
    public function getLabelForDockBar()
    {
        switch ($this->getUniqueName()) {
            case "MESSAGING":
                return "Ma messagerie";
                break;
            case "FORUM":
                return "Mon forum";
                break;
            case "PROFILE":
                return "Mon profil";
                break;
            case "HOMEWORK":
                return "Mon cahier de textes";
                break;
            case "RESOURCE":
                return "Ma médiathèque";
                break;
            case "CALENDAR":
                return "Mon calendrier";
                break;
            case "DIRECTORY":
                return "Mon annuaire";
                break;
            case "WORKSHOP":
                return "Mon atelier";
                break;
            default:
                return $this->getLabel();
        }
    }

    /**
     * Checks whether this module should be considered an angular modal: changes its behavior in the dockbar.
     *
     * @return bool
     */
    public function isAngularModal()
    {
        return in_array(
            $this->getUniqueName(),
            array(
                'USER_DIRECTORY',
            )
        );
    }

    public function getDescription()
    {
        $translator = $this->getTranslator();
        if (!$translator) {
            return $this->getDescriptionToken();
        }

        /** @Ignore */
        return $translator->trans($this->getDescriptionToken(), array(), 'MODULE');
    }

    public function getDescriptionToken()
    {
        return 'DESCRIPTION_' . $this->getUniqueName();
    }

    public function getLabel()
    {
        $translator = $this->getTranslator();
        if (!$translator) {
            return $this->getUniqueName();
        }

        /** @Ignore */
        return $translator->trans($this->getUniqueName(), array(), 'MODULE');
    }

    public function getCustomLabel()
    {
        return $this->customLabel ;
    }

    public function setCustomLabel($label)
    {
        $this->customLabel = $label;
    }

    /**
     * @deprecated do not use this
     * @param $v
     * @return $this
     */
    public function setLabel($v)
    {
        return $this;
    }

    /**
     * @deprecated do not use this
     * @param $v
     * @return $this
     */
    public function setDescription($v)
    {
        return $this;
    }

    public function isAutoOpen()
    {
        return in_array($this->getUniqueName(), $this->autoOpenList);
    }
}
