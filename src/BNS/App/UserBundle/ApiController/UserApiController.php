<?php

namespace BNS\App\UserBundle\ApiController;

use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\CoreBundle\ApiLimit\ApiLimit;

use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\ModulePeer;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\InfoBundle\Model\SponsorshipQuery;
use BNS\App\NotificationBundle\Model\NotificationQuery;
use BNS\App\UserBundle\Credentials\UserCredentialsManager;
use BNS\App\UserBundle\Form\Type\ApiUserType;
use BNS\App\UserBundle\Form\Type\PasswordChangeApiType;
use BNS\App\UserBundle\Form\Type\SpotUserCreateAccountType;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcherInterface;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Guzzle\Http\Exception\ClientErrorResponseException;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Validator\Constraints\Email as EmailConstraint;
use Symfony\Component\Validator\Constraints\NotBlank as EmailNotBlank;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Class UserApiController
 *
 * @package BNS\App\UserBundle\ApiController
 */
class UserApiController extends BaseApiController
{

    /**
     * @ApiDoc(
     *  section="Users",
     *  resource = true,
     *  description="Détails de l'utilisateur actuellement authentifié",
     * )
     *
     * @Rest\Get("/me")
     * @Rest\View(serializerGroups={"Default","detail","me","user_children_preview"})
     */
    public function getMeAction()
    {
        return $this->getUser();
    }

    /**
     * @ApiDoc(
     *  section="Users",
     *  resource = true,
     *  description="Show beta mode informations",
     * )
     *
     * @Rest\Get("/me/beta")
     * @Rest\View()
     */
    public function getMeBetaAction(Request $request)
    {
        $betaManager = $this->get('bns_app_core.beta_manager');
        if (!$betaManager->isBetaModeAllowed() || 'fr' !== $request->getLocale()) {
            // no beta mode
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        $betaMode = $betaManager->isBetaModeEnabled();
        $user = $this->getUser();

        $userManager = $this->get('bns.user_manager');
        $groupIds = $userManager->getGroupIdsWherePermission('MAIN_BETA_SWITCH_GROUP');

        return [
            'beta_mode' => $betaMode,
            'beta_user' => $user->getBeta(),
            'can_change_mode' => $userManager->hasRightSomeWhere('MAIN_BETA_SWITCH'),
            'can_change_mode_in' => $groupIds,
            'beta_redirect_url' => $betaManager->generateBetaRoute('home', ['user_id' => $user->getId()]),
            'normal_redirect_url' => $betaManager->generateNormalRoute('home', ['user_id' => $user->getId()]),
        ];
    }

    /**
     * @ApiDoc(
     *  section="Users",
     *  resource = true,
     *  description="Change beta mode informations",
     * )
     *
     * @Rest\Patch("/me/beta/{mode}", requirements={"mode":"0|1"})
     * @Rest\View()
     */
    public function patchMeBetaAction($mode, Request $request)
    {
        $betaManager = $this->get('bns_app_core.beta_manager');
        if (!$betaManager->isBetaModeAllowed() || 'fr' !== $request->getLocale()) {
            // no beta mode
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        $mode = (boolean) $mode;
        $user = $this->getUser();

        if ($user->getBeta() !== $mode) {
            $user->setBeta($mode);
            $user->save();

            return View::create('', Codes::HTTP_NO_CONTENT);
        }

        return View::create('', Codes::HTTP_NOT_MODIFIED);
    }

    /**
     * @ApiDoc(
     *  section="Users",
     *  resource = true,
     *  description="L'école de l'utilisateur connecté (pour Spot)",
     * )
     *
     * @Rest\Get("/me/school")
     * @Rest\View()
     */
    public function getMeSchoolAction()
    {
        $user = $this->getUser();
        if (!$user || !$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $schoolTypeId = GroupTypeQuery::create()->filterByType('SCHOOL')->select('Id')->findOne();
        $schools = $this->get('bns.user_manager')->setUser($user)->getSimpleGroupsAndRolesUserBelongs(true, array($schoolTypeId));

        /** @var Group $school */
        foreach ($schools as $school) {

            $data = [
                'name' => $school->getLabel(),
                'city' => $school->getAttribute('CITY'),
                'zip_code' => $school->getAttribute('ZIPCODE'),
                'country' => $school->getCountry(),
            ];

            return View::create($data);
        }

        return View::create('', Codes::HTTP_NOT_FOUND);
    }


    /**
     * @ApiDoc(
     *  section="Users",
     *  resource = true,
     *  description="Get list of user applications",
     * )
     *
     * @Rest\Get("/me/applications")
     * @Rest\View(serializerGroups={"Default", "basic", "details"})
     */
    public function getMeApplicationsAction()
    {
        $user = $this->getUser();
        $applicationManager = $this->get('bns_core.application_manager');

        if (!$applicationManager->isEnabled()) {
            return View::create('', Codes::HTTP_BAD_REQUEST);
        }

        $modules = $applicationManager->getBaseApplications([ModulePeer::TYPE_APP, ModulePeer::TYPE_SUBAPP]);
        $group = $this->get('bns.right_manager')->getCurrentGroup();
        $groupManager = $this->get('bns.group_manager');
        $activatedModules = $groupManager->getActivatedModuleUniqueNames($group);

        // TODO move to a manager
        $userManager = $this->get('bns.user_manager');

        if ('PARTNERSHIP' === $group->getType() && $group->getAttribute('IS_HIGH_SCHOOL')) {
            $forcePrivateApplications = [
                'USER_DIRECTORY',
                'MESSAGING',
                'MEDIA_LIBRARY',
                'PROFILE',
            ];
        } else {
            $forcePrivateApplications = $groupManager->getProjectInfoCurrentFirst('private_applications');
            if (!$forcePrivateApplications || !is_array($forcePrivateApplications)) {
                $forcePrivateApplications = [];
            }
        }

        $removed = [];
        foreach ($modules as $key => $module) {
            $uniqueName = $module->getUniqueName();
            if ($userManager->setUser($user)->hasRightSomeWhere($uniqueName . '_ACCESS')) {
                $module->hasAccessFront = true;
            }
            if ($userManager->hasRightSomeWhere($uniqueName . '_ACCESS_BACK')) {
                $module->hasAccessBack = true;
            }

            if (!($module->hasAccessFront || $module->hasAccessBack)
                && in_array($module->getUniqueName(), $forcePrivateApplications)
            ) {
                $removed[] = $key;
                continue;
            }

            if ($userManager->hasRight($uniqueName . 'ACTIVATION', $group->getId())) {
                $module->canOpen = true;
            }
            if (isset($activatedModules[$uniqueName])) {
                $module->isOpen = true;
                if ('partial' === $activatedModules[$uniqueName]) {
                    $module->isPartiallyOpen = true;
                }
            }

            switch ($module->getUniqueName()) {
                case 'PROFILE':
                    $module->setCustomLabel($user->getFullname());
                    break;

                case 'NOTIFICATION':
                    $module->counter = $this->get('notification_manager')->getUnreadNotificationNumber($user);
                    break;

                case 'INFO':
                    $module->counter = $this->get('bns.right_manager')->getNbNotifInfo() ?: null;
                    break;
            }
        }
        foreach ($removed as $key) {
            // php7 bug remove key outside foreach
            unset($modules[$key]);
        }

        // fix serializer bug with array wrongly indexed (it should be 0 to count()-1)
        return array_values($modules->getArrayCopy());
    }

    /**
     * @ApiDoc(
     *  section="Users",
     *  description="Check if user has right somewhere",
     * )
     *
     * @Rest\Get("/rights/{rightName}")
     * @Rest\View()
     *
     * @param string $rightName
     * @return array|View
     */
    public function getHasRightAction($rightName)
    {
        if (!preg_match("/[a-zA-Z0-9_-]/", $rightName)) {
            return View::create('', Codes::HTTP_BAD_REQUEST);
        }

        return [
            'has_right' => $this->get('bns.right_manager')->hasRightSomeWhere($rightName),
        ];
    }

    /**
     * @ApiDoc(
     *  section="Users",
     *  description="Check if user has right in group",
     * )
     *
     * @Rest\Get("/rights/{rightName}/groups/{groupId}")
     * @Rest\View()
     *
     * @param int $groupId
     * @param string $rightName
     */
    public function getHasRightInGroupAction($rightName, $groupId)
    {
        if (!preg_match("/[a-zA-Z0-9_-]/", $rightName)) {
            return View::create('', Codes::HTTP_BAD_REQUEST);
        }

        if ($this->get('bns.right_manager')->hasRight($rightName, (int)$groupId)) {
            return array('has_right' => true);
        }

        return array('has_right' => false);
    }

    /**
     * @ApiDoc(
     *  section="Users",
     *  description="Asks for a password reset",
     * )
     *
     * @Rest\Post("/password/reset")
     * @Rest\RequestParam(name="identifier", requirements="\S+", description="Username or email")
     * @Rest\View()
     *
     * @param ParamFetcherInterface $paramFetcher
     * @return array
     */
    public function resetPasswordAction(ParamFetcherInterface $paramFetcher)
    {
        $identifier = $paramFetcher->get('identifier');
        $user = UserQuery::create()->findOneByLogin($identifier);
        if (!$user) {
            $user = UserQuery::create()->findOneByEmail($identifier);
        }
        if (!$user) {
            throw new NotFoundHttpException('User not found');
        }

        $confirmationToken = $this->get('bns.user_manager')->requestConfirmationResetPassword($user);

        $this->get('bns.mailer')->sendUser('REQUEST_RESET_PASSWORD', array(
            'first_name'		=> $user->getFirstName(),
            'confirmation_url'	=> $this->get('router')->generate('user_password_reset_process', array(
                'confirmationToken'	=> $confirmationToken
            ), true)
        ), $user);

        return ['sent' => true];
    }

    /**
     * @ApiDoc(
     *  section="Users",
     *  description="Change the current user password",
     * )
     * @Rest\Post("/password/change")
     * @Rest\View()
     *
     * @RightsSomeWhere("MAIN_UPDATE_CREDENTIAL")
     */
    public function changePasswordAction()
    {
        if (!$this->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw $this->createAccessDeniedException();
        }

        $form = new PasswordChangeApiType();

        return $this->restForm($form, [], [
            'csrf_protection' => false,
        ], null, function ($data, $form) {
            $responseData = null;
            try {
                $mustRedirect = false;
                if (isset($data['redirect']) && $data['redirect']) {
                    $mustRedirect = true;
                }
                unset($data['redirect']);

                $this->get('bns.user.credentials_manager')->updatePassword($data);

                // Check if redirect after logon
                if ($this->get('session')->has(UserCredentialsManager::NEED_UPDATE_CREDENTIAL_SESSION_KEY) || !$this->get('bns.right_manager')->getCurrentGroupId()) {
                    if ($redirect = $this->get('bns.user_manager')->onLogon()) {
                        $responseData = [
                            'redirect' => $redirect
                        ];
                    }
                }

                $this->get('session')->remove(UserCredentialsManager::NEED_UPDATE_CREDENTIAL_SESSION_KEY);
            } catch (ClientErrorResponseException $e) {
                $response = $e->getResponse()->json();

                return View::create($response)->setStatusCode($response['code']);
            }

            if ($mustRedirect) {
                $this->addFlash('success', $this->get('translator')->trans('FLASH_CHANGE_PASSWORD_SUCCESS', [], 'JS_ACCOUNT'));
            }

            return View::create($responseData, Codes::HTTP_OK);
        });
    }

    /**
     * @deprecated should be removed when new Beneylu Pay is online
     *
     * @ApiDoc(
     *  section="Utilisateurs - Création d'utilisateur depuis page d'accueil",
     *  resource = false,
     *  description="Uniquement depuis la home non connecté",
     *  requirements = {
     *      {
     *          "name" = "email",
     *          "dataType" = "varchar",
     *          "description" = "Email pour inscription"
     *      }
     *  },
     *  statusCodes = {
     *      201 = "Ok - Compte créé",
     *      400 = "Erreur - A priori email déjà existant",
     *      403 = "Pas d'accès à l'inscription",
     *      404 = "Aucun email saisi",
     *      500 = "erreur serveur"
     *  }
     * )
     *
     * @Rest\Post("/subscription")
     */
    public function subscriptionAction(Request $request)
    {
        if (!$this->container->getParameter('bns.enable_register')) {
            throw $this->createNotFoundException();
        }

        $translator = $this->get('translator');
        $email = $request->get('email');
        $origin = substr(strtoupper($request->get('origin')), 0, 50);

        $emailConstraint = array(
            new EmailConstraint(),
            new EmailNotBlank()
        );
        $errors = $this->get('validator')->validateValue(
            $email,
            $emailConstraint
        );

        if (count($errors) !== 0) {
            return new Response($translator->trans("ERROR_INVALID_EMAIL", array(), "USER"), 404);
        }

        $canProceed = $this->get('bns.api_limit_manager')->check($request->getClientIp(),
                ApiLimit::HOME_SUBSCRIPTION) && $this->container->hasParameter('bns.enable_register') && $this->container->getParameter('bns.enable_register');

        if (!$canProceed) {
            return new Response($translator->trans("ERROR_NOT_AUTHORIZED", array(), "USER"), 403);
        }

        //Existe déjà ?

        $exists = $this->get('bns.user_manager')->getUserByEmail($email);

        if ($exists != null) {

            if (1 === $exists->getRegistrationStep()) {
                $teacher = $exists;
                goto registrationStep1;
            }

            return new Response($translator->trans("ERROR_ALREADY_EXIST", array(), "USER"), 400);
        }

        //Ok, on lance le process :)
        //Choix de la langue

        $locale = $request->getLocale();

        $values = array(
            'first_name' => 'First_Name',
            'last_name' => 'Last_Name',
            'email' => $email,
            'email_validated' => false,
            'lang' => $locale,
        );

        $teacher = $this->get('bns.user_manager')->createUser($values, true);
        $teacher->setRegistrationStep(1);
        $teacher->setRegisterOrigin($origin?: 'UNKNOWN');
        $teacher->save();

        $school = $this->get('bns.group_manager')->createGroup([
            'type' => 'SCHOOL',
            'label' => $translator->trans('LABEL_MY_SCHOOL', array(), 'USER',
                $locale),
            'lang' => $locale,
            'group_parent_id' => 1,
        ]);


        if (!$school) {
            return new JsonResponse([ 'message' => $translator->trans("ERROR_CANT_CREATE_SCHOOL", array(), "USER")], 500);
        }
        $this->get('bns.group_manager')->addParent($school->getId(), 1);

        $classroomManager = $this->get('bns.classroom_manager');
        $classroom = $classroomManager->createClassroom(array(
            'label' => $translator->trans('LABEL_MY_CLASSROOM', array(), 'USER',
                $locale),
            'lang' => $locale,
            //On place en enfant du groupe d'#ID 1
            'group_parent_id' => $school->getId(),
            'validated' => true,
            'attributes' => array(
                'CURRENT_YEAR' => $this->container->getParameter('registration.current_year')
            )
        ));


        $classroomManager->setClassroom($classroom);
        $classroomManager->assignTeacher($teacher);
        $classroomManager->sponsorshipAfterRegistration($teacher);

        $this->get('session')->set('identify_instant', true);
        $this->get('bns.analytics.manager')->identifyUser($teacher, $classroom);

        registrationStep1:
        $autologinToken = $this->get('bns.user_manager')->getAutologinToken(
            $teacher,
            $this->getParameter('security.oauth.client_id'),
            $this->generateUrl('home', array(), UrlGenerator::ABSOLUTE_URL)
        );

        $autologinUrl = $this->getParameter('oauth_host') . '/registration/autologin/' . $autologinToken;

        return new JsonResponse(array('url' => $autologinUrl));
    }

    /**
     * @deprecated should be removed when new Beneylu Pay is online
     *
     * <pre>
     * {
     *   "first_name": "Jim",
     *   "last_name": "Beneylu",
     *   "civility": "M",
     *   "lang": "fr",
     *   "email": "email@beneylu.com",
     *   "country": "FR",
     *   "classroom": {
     *     "label": "My classroom"
     *   },
     *   "school": {
     *     "label": "My school",
     *     "address": "1 road of school",
     *     "zipcode": "999999",
     *     "city": "paris",
     *   }
     * }
     * </pre>
     *
     *
     * @ApiDoc(
     *  section="Utilisateurs - Création d'un compte utilisateur et de sa classe",
     *  resource = false,
     *  description="Uniquement depuis le spot sécurisé par signature",
     *  statusCodes = {
     *      201 = "Ok - Compte créé",
     *      400 = "Erreur - A priori email déjà existant",
     *      403 = "Pas d'accès à l'inscription",
     *      404 = "Aucun email saisi",
     *      500 = "erreur serveur"
     *  }
     * )
     *
     * @Rest\Post("/spot-create-account")
     */
    public function createAccountAction(Request $request)
    {
        if (!$this->container->getParameter('bns.enable_register')) {
            throw $this->createNotFoundException();
        }
        // security SPOT public signature check
        $this->get('bns_core.security_firewall.apikey_request_validator')->validateRequest($request);

        // normalize locale
        $locale = $this->get('bns.locale_manager')->getBestLocale($request->request->get('lang'));
        $request->request->set('lang', $locale);

        return $this->restForm(new SpotUserCreateAccountType(), null, [
            'csrf_protection' => false,
            'validation_groups' => ['SpotUserCreate']
        ], null, function($data) use ($locale) {
            $translator = $this->get('translator');
            $locale = $data['lang'];

            // check user email exist
            $exists = $this->get('bns.user_manager')->getUserByEmail($data['email']);
            if ($exists) {
                if (1 === $exists->getRegistrationStep()) {
                    // TODO complete user registration
                }

                return new JsonResponse([
                    'code' => Codes::HTTP_BAD_REQUEST,
                    'error_code' => 'email_exist',
                    'message' => $translator->trans("ERROR_ALREADY_EXIST", array(), "USER", $locale)
                ], Codes::HTTP_BAD_REQUEST);
            }

            // Create User With Api call
            $teacher = $this->get('bns.user_manager')->createUser([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'lang' => $locale,
                'email' => $data['email'],
                'email_validated' => false,
            ], true);

            // update locale user data
            $teacher->setGender(isset($data['gender']) ? $data['gender'] : 'M');
            $teacher->setRegistrationStep(0);
            $teacher->setRegisterOrigin(isset($data['origin']) ? strtoupper($data['origin']) : 'SPOT');
            $teacher->save();

            // Create School
            $school = $this->get('bns.group_manager')->createGroup([
                'type' => 'SCHOOL',
                'label' => isset($data['school']['label']) ? $data['school']['label'] : $translator->trans('LABEL_MY_SCHOOL', array(), 'USER', $locale),
                'lang' => $locale,
                'group_parent_id' => 1,
            ]);

            if (!$school) {
                return new JsonResponse([
                    'code' => Codes::HTTP_INTERNAL_SERVER_ERROR,
                    'error_code' => 'school_creation_failed',
                    'message' => $translator->trans("ERROR_CANT_CREATE_SCHOOL", [], "USER", $locale)
                ], Codes::HTTP_INTERNAL_SERVER_ERROR);
            }
            $school->setCountry($data['country']);
            if (isset($data['school']['city'])) {
                $school->setAttribute('CITY', $data['school']['city']);
            }
            if (isset($data['school']['zipcode'])) {
                $school->setAttribute('ZIPCODE', $data['school']['zipcode']);
            }
            $school->save();
            $this->get('bns.group_manager')->addParent($school->getId(), 1);

            // Create Classroom
            $classroomManager = $this->get('bns.classroom_manager');
            $classroom = $classroomManager->createClassroom(array(
                'label' => isset($data['classroom']['label']) ? $data['classroom']['label'] : $translator->trans('LABEL_MY_CLASSROOM', array(), 'USER', $locale),
                'lang' => $locale,
                'group_parent_id' => $school->getId(),
                'validated' => true,
                'attributes' => [
                    'CURRENT_YEAR' => $this->container->getParameter('registration.current_year')
                ]
            ));

            if (!$classroom) {
                return new JsonResponse([
                    'code' => Codes::HTTP_INTERNAL_SERVER_ERROR,
                    'error_code' => 'classroom_creation_failed',
                    'message' => $translator->trans("ERROR_CANT_CREATE_SCHOOL", array(), "USER", $locale)
                ], Codes::HTTP_INTERNAL_SERVER_ERROR);
            }

            $classroom->setCountry($school->getCountry());
            $classroom->save();

            // Add user to classroom
            $classroomManager->setClassroom($classroom);
            $classroomManager->assignTeacher($teacher);

            // Handler sponsorship
            $classroomManager->sponsorshipAfterRegistration($teacher);

            // Send login email
            $this->get('bns.user_manager')->sendLoginEmail($teacher);

            // Analytics identify
            $this->get('session')->set('identify_instant', true);
            $this->get('bns.analytics.manager')->identifyUser($teacher, $classroom);

            // Autoconnect URL
            $autologinToken = $this->get('bns.user_manager')->getAutologinToken(
                $teacher,
                $this->getParameter('security.oauth.client_id'),
                $this->generateUrl('home', array(), UrlGenerator::ABSOLUTE_URL),
                43200 // 12H pour le token de connexion
            );

            $autologinUrl = $this->getParameter('oauth_host') . '/registration/autologin/' . $autologinToken;

            $response = [
                'school' => [
                    'id' => $school->getId(),
                    'label' => $school->getLabel(),
                    'type'  => $school->getType(),
                ],
                'classroom' => [
                    'id' => $classroom->getId(),
                    'label' => $classroom->getLabel(),
                    'type'  => $school->getType(),
                ],
                'user' => [
                    'id' => $teacher->getId(),
                    'username' => $teacher->getUsername(),
                    'email' => $teacher->getEmail(),
                ],
                'autologinUrl' => $autologinUrl
            ];

            return new JsonResponse($response);
        });
    }

    /**
     * <pre>
     * {
     *   "email": "email@beneylu.com",
     *   "password": "a secret password"
     *   "locale": "en_US",
     * }
     * </pre>
     *
     *
     * @ApiDoc(
     *  section="Utilisateurs",
     *  resource = false,
     *  description="Création d'un compte utilisateur - Uniquement depuis Beneylu Pay sécurisé par signature",
     *  statusCodes = {
     *      201 = "Ok - Compte créé",
     *      400 = "Erreur - A priori email déjà existant",
     *      403 = "Pas d'accès à l'inscription",
     *      404 = "Aucun email saisi",
     *      500 = "erreur serveur"
     *  }
     * )
     *
     * @Rest\Post("/create-account")
     *
     */
    public function postPayCreateAccountAction(Request $request)
    {
        if (!$this->container->getParameter('bns.enable_register')) {
            throw $this->createNotFoundException();
        }

        // security PAY/SPOT public signature check
        $this->get('bns_core.security_firewall.apikey_request_validator')->validateRequest($request);

        // normalize locale
        $locale = $this->get('bns.locale_manager')->getBestLocale($request->request->get('locale'));
        $request->request->set('locale', $locale);

        $form = $this->get('form.factory')->createNamedBuilder('', 'form', null, [
            'csrf_protection' => false
        ])
            ->add('email', 'email')
            ->add('password', 'password')
            ->add('locale', 'available_locale')
//            ->add('country', 'available_country')
            ->add('origin', 'text')
            ->getForm();

        $form->handleRequest($request);
        if ($form->isValid()) {
            $translator = $this->get('translator');
            $data = $form->getData();
            $locale = $data['locale'];
            // check user email exist
            $exists = $this->get('bns.user_manager')->getUserByEmail($data['email']);
            if ($exists) {
                return new JsonResponse([
                    'code' => Codes::HTTP_BAD_REQUEST,
                    'error_code' => 'email_exist',
                ], Codes::HTTP_BAD_REQUEST);
            }

            // check registration step
            // Create User With Api call
            $teacher = $this->get('bns.user_manager')->createUser([
                'first_name' => 'firstname',
                'last_name' => 'lastname',
                'lang' => $locale,
                'email' => $data['email'],
                'email_validated' => false,
                'plain_password' => $data['password'],
            ], false);

            // update locale user data
            $teacher->setRegistrationStep(1);
            $teacher->setRegisterOrigin(isset($data['origin']) ? strtoupper($data['origin']) : 'PAY');
            $teacher->save();

            // set user has teacher on group 1
            $this->get('bns.role_manager')->setGroupTypeRoleFromType('TEACHER')->assignRole($teacher, 1);
/*
            // Create School
            $school = $this->get('bns.group_manager')->createGroup([
                'type' => 'SCHOOL',
                'label' => $translator->trans('LABEL_MY_SCHOOL', array(), 'USER', $locale),
                'lang' => $locale,
                'group_parent_id' => 1,
            ]);

            if (!$school) {
                return new JsonResponse([
                    'code' => Codes::HTTP_INTERNAL_SERVER_ERROR,
                    'error_code' => 'school_creation_failed',
                    'message' => $translator->trans("ERROR_CANT_CREATE_SCHOOL", [], "USER", $locale)
                ], Codes::HTTP_INTERNAL_SERVER_ERROR);
            }
            $school->setCountry($data['country']);
            $school->save();

            // Create Classroom
            $classroomManager = $this->get('bns.classroom_manager');
            $classroom = $this->get('bns.group_manager')->createGroup(array(
                'type' => 'CLASSROOM',
                'label' => $translator->trans('LABEL_MY_CLASSROOM', array(), 'USER', $locale),
                'lang' => $locale,
                'group_parent_id' => $school->getId(),
                'validated' => true,
            ));

            if (!$classroom) {
                return new JsonResponse([
                    'code' => Codes::HTTP_INTERNAL_SERVER_ERROR,
                    'error_code' => 'classroom_creation_failed',
                    'message' => $translator->trans("ERROR_CANT_CREATE_SCHOOL", array(), "USER", $locale)
                ], Codes::HTTP_INTERNAL_SERVER_ERROR);
            }
            $classroom->setAttribute('CURRENT_YEAR', $this->container->getParameter('registration.current_year'));
            $classroom->setCountry($school->getCountry());
            $classroom->save();

            // Add user to classroom
            $classroomManager->setClassroom($classroom);
            $classroomManager->assignTeacher($teacher);

            // Handler sponsorship
            $classroomManager->sponsorshipAfterRegistration($teacher);
*/
            // Autoconnect URL
            $autologinToken = $this->get('bns.user_manager')->getAutologinToken(
                $teacher,
                $this->getParameter('security.oauth.client_id'),
                $this->generateUrl('home', array(), UrlGenerator::ABSOLUTE_URL),
                43200 // 12H pour le token de connexion
            );

            $autologinUrl = $this->getParameter('oauth_host') . '/registration/autologin/' . $autologinToken;

            $response = [
                /*
                'groups' => [
                    [
                        'id' => $school->getId(),
                        'label' => $school->getLabel(),
                        'type' => $school->getType(),
                        'parent_id' => 1,
                    ],
                    [
                        'id' => $classroom->getId(),
                        'label' => $classroom->getLabel(),
                        'type' => $classroom->getType(),
                        'parent_id' => $school->getId()
                    ],
                ],*/
                'user' => [
                    'id' => $teacher->getId(),
                    'username' => $teacher->getUsername(),
                    'email' => $teacher->getEmail(),
                ],
                'autologinUrl' => $autologinUrl
            ];

            return new JsonResponse($response);
        }

        return View::create($form, Response::HTTP_BAD_REQUEST);
    }

    /**
     * create a new school and new classroom
     * <pre>
     * {
     *   "userId": 42,
     *   "school": true
     *   "classroom": true
     *   "spotCountry": "FR"
     * }
     * </pre>
     * create a new classroom
     * <pre>
     * {
     *   "userId": 42,
     *   "school": 127
     *   "classroom": true
     *   "spotCountry": "FR"
     * }
     * </pre>
     * create a new school
     * <pre>
     * {
     *   "userId": 42,
     *   "school": true
     *   "classroom": false
     *   "spotCountry": "FR"
     * }
     * </pre>
     *
     * @ApiDoc(
     *  section="Utilisateurs",
     *  resource = false,
     *  description="Creation de groupes (classe et/ou école) - Uniquement depuis Beneylu Pay sécurisé par signature",
     *  statusCodes = {
     *      200 = "Ok",
     *  }
     * )
     *
     * @Rest\Post("/create-groups")
     *
     */
    public function postPayCreateGroupsAction(Request $request)
    {
        if (!$this->container->getParameter('bns.enable_register')) {
            throw $this->createNotFoundException();
        }

        // security PAY/SPOT public signature check
        $this->get('bns_core.security_firewall.apikey_request_validator')->validateRequest($request);

        $form = $this->get('form.factory')->createNamedBuilder('', 'form', null, [
            'csrf_protection' => false
        ])
            ->add('userId', 'text')
            ->add('classroom', 'checkbox')
            ->add('school', 'checkbox')
            ->add('schoolId', 'text')
            ->add('spotCountry', 'available_country')
            ->getForm();

        $form->handleRequest($request);
        if ($form->isValid()) {
            $translator = $this->get('translator');
            $data = $form->getData();
            // check user email exist
            $user = UserQuery::create()->findPk($data['userId']);
            if (!$user) {
                return new JsonResponse([
                    'code' => Codes::HTTP_NOT_FOUND,
                ], Codes::HTTP_NOT_FOUND);
            }

            $locale = $user->getLang();
            $school = false;
            $classroom = false;
            // boolean true normalized has 1
            if ($data['school']) {
                // Create School
                $school = $this->get('bns.group_manager')->createGroup([
                    'type' => 'SCHOOL',
                    'label' => $translator->trans('LABEL_MY_SCHOOL', array(), 'USER', $user->getLang()),
                    'lang' => $locale,
                    'group_parent_id' => 1,
                ]);
                if (!$school) {
                    return new JsonResponse([
                        'code' => Codes::HTTP_INTERNAL_SERVER_ERROR,
                        'error_code' => 'school_creation_failed',
                        'message' => $translator->trans("ERROR_CANT_CREATE_SCHOOL", [], "USER", $locale)
                    ], Codes::HTTP_INTERNAL_SERVER_ERROR);
                }
                $school->setSpotCountry($data['spotCountry']);
                $school->save();
            } elseif ($data['schoolId']) {
                $school = GroupQuery::create()
                    ->filterByArchived(false)
                    ->findPk((int)$data['schoolId']);

                if ($school && !in_array($user->getId(), $this->get('bns.group_manager')->setGroup($school)->getUsersIds())) {
                    return new JsonResponse([
                        'code' => Codes::HTTP_BAD_REQUEST,
                        'message' => 'user not in this group'
                    ], Codes::HTTP_BAD_REQUEST);
                }
            }
            if (!$school) {
                return new JsonResponse([
                    'code' => Codes::HTTP_NOT_FOUND,
                    'message' => 'school not found'
                ], Codes::HTTP_NOT_FOUND);
            }

            if ($data['classroom']) {
                // Create Classroom
                $classroomManager = $this->get('bns.classroom_manager');
                $classroom = $this->get('bns.group_manager')->createGroup(array(
                    'type' => 'CLASSROOM',
                    'label' => $translator->trans('LABEL_MY_CLASSROOM', array(), 'USER', $locale),
                    'lang' => $locale,
                    'group_parent_id' => $school->getId(),
                    'validated' => true,
                ));

                if (!$classroom) {
                    return new JsonResponse([
                        'code' => Codes::HTTP_INTERNAL_SERVER_ERROR,
                        'error_code' => 'classroom_creation_failed',
                        'message' => $translator->trans("ERROR_CANT_CREATE_SCHOOL", array(), "USER", $locale)
                    ], Codes::HTTP_INTERNAL_SERVER_ERROR);
                }
                $classroom->setAttribute('CURRENT_YEAR', $this->container->getParameter('registration.current_year'));
                $classroom->setSpotCountry($school->getSpotCountry());
                $classroom->save();

                // Add user to classroom
                $classroomManager->setClassroom($classroom);
                $classroomManager->assignTeacher($user);
            } elseif ($data['school']) {
                $this->get('bns.role_manager')->setGroupTypeRoleFromType('TEACHER')->assignRole($user, $school->getId());
            }

            $response = [
                'groups' => [
                    [
                        'id' => $school->getId(),
                        'label' => $school->getLabel(),
                        'type' => $school->getType(),
                        'parent_id' => 1,
                    ],
                ],
            ];
            if ($classroom) {
                $response['groups'][] = [
                    'id' => $classroom->getId(),
                    'label' => $classroom->getLabel(),
                    'type' => $classroom->getType(),
                    'parent_id' => $school->getId()
                ];
            }

            return new JsonResponse($response);
        }

        return View::create($form, Response::HTTP_BAD_REQUEST);
    }

    /**
     * @ApiDoc(
     *  section="Users",
     *  description="Updates a user",
     * )
     *
     * @Rest\Patch("/{id}", requirements={"id"="\d+"})
     * @Rest\View()
     */
    public function patchAction (User $user, Request $request)
    {
        $targetUserGroupIds = $this->get('bns.user_manager')->getGroupsIdsUserBelong();
        $currentUserManageableGroupIds = $this->get('bns.right_manager')->getGroupIdsWherePermission('CLASSROOM_ACCESS_BACK');

        if (!count(array_intersect($targetUserGroupIds, $currentUserManageableGroupIds))) {
            throw $this->createAccessDeniedException('User is not manageable');
        }

        return $this->restForm(new ApiUserType(), $user, [
            'csrf_protection' => false, // TODO the right way
        ], null, function ($user) use ($request) {
            $user->save();

            return $user;
        });
    }
}
