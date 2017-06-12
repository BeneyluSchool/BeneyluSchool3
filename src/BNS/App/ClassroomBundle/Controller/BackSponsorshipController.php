<?php

namespace BNS\App\ClassroomBundle\Controller;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\RegistrationBundle\Model\SchoolInformation;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use BNS\App\ClassroomBundle\Form\Type\PartnershipType;
use BNS\App\ClassroomBundle\Form\Model\PartnershipFormModel;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use BNS\App\InfoBundle\Form\Type\SponsorshipType;
use BNS\App\InfoBundle\Model\AnnouncementQuery;
use BNS\App\InfoBundle\Form\Type\ContactType;
use BNS\App\InfoBundle\Model\Contact;
use BNS\App\InfoBundle\Model\Sponsorship;
use BNS\App\InfoBundle\Model\SponsorshipPeer;
use BNS\App\InfoBundle\Model\SponsorshipQuery;

class BackSponsorshipController extends Controller
{
    /**
     * @Route("/", name="BNSAppClassroomBundle_back_sponsorship")
     * @Template()
     * @Rights("CLASSROOM_ACCESS_BACK")
     */
    public function indexAction()
    {

        $onPublicVersion = $this->get('bns.right_manager')->getCurrentGroupManager()->isOnPublicVersion();
        if(!$onPublicVersion)
        {
            return $this->redirect($this->generateUrl('BNSAppClassroomBundle_back'));
        }
        $currentGroup = $this->get('bns.right_manager')->getCurrentGroup();
        $hasGroupBoard = $this->get('bns.group_manager')->setGroup($currentGroup)->getProjectInfo('has_group_blackboard');

        $sponsorship = new Sponsorship();
        $userId = $this->get('bns.right_manager')->getModelUser()->getId();
        $schools = $this->get('bns.right_manager')->getUserManager()->getSimpleGroupsAndRolesUserBelongs(true,3);
        $form = $this->createForm(new SponsorshipType(),$sponsorship);
        if ($this->getRequest()->getMethod() == 'POST'){
            $form->handleRequest($this->getRequest());
            if ($form->isValid()){
                if ($form->get('email')->getData() == $this->get('bns.user_manager')->getUser()->getEmail()) {
                    $this->get('session')->getFlashBag()->add('error', 'Vous ne pouvez pas entrer votre propre e-mail.');
                    return $this->redirect($this->generateUrl("BNSAppClassroomBundle_back_sponsorship"));
                }
                if (0 == $schools->count()) {
                    $group = $this->get('bns.right_manager')->getCurrentGroup();
                    $school = $this->get('bns.group_manager')->setGroup($group)->createSchool($group);
                } else {
                    $school = $schools->getFirst();
                }
                $sponsorship->setFromUserId($userId);
                $available = $sponsorship->isAvailable();
                if($available === true)
                {
                    $sponsorship->setStatus('PENDING');
                    $sponsorship->setSchoolId($school->getId());
                    $sponsorship->save();
                    $this->get('bns.mailer')->send('SPONSORSHIP_NEW',array(
                        'email' => $form->get('email')->getData(),
                        'sponsor_full_name' => $sponsorship->getUserRelatedByFromUserId()->getFullName(),
                        'link' => $this->getParameter('application_base_url')
                    ),$form->get('email')->getData());
                    $this->get('session')->getFlashBag()->add('success',$this->get('translator')->trans("FLASH_REFERAL_SUCCESS", array(), 'INFO'));
                }else{
                    switch($available)
                    {
                        /*case 'EXISTS':
                            $this->get('session')->getFlashBag()->add('notice',"Cet email est déjà associé à un utilisateur de Beneylu School.");
                            break;*/
                        case 'SPONSORSHIP_EXISTS':
                            $this->get('session')->getFlashBag()->add('notice',$this->get('translator')->trans("FLASH_EMAIL_ALREADY_HAVE_REFERAL", array(), 'INFO'));
                            break;
                    }
                }
                return $this->redirect($this->generateUrl("BNSAppClassroomBundle_back_sponsorship"));
            }
        }
        return array(
            'section'  => 'sponsorship',
            'onPublicVersion' => $onPublicVersion,
            'form' => $form->createView(),
            'sponsorships' => SponsorshipQuery::create()->joinUserRelatedByFromUserId()->findByFromUserId($userId),
            'hasGroupBoard' => $hasGroupBoard
        );
    }

}
