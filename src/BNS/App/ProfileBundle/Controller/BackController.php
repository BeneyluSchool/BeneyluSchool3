<?php

namespace BNS\App\ProfileBundle\Controller;

use BNS\App\CommentBundle\Form\Type\CommentType;
use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Controller\BaseController;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Utils\Crypt;
use BNS\App\NotificationBundle\Notification\ProfileBundle\ProfileModifiedNotification;
use BNS\App\ProfileBundle\Form\Model\ProfileFormModel;
use BNS\App\ProfileBundle\Form\Type\ProfileType;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BackController extends BaseController
{
    /**
     * @Route("/", name="BNSAppProfileBundle_back")
     *
     * @return RedirectResponse
     */
    public function indexAction()
    {
        $user = $this->getUser();
        $um = $this->get('bns.user_manager');
        $rm = $this->get('bns.right_manager');
        $um->setUser($user);
        if ($um->isChild()) {
            //Les adultes ont forcément accès à l'édition de leur profil
            $rm->forbidIf(!$rm->hasRight('PROFILE_ACCESS_BACK'));
        }
        if ($user->getProfile()->isFilled() && $this->hasFeature('profile_status')) {
            return $this->redirect($this->generateUrl('BNSAppProfileBundle_back_status'));
        }

        $this->get('stat.profile')->visit();

        return $this->editProfileAction($user->getSlug(), $user);
    }

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
            'is_moderate'    => !in_array('PROFILE_NO_MODERATE_COMMENT', $groupManager->getPermissionsForRole($groupManager->getGroup(), $pupilRole)),
            'namespace'        => Crypt::encrypt('BNS\\App\\CoreBundle\\Model\\ProfileComment'),
            'page'            => $page,
            'user'            => $this->getUser(),
            'editRoute'        => 'profile_manager_comment_moderation_edit',
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

        //La modération est en place si les élèves n'ont pas la permission PROFILE_NO_MODERATE_STATUS

        return $this->render('BNSAppProfileBundle:Moderation:index.html.twig', array(
            'is_moderate'    => !in_array('PROFILE_NO_MODERATE_STATUS', $groupManager->getPermissionsForRole($groupManager->getGroup(), $pupilRole)),
            'page'           => $page,
            'user'           => $this->getUser(),
        ));
    }

    /**
     * @Route("/assistance", name="profile_manager_assistance")
     * @Rights("PROFILE_ACCESS_BACK")
     */
    public function assistanceAction()
    {
        $rightManager = $this->get('bns.right_manager');

        // Check des droits d'accès
        $rightManager->forbidIf(!$rightManager->canActivateAssistance($this->getUser()));

        $groupManager = $this->get('bns.group_manager');
        $groupManager->setGroup($rightManager->getCurrentGroup());

        $assistantIds = [];
        foreach ($groupManager->getUniqueAncestors() as $parent) {
            $userIds = $groupManager->getUserIdsWithPermission('GROUP_GIVE_ASSISTANCE', $parent);

            $assistantIds = array_merge($assistantIds, $userIds);
        }
        $assistants = UserQuery::create()->findPks($assistantIds);

        return $this->render('BNSAppProfileBundle:Assistance:index.html.twig', array(
            'user'  => $this->getUser(),
            'assistants' => $assistants,
        ));
    }

    /**
     * @Route("/assistance/switch", name="profile_manager_assistance_switch")
     * @Rights("PROFILE_ACCESS_BACK")
     */
    public function switchAssistanceAction(Request $request)
    {
        if (!$this->get('bns.right_manager')->canActivateAssistance($this->getUser())) {
            throw $this->createAccessDeniedException();
        }
        /** @var User $user */
        $user = $this->getUser();
        $profile = $user->getProfile();
        $state = json_decode($request->getContent());
        if ($state) {
            $profile->setAssistanceEnabled(!$state->state);
            $profile->save();
        }
        $assistanceEnabled = $profile->getAssistanceEnabled();

        return new JsonResponse(array('moderate' => $assistanceEnabled));
    }

    /**
     * @Route("/moderation/switch", name="profile_manager_moderation_switch")
     *
     * @Rights("PROFILE_ADMINISTRATION")
     */
    public function switchModerationAction(Request $request)
    {
//        if (!$this->getRequest()->isMethod('POST') || !$this->getRequest()->isXmlHttpRequest()) {
//            throw new NotFoundHttpException('The page excepts POST & AJAX header !');
//        }

        $groupManager = $this->get('bns.group_manager');
        $context = $this->get('bns.right_manager')->getContext();

        $pupilRole = GroupTypeQuery::create('g')
            ->where('g.Type = ?', 'PUPIL')
        ->findOne();

        if (null == $pupilRole) {
            throw new \RuntimeException('The group type with type : PUPIL is NOT found !');
        }

        $state = json_decode($request->getContent());
        $groupManager->setGroupById($context['id']);

        if ($state !== null) {
            $state = $state->state;
            $groupManager->activationRankRequest('PROFILE_NO_MODERATE_STATUS', $pupilRole, $state);
        }

        return  new Response(json_encode(array('moderate' => !in_array('PROFILE_NO_MODERATE_STATUS', $groupManager->getPermissionsForRole($groupManager->getGroup(), $pupilRole)))));
    }


    /**
     * @Route("/editer/{id}", name="profile_back_edit_id", options={"expose": true}, requirements={"id": "[0-9]+"})
     * @Route("/editer/{userSlug}", name="profile_back_edit")
     */
    public function editProfileAction($userSlug = null, User $user = null, $id = null)
    {
        if (null == $user) {
            if ($id) {
                $user = $this->get('bns.user_manager')->findUserById($userSlug);
            } elseif ('null' != $userSlug) {
                $user = $this->get('bns.user_manager')->findUserBySlug($userSlug);
            } else {
                $user = $this->getUser();
            }
        }

        $this->canViewProfile($user);

        if ($isChild = $this->get('bns.user_manager')->isChild()) {
            //Les adultes ont forcément accès à l'édition de leur profil
            $this->get('bns.right_manager')->forbidIf(!$this->get('bns.right_manager')->hasRight('PROFILE_ACCESS_BACK'));
        }
        $parameters = array('timezone' => $user->getTimezone(), 'lang' => $user->getLang());
        $form = $this->createForm(new ProfileType($user, $parameters), new ProfileFormModel($user));

        return $this->render('BNSAppProfileBundle:Back:back_profile_index.html.twig', array(
            'user'    => $user,
            'form'    => $form->createView(),
            'isChild' => $isChild,
        ));
    }

    /**
     * @Route("/{userSlug}/profil/sauvegarder", name="BNSAppProfileBundle_back_save")
     * @return RedirectResponse
     */
    public function saveAction($userSlug)
    {
        $user = $this->get('bns.user_manager')->findUserBySlug($userSlug);
        if (null == $user) {
            $this->get('bns.right_manager')->forbidIf(true);
        }

        $this->canViewProfile($user);
        $oldEmail = $user->getEmail();

        if ($isChild = $this->get('bns.user_manager')->isChild()) {
            //Les adultes ont forcément accès à l'édition de leur profil
            $this->get('bns.right_manager')->forbidIf(!$this->get('bns.right_manager')->hasRight('PROFILE_ACCESS_BACK'));
        }
        $parameters = array('available_languages' => $this->container->getParameter('available_languages'), 'timezone' => $user->getTimezone(), 'lang' => $user->getLang());
        $form = $this->createForm(new ProfileType($user, $parameters), new ProfileFormModel($user));
        if (null != $this->getRequest()->get(ProfileType::FORM_NAME)) {
            if ('POST' == $this->getRequest()->getMethod()) {
                $form->bind($this->getRequest());

                if ($form->isValid()) {
                    if ($form->getData()->lang != null) {
                        $this->get('bns.right_manager')->setLocale($form->getData()->lang);
                    }
                    if ($form->getData()->timezone != null) {
                        $this->get('bns.right_manager')->setTimezone($form->getData()->timezone);
                    }
                    $email = $form->getData()->email;
                    if ($email && $oldEmail !== $email && /* Temporary allow parent to reuse email */ 9 !== (int)$user->getHighRoleId()) {
                        $emailUser = $this->get('bns.user_manager')->getUserByEmail($form->getData()->email);

                        if ($emailUser && $emailUser->getId() != $user->getId()) {
                            $form->get('email')->addError(new FormError($this->get('translator')->trans('EMAIL_ALREADY_USED', array(), 'PROFILE')));

                            return $this->render('BNSAppProfileBundle:Back:back_profile_index.html.twig', array(
                                'user'    => $user,
                                'form'    => $form->createView(),
                                'isChild' => $isChild
                            ));
                        }
                    }
                    $form->getData()->save();

                    if($this->get('bns.right_manager')->isAdult())
                    {
                        $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('ADULT_PROFILE_UPDATED', array(), 'PROFILE'));
                    }else{
                        $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('CHILD_PROFILE_UPDATED', array(), 'PROFILE'));
                    }

                    if ($isChild = $this->get('bns.user_manager')->isChild()) {
                        $currentGroupId = $this->get('bns.right_manager')->getCurrentGroupId();
                        $managerIds = $this->get('bns.group_manager')->getUserWithPermission('CLASSROOM_ACCESS_BACK');
                        $this->get('notification_manager')->send($managerIds, new ProfileModifiedNotification($this->get('service_container'), $user->getId(), $currentGroupId));
                    }

                    return new RedirectResponse($this->generateUrl('profile_back_edit', array('userSlug' => $user->getSlug())));
                }
            }
        }

        return $this->render('BNSAppProfileBundle:Back:back_profile_index.html.twig', array(
            'user'    => $user,
            'form'    => $form->createView(),
            'isChild' => $isChild
        ));
    }

    /**
     * @Route("/commentaires/moderation", name="profile_manager_comment_moderation_switch")
     * @Rights("PROFILE_ADMINISTRATION")
     */
    public function switchCommentModerationAction(Request $request)
    {
//        if (!$this->getRequest()->isMethod('POST') || !$this->getRequest()->isXmlHttpRequest()) {
//            throw new NotFoundHttpException('The page excepts POST & AJAX header !');
//        }

        $groupManager = $this->get('bns.group_manager');
        $context = $this->get('bns.right_manager')->getContext();
        $groupManager->setGroupById($context['id']);

        $pupilRole = GroupTypeQuery::create('g')
            ->where('g.Type = ?', 'PUPIL')
        ->findOne();

        if (null == $pupilRole) {
            throw new \RuntimeException('The group type with type : PUPIL is NOT found !');
        }

        $state = json_decode($request->getContent());

        if ($state !== null) {
            $state = $state->state;
            $groupManager->activationRankRequest('PROFILE_NO_MODERATE_COMMENT', $pupilRole, $state);
        }

        return  new Response(json_encode(array('moderate' => !in_array('PROFILE_NO_MODERATE_COMMENT', $groupManager->getPermissionsForRole($groupManager->getGroup(), $pupilRole)))));
    }

    /**
     * @Route("/commentaire/{id}/editer", name="profile_manager_comment_edit")
     * @Rights("PROFILE_ADMINISTRATION")
     */
    public function editComment($id, $isModeration = false)
    {
        $user = UserQuery::create('u')
            ->join('u.Profile')
            ->join('Profile.ProfileFeed')
            ->joinWith('ProfileFeed.ProfileComment')
            ->where('ProfileComment.Id = ?', $id)
        ->findOne();

        if (null == $user) {
            throw new NotFoundHttpException('The user linked by comment id : ' . $id  . ' is NOT found ! ');
        }

        $this->canViewProfile($user);

        $namespace = 'BNS\\App\\CoreBundle\\Model\\ProfileComment';
        $form = $this->createForm(new CommentType());
        if ($this->getRequest()->isMethod('POST')) {
            $form->bind($this->getRequest());

            if ($form->isValid()) {
                $comment = $form->getData();
                $comment->save($namespace);

                $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('COMMENT_UPDATED', array(), 'PROFILE'));

                // Choose the right redirection, moderation or object visualisation
                if ($isModeration) {
                    $view = 'profile_manager_comment';
                    $params = array();
                }
                else {
                    $view = 'profile_manager_feed_visualisation';
                    $params = array(
                        'feedId' => $comment->getComment()->getObject()->getId()
                    );
                }

                return $this->redirect($this->generateUrl($view, $params));
            }
        }

        return $this->forward('BNSAppCommentBundle:BackComment:renderEditComment', array(
            'id'              => $id,
            'namespace'       => $namespace,
            'extendsView'     => 'BNSAppProfileBundle:Comment:comment_form.html.twig',
            'isModeration'    => $isModeration,
            'form'            => $form
        ));
    }

    /**
     * @Route("/commentaires/moderation/{id}/editer", name="profile_manager_comment_moderation_edit")
     * @Rights("PROFILE_ADMINISTRATION")
     */
    public function editModerationComment($id)
    {
        return $this->editComment($id, true);
    }

    /**
     * @param User $user
     *
     * @return boolean
     */
    protected function canViewProfile($user)
    {
        if ($this->getUser()->getId() == $user->getId()) {
            return true;
        } else {
            $gm = $this->get('bns.group_manager');
            $authorisedGroups = $this->get('bns.right_manager')->getGroupsWherePermission('PROFILE_ADMINISTRATION');

            foreach ($authorisedGroups as $group) {
                $gm->setGroup($group);

                if (in_array($user->getId(), $gm->getUsersIds())) {
                    return true;
                }
            }
        }

        $this->get('bns.right_manager')->forbidIf(true);
    }

}
