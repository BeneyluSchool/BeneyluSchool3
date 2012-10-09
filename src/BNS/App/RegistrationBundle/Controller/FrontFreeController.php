<?php

namespace BNS\App\RegistrationBundle\Controller;

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
		$classRoomLevels = GroupTypeDataChoiceQuery::create('l')
			->joinWithI18n(BNSAccess::getLocale())
			->where('l.GroupTypeDataTemplateUniqueName = ?', 'LEVEL')
		->find();
		
		if (null == $classRoomLevels) {
			throw new \RuntimeException('There is no group type data template with type "LEVEL" ! Please check the database.');
		}
		
		// Deleting sessions
		$session = $this->getRequest()->getSession();
		$session->remove('user_data');
		$session->remove('classroom_data');
		
		$userForm		= $this->createForm(new UserRegistrationType());
		$classRoomForm	= $this->createForm(new ClassRoomRegistrationType($classRoomLevels));
		
		if ($this->getRequest()->isMethod('POST')) {
			$userForm->bindRequest($this->getRequest());
			$classRoomForm->bindRequest($this->getRequest());
			
			if ($userForm->isValid()) {
				$session->set('user_data', $userForm->getData());
				
				if ($classRoomForm->isValid()) {
					$session->set('classroom_data', $classRoomForm->getData());
					return $this->redirect($this->generateUrl('registration_free_registration_process'));
				}
			}
		}
		
        return $this->render('BNSAppRegistrationBundle:Free:register_classroom.html.twig', array(
			'userForm'			=> $userForm->createView(),
			'classRoomForm'		=> $classRoomForm->createView(),
			'level_separators'	=> array(
				'PS'			=> 'Maternelle',
				'CP'			=> 'Élémentaire',
				'6ème'			=> 'Collège',
				'Seconde'		=> 'Lycée',
				'Elémentaire'	=> 'Clis'
			)
		));
    }
	
	/**
	 * @Route("/bienvenue", name="registration_free_registration_process")
	 * @Anon
	 */
	public function agreeCguAction()
	{
		$session = $this->getRequest()->getSession();
		if (!$session->has('user_data') || !$session->has('classroom_data')) {
			return $this->redirect($this->generateUrl('registration_free'));
		}
		
		$userForm		= $this->createForm(new UserRegistrationType(), $session->get('user_data'));
		$classRoomForm	= $this->createForm(new ClassRoomRegistrationType(), $session->get('classroom_data'));
		
		// Deleting sessions
		$session->remove('user_data');
		$session->remove('classroom_data');
		
		// Creating teacher
		$teacher = $userForm->getData()->save($this->get('bns.user_manager'));
		
		// School is exist ?
		$schoolInfo = SchoolInformationQuery::create('si')
			->joinWith('Group', \Criteria::LEFT_JOIN)
			->where('si.Id = ?', $classRoomForm->getData()->school_information_id)
		->findOne();
		
		// Creating school if not exists
		if (null == $schoolInfo->getGroupId()) {
			$this->get('bns.classroom_manager')->createSchoolFromInformation($schoolInfo);
		}
		
		// Creating classroom
		$classRoom = $classRoomForm->getData()->save(
			$this->get('bns.classroom_manager'),
			$this->container->getParameter('domain_id'),
			$schoolInfo->getGroupId(),
			$teacher,
			$schoolInfo
		);
		
		/* TEMPORAIRE pour pré inscription
		 */
			
		$this->get('bns.mailer')->send('CLASSROOM_PREREGISTRATION', array(
				'first_name'	=> $teacher->getFirstName()
			),
			$teacher->getEmail(),
			'fr'
		);
		
		/* FIN TEMPORAIRE
		 */
		return $this->render('BNSAppRegistrationBundle:Free:register_classroom_thanks.html.twig', array(
			'teacher'	=> $teacher,
			'classRoom'	=> $classRoom
		));
	}
	
	/**
	 * @Route("/creation-ecole", name="registration_free_create_school")
	 * @Anon
	 */
	public function createSchoolAction()
	{
		$form = $this->createForm(new SchoolCreationType());
		if ($this->getRequest()->isMethod('POST')) {
			$form->bindRequest($this->getRequest());
			
			if ($form->isValid()) {
				$schoolInfo = $form->getData();
				/* @var $schoolInfo \BNS\App\RegistrationBundle\Model\SchoolInformation */
				if ($schoolInfo->getCountry() == 'FR' && null == $schoolInfo->getUai()) {
					// UAI obligatoire pour la France
					$form->get('uai')->addError(new FormError('Veuillez saisir le code UAI'));
				}
				else {
					$schoolInfo->save();
					
					$this->get('bns.mailer')->send('REQUEST_FOR_SCHOOL_CREATION', array(
						'school_name'		=> $schoolInfo->getName(),
						'confirmation_url'	=> $this->generateUrl('admin_school_information_show', array(
							'id' => $schoolInfo->getId()
						), true)
					),
					$this->container->getParameter('beneyluschool_email'),
					'fr');
					
					return $this->redirect($this->generateUrl('registration_free_create_school_validation'));
				}
			}
		}
		
		return $this->render('BNSAppRegistrationBundle:Free:create_school.html.twig', array(
			'form' => $form->createView()
		));
	}
	
	/**
	 * @Route("/creation-ecole/validation", name="registration_free_create_school_validation")
	 * @Anon
	 */
	public function createSchoolConfirmationAction()
	{
		return $this->render('BNSAppRegistrationBundle:Free:create_school_validation.html.twig');
	}
}