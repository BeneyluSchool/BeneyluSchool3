<?php

namespace BNS\App\RegistrationBundle\Controller;

use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\InfoBundle\Model\SponsorshipPeer;
use BNS\App\InfoBundle\Model\SponsorshipQuery;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\Form\FormError;

use BNS\App\CoreBundle\Annotation\Anon;
use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Model\GroupTypeDataChoiceQuery;
use BNS\App\RegistrationBundle\Form\Type\UserRegistrationType;
use BNS\App\RegistrationBundle\Form\Type\ClassRoomRegistrationType;
use BNS\App\RegistrationBundle\Model\SchoolInformationQuery;
use BNS\App\RegistrationBundle\Form\Type\SchoolCreationType;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class FrontFreeController extends Controller
{
    /**
     * @Route("/", name="registration_free")
     * @Anon
     */
    public function indexAction()
    {
        /**
         * Deprecated
         */
        return $this->redirect($this->generateUrl('home'));
    }

    /**
     * @Route("/bienvenue", name="registration_free_registration_process")
     * @Anon
     */
    public function agreeCguAction()
    {
        /**
         * Deprecated
         */
        return $this->redirect($this->generateUrl('home'));
    }

    /**
     * @Route("/creation-ecole", name="registration_free_create_school")
     * @Anon
     */
    public function createSchoolAction()
    {
        /**
         * Deprecated
         */
        return $this->redirect($this->generateUrl('home'));
    }

    /**
     * @Route("/creation-ecole/validation", name="registration_free_create_school_validation")
     * @Anon
     */
    public function createSchoolConfirmationAction()
    {
        /**
         * Deprecated
         */
        return $this->redirect($this->generateUrl('home'));
    }
}
