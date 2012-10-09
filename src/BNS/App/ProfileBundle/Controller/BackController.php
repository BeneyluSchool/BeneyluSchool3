<?php

namespace BNS\App\ProfileBundle\Controller;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Utils\Crypt;
use BNS\App\ProfileBundle\Form\Model\ProfileFormModel;
use BNS\App\ProfileBundle\Form\Type\ProfileType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BackController extends Controller
{
	/**
	 * @Route("/commentaires", name="profile_manager_comment")
	 * @Rights("PROFILE_ADMINISTRATION")
	 */
	public function commentsAction($page = 1)
	{
		$groupManager = $this->get('bns.group_manager');
		$context = $this->get('bns.right_manager')->getContext();
		$groupManager->setGroupById($context['id']);
		
		$pupilRole = GroupTypeQuery::create('g')
			->where('g.Type = ?', 'PUPIL')
		->findOne();
		
		return $this->render('BNSAppProfileBundle:Comment:index.html.twig', array(
			'is_moderate'	=> in_array('PROFILE_NO_MODERATE_COMMENT', $groupManager->getPermissionsForRoleInCurrentGroup($pupilRole)),
			'namespace'		=> Crypt::encrypt('BNS\\App\\CoreBundle\\Model\\ProfileComment'),
			'page'			=> $page
		));
	}
	
	/**
	 * @Route("/moderation", name="profile_manager_moderation")
	 * @Rights("PROFILE_ADMINISTRATION")
	 */
	public function moderationAction($page = 1)
	{
		$groupManager = $this->get('bns.group_manager');
		$context = $this->get('bns.right_manager')->getContext();
		$groupManager->setGroupById($context['id']);
		
		$pupilRole = GroupTypeQuery::create('g')
			->where('g.Type = ?', 'PUPIL')
		->findOne();
		
		return $this->render('BNSAppProfileBundle:Moderation:index.html.twig', array(
			'is_moderate'	=> in_array('PROFILE_NO_MODERATE_STATUS', $groupManager->getPermissionsForRoleInCurrentGroup($pupilRole)),
			'page'			=> $page
		));
	}
	
	/**
	 * @Route("/moderation/switch", name="profile_manager_moderation_switch")
	 * @Rights("PROFILE_ADMINISTRATION")
	 */
	public function switchModerationAction()
	{
		if (!$this->getRequest()->isMethod('POST') || !$this->getRequest()->isXmlHttpRequest()) {
			throw new NotFoundHttpException('The page excepts POST & AJAX header !');
		}
		
		$groupManager = $this->get('bns.group_manager');
		$context = $this->get('bns.right_manager')->getContext();
		
		$pupilRole = GroupTypeQuery::create('g')
			->where('g.Type = ?', 'PUPIL')
		->findOne();
		
		if (null == $pupilRole) {
			throw new \RuntimeException('The group type with type : PUPIL is NOT found !');
		}
		
		$state = $this->getRequest()->get('state', false);
		
		$groupManager->setGroupById($context['id']);
		$groupManager->activationRankRequest('PROFILE_NO_MODERATE_STATUS', $pupilRole, $state);
		
		return new Response();
	}
	
	/**
	 * @Route("/{userSlug}", name="BNSAppProfileBundle_back", defaults={"userSlug"="null"})
	 * @RightsSomeWhere("PROFILE_ACCESS_BACK")
	 * 
	 * @return RedirectResponse 
	 */
    public function indexAction()
    {
        $user = $this->getUser();
        if ($user->getProfile()->isFilled()) {
            return $this->redirect($this->generateUrl('BNSAppProfileBundle_back_status'));
        }

        return $this->editProfileAction($user->getSlug(), $user);
    }

    /**
     * @Route("/editer/{userSlug}", name="profile_back_edit")
	 * @RightsSomeWhere("PROFILE_ACCESS_BACK")
     */
    public function editProfileAction($userSlug, User $user = null)
    {
        if (null == $user) {
            if ('null' != $userSlug) {
                $user = $this->get('bns.user_manager')->findUserBySlug($userSlug);
            }
            else {
                $user = $this->getUser();
            }
        }

        $form = $this->createForm(new ProfileType($user), new ProfileFormModel($user));

        return $this->render('BNSAppProfileBundle:Back:back_profile_index.html.twig', array(
            'user'	=> $user,
            'form'	=> $form->createView()
        ));
    }
    
    /**
     * @Route("/{userSlug}/profil/sauvegarder", name="BNSAppProfileBundle_back_save")
	 * @RightsSomeWhere("PROFILE_ACCESS_BACK")
     *  
     * @return RedirectResponse
     */
    public function saveAction($userSlug)
    {
        $user = $this->get('bns.user_manager')->findUserBySlug($userSlug);
        if (null == $user) {
            $this->get('bns.right_manager')->forbidIf(true);
        }
		
    	if (null != $this->getRequest()->get(ProfileType::FORM_NAME)) {
            $form = $this->createForm(new ProfileType($user), new ProfileFormModel($user));
            if ('POST' == $this->getRequest()->getMethod()) {
                $form->bindRequest($this->getRequest());
                
                if ($form->isValid()) {
					$emailUser = $this->get('bns.user_manager')->getUserByEmail(urlencode($form->getData()->email));
					
					if ($emailUser->getId() != $user->getId()) {
						$form->get('email')->addError(new FormError("L'adresse e-mail renseignée est déjà utilisée, veuillez en renseigner une nouvelle"));
						
						return $this->render('BNSAppProfileBundle:Back:back_profile_index.html.twig', array(
							'user'	=> $user,
							'form'	=> $form->createView()
						));
					}
					
                    $form->getData()->save();
					
                    if ($form->getData()->avatarId == null) {
                        $user->getProfile()->setAvatarId(null);
                        $user->save();
                    }
					
                    $this->get('session')->setFlash('success', 'Votre profil a bien été mis à jour !');
                }
            }
    	}
    	
    	return new RedirectResponse($this->generateUrl('profile_back_edit', array('userSlug' => $user->getSlug())));
    }
	
	/**
	 * @Route("/commentaires/moderation", name="profile_manager_comment_moderation_switch")
	 * @Rights("PROFILE_ADMINISTRATION")
	 */
	public function switchCommentModerationAction()
	{
		if (!$this->getRequest()->isMethod('POST') || !$this->getRequest()->isXmlHttpRequest()) {
			throw new NotFoundHttpException('The page excepts POST & AJAX header !');
		}
		
		$groupManager = $this->get('bns.group_manager');
		$context = $this->get('bns.right_manager')->getContext();
		
		$pupilRole = GroupTypeQuery::create('g')
			->where('g.Type = ?', 'PUPIL')
		->findOne();
		
		if (null == $pupilRole) {
			throw new \RuntimeException('The group type with type : PUPIL is NOT found !');
		}
		
		$state = $this->getRequest()->get('state', false);
		
		$groupManager->setGroupById($context['id']);
		$groupManager->activationRankRequest('PROFILE_NO_MODERATE_COMMENT', $pupilRole, $state);
		
		return new Response();
	}
}