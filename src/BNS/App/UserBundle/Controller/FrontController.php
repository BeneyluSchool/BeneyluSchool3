<?php
namespace BNS\App\UserBundle\Controller;

use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\User;
use BNS\App\NotificationBundle\Model\NotificationQuery;
use BNS\App\NotificationBundle\Model\NotificationSettings;
use BNS\App\NotificationBundle\Model\NotificationSettingsQuery;
use BNS\App\UserBundle\Form\Type\CGUType;
use BNS\App\UserBundle\Form\Type\NameEmailType;
use BNS\App\UserBundle\Form\Type\PolicyType;

use BNS\App\UserBundle\Form\Type\UserRegistrationStep1Type;
use BNS\App\UserBundle\Form\Type\UserRegistrationStep2Type;
use BNS\App\UserBundle\Form\Type\UserRegistrationStep3Type;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Validator\Constraints\DateTime;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 *
 */
class FrontController extends Controller
{
    /**
     * @Route("/nom-email-notification", name="user_front_add_name_email")
     * @Template()
     */
    public function addNameEmailAction(Request $request)
    {
        $user = $this->getUser();

        $userManager = $this->get('bns.user_manager')->setUser($user);
        if (!$userManager->isAdult()) {
            return $this->redirect($this->generateUrl('home'));
        }
        $userGroups = $userManager->getGroupsUserBelong();

        $modules = array();
        foreach ($userGroups as $group) {
            foreach ($group->getGroupType()->getModules() as $module) {
                if ('NOTIFICATION' !== $module->getUniqueName()) {
                    $modules[$module->getUniqueName()] = $module;
                }
            }
        }

        $data = array('email' => $user->getEmail()
                    , 'modules' => array());
        if ($request->isMethod('get')) {
            //Pour ne pas afficher de nom sur lme champ comme ça on force l'utilisateur à saisir son vrai nom
            $data['first_name'] = null;
            $data['last_name'] = null;
            $data['civility'] = $user->getGender();
            $form = $this->createForm(new NameEmailType($userManager), $data, array('modules' => $modules));

        } elseif ($request->isMethod('post')) {
            $data['first_name'] = $user->getFirstName();
            $data['last_name'] = $user->getLastName();

            $form = $this->createForm(new NameEmailType($userManager), $data, array('modules' => $modules));

            $form->bind($request);
            if ($form->isValid()) {
                $data = $form->getData();
                $user->setEmail($data['email']);
                $user->setFirstName($data['first_name']);
                $user->setLastName($data['last_name']);
                $user->setGender($data['civility']);
                $user->save();

                $moduleEnable = $data['modules'];

                $modulesChosenUniqueNames = array();

                foreach($moduleEnable as $moduleChosen)
                {
                    $modulesChosenUniqueNames[] = $moduleChosen->getUniqueName();
                }

                $moduleSaved = array();

                $userManager->updateUser($user);
                foreach ($userGroups as $group) {
                    foreach ($group->getGroupType()->getModules() as $module) {
                        if ('NOTIFICATION' !== $module->getUniqueName()) {
                            /*if (!$module->isContextable() && in_array($module->getUniqueName(), $moduleSaved)) {
                                continue;
                            } elseif(!$module->isContextable()) {
                                $moduleSaved[] = $module->getUniqueName();
                            }*/
                            $query = NotificationSettingsQuery::create()
                                ->filterByUser($user)
                                ->_if(!$module->isContextable())
                                ->filterByContextGroupId(null, \Criteria::ISNULL)
                                ->_else()
                                ->filterByContextGroupId($group->getId())
                                ->_endif()
                                ->filterByModuleUniqueName($module->getUniqueName())
                                ->filterByNotificationEngine('EMAIL');

                            if (in_array($module->getUniqueName(), $modulesChosenUniqueNames)) {
                                $query->delete();
                            } else {
                                $notification  = $query->findOneOrCreate();
                                $notification->save();
                            }
                        }
                    }
                }
                if (!($url = $userManager->onLogon())) {
                    $url = $this->generateUrl('home');
                }
                return $this->redirect($url);
            }
        }

        return array(
                'form' => $form->createView(),
        );
    }

    /**
     * @Route("/email-notification/passer", name="user_front_skip_add_email")
     * @Template()
     */
    public function skipAddEmailAction()
    {
        $userManager = $this->get('bns.user_manager');

        if (!($url = $userManager->onLogon('notification'))) {
            $url = $this->generateUrl('home');
        }

        return $this->redirect($url);
    }

    /**
     * @Route("/charte-utilisation", name="user_front_policy_validate")
     * @Template()
     * @param Request $request
     */
    public function policyValidateAction(Request $request)
    {
        $user = $this->getUser();
        $userManager = $this->get('bns.user_manager');

        $form = $this->createForm(new PolicyType(), $user, array('is_child' => $userManager->isChild()));
        if ($request->isMethod('post')) {
            $form->handleRequest($request);
            if ($form->isValid()) {
                $user->save();
                $request->getSession()->remove('need_policy_validation');

                if (!($url = $userManager->onLogon())) {
                    $url = $this->generateUrl('home');
                }

                return $this->redirect($url);
            }
        }

        $policyUrl = null;
        $mainRole = $userManager->getMainRole();

        foreach ($userManager->getGroupsUserBelong() as $group) {
            $groupManager = $this->container->get('bns.group_manager');
            $groupManager->setGroup($group);
            if ('pupil' == $mainRole) {
                $policyUrl = $groupManager->getAttribute('POLICY_URL_CHILD', false) ?: $groupManager->getAttribute('POLICY_URL_OTHER', null);
            } elseif ('parent' == $mainRole) {
                $policyUrl = $groupManager->getAttribute('POLICY_URL_PARENT', false) ?: $groupManager->getAttribute('POLICY_URL_OTHER', null);
            } elseif ('teacher' == $mainRole || 'director' == $mainRole){
                $policyUrl = $groupManager->getAttribute('POLICY_URL_TEACHER', false) ?: $groupManager->getAttribute('POLICY_URL_OTHER', null);
            } elseif ('city_referent' == $mainRole ){
                $policyUrl = $groupManager->getAttribute('POLICY_URL_CITY_REFERENT', false) ?: $groupManager->getAttribute('POLICY_URL_OTHER', null);
            } else {
                $policyUrl = $groupManager->getAttribute('POLICY_URL_OTHER', null);
            }
            if (null !== $policyUrl) {
                break;
            }
        }

        return array('form' => $form->createView(), 'policyUrl' => urlencode($policyUrl));
    }

    /**
     * @Route("/saisie-inscription/{step}", name="user_front_registration_step")
     * @param Request $request
     * @param $step
     * @return Response
     */
    public function registrationStepAction(Request $request, $step)
    {
        /** @var User $user */
        $user = $this->get('bns.right_manager')->getUserSession();
        if (!$user->getRegistrationStep() && $step == '1') {
            return $this->redirect($this->generateUrl('home'));
        }
        if ($user->getRegistrationStep() && $user->getRegistrationStep() != $step) {
            return $this->redirect($this->generateUrl('user_front_registration_step', array('step' => $user->getRegistrationStep())));
        }

        // make sure user has a current group
        $classroom = $this->get('bns.right_manager')->getUserManager()->getGroupsUserBelong('CLASSROOM')->getFirst();
        $this->get('bns.group_manager')->setGroup($classroom);

        switch ($step) {
            case 1:
                return $this->doRegistrationStep1($request, $user);
            case 2:
                return $this->doRegistrationStep2($request, $user);
            case 3:
                return $this->doRegistrationStep3($request, $user);
            case 4:
                return $this->doRegistrationStep4($request, $user);
            case 5:
                return $this->doRegistrationStep5($request, $user);
            default:
                throw new NotFoundHttpException();
        }
    }

    /**
     * @Route("/cgu", name="user_front_cgu_validate")
     * @Template()
     * @param Request $request
     */
    public function cguValidateAction(Request $request)
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($user->isChild()) {
            return $this->redirect($this->generateUrl('home'));
        }

        $userManager = $this->get('bns.user_manager');
        $user->setCguValidation(false);
        $form = $this->createForm(new CGUType(), $user);

        $cguUrl = null;
        // store if we use default url (french one)
        $default = null;
        $cguVersion = null;
        $cguDate = null;
        $groupManager = $this->container->get('bns.group_manager');
        foreach ($userManager->getGroupsUserBelong() as $group) {
            if ($group->getType() === 'ENVIRONMENT') {
                continue;
            }
            /** @var Group $env */
            $env = $groupManager->getEnvironment($group);
            if ($env) {
                $cguEnabled = $groupManager->getAttributeStrict($env, 'CGU_ENABLED', null);
                if ($cguEnabled) {
                    $cguVersion = $groupManager->getAttributeStrict($env, 'CGU_VERSION', null);
                    $cguUrlData = $this->get('bns.group_manager')->getCguUrl($env, $user);
                    if ($cguUrlData) {
                        $cguUrl = $cguUrlData['url'];
                        $default = $cguUrlData['default'];
                    }
                    $date = $groupManager->getAttributeStrict($env, 'CGU_DATE');
                    try {
                        $cguDate = $date ? new \DateTime($date) : null;
                    } catch (\Exception $e) {
                        $this->get('logger')->error(sprintf('cgu invalid date "%s" : "%s"', $date, $e->getMessage()), ['date' => $date, 'error' => $e]);
                    }
                }
                if (false === $cguEnabled || true === $cguEnabled && $cguVersion) {
                    break;
                }
            }
        }
        if (!$cguVersion || !$cguUrl) {
            // config issue skip for this session
            $request->getSession()->remove('need_cgu_validation');
            if (!($url = $userManager->onLogon())) {
                $url = $this->generateUrl('home');
            }

            return $this->redirect($url);
        }

        if ($request->isMethod('post')) {
            $form->handleRequest($request);
            if ($form->isValid()) {
                $user->setCguVersion($cguVersion);
                $user->setCguValidationDate(date('Y-m-d'));
                $user->save();
                $request->getSession()->remove('need_cgu_validation');
                if (!($url = $userManager->onLogon())) {
                    $url = $this->generateUrl('home');
                }

                return $this->redirect($url);
            }
        }

        return [
            'form' => $form->createView(),
            'cguUrl' =>  $cguUrl,
            'default' => $default,
            'cguVersion' => $cguVersion,
            'cguDate' => $cguDate
        ];
    }

    protected function doRegistrationStep1(Request $request, User $user)
    {
        $data = [
            'first_name' => null,
            'last_name' => null,
            'civility' => $user->getGender(),
            'lang' =>  $user->getLang(),
            'cgu' => false,
        ];

        // TODO create classroom/school if user don't have one for obvious reason

        $cguVersion = null;
        $cguUrl = null;
        $cguEnabled = false;
//        foreach ($this->get('bns.user_manager')->getGroupsUserBelong() as $group) {
//            $groupManager = $this->container->get('bns.group_manager');
//            $groupManager->setGroup($group);
//            $cguEnabled = $groupManager->getAttribute('CGU_ENABLED', null);
//            $cguVersion = $groupManager->getAttribute('CGU_VERSION', null);
//            $cguUrl = $this->get('bns.group_manager')->getCguUrl($group, $user)['url'];
//
//            if (null !== $cguUrl) {
//                break;
//            }
//        }

        $form = $this->createForm(new UserRegistrationStep1Type(), $data, ['cgu_enabled' => $cguEnabled]);
        $form->handleRequest($request);
        if ($form->isValid()) {
            $data = $form->getData();

            $classroom = $this->getRegistrationClassroom();
            $this->get('bns.group_manager')->setGroup($classroom)->clearGroupCache();
            $school = $this->get('bns.group_manager')->setGroup($classroom)->getParent();

            $classroom->setCountry($data['country']);
            $this->get('bns.group_manager')->setGroup($classroom)->updateGroup([]);
            $school->setCountry($data['country']);
            $this->get('bns.group_manager')->setGroup($school)->updateGroup([]);

            $user->setCountry($data['country']);
            $user->setFirstName($data['first_name']);
            $user->setLastName($data['last_name']);
            $user->setGender($data['civility']);
            $user->setLang($data['lang']);

            if ($cguEnabled) {
                $user->setCguValidation($data['cgu']);
                $user->setCguValidationDate(date('Y-m-d'));
                $user->setCguVersion($cguVersion);
            }

            $this->get('bns.user_manager')->updateUser($user, null, true);
            $user->setRegistrationStep(2);
            $user->save();
            $this->get('bns.right_manager')->setLocale($user->getLang());

//            $this->get('bns.user_manager')->sendLoginEmail($user);
            $this->get('session')->set('identify_instant', true);

            $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('STEP_ONE_SUCCESS', array(), 'USER'));

            return $this->autologUser($user);
        }

        return $this->render('BNSAppUserBundle:Front:registrationStep1.html.twig', [
            'form' => $form->createView(),
            'cguUrl' => $cguUrl,
            'cguEnabled' => $cguEnabled
        ]);
    }

    protected function doRegistrationStep2(Request $request, User $user)
    {
        $classroom = $this->getRegistrationClassroom();
        $data = [
            'label' => $user->getRegistrationStep() ? null : $classroom->getLabel(),
            'level' => $user->getRegistrationStep() ? [] : $classroom->getAttribute('LEVEL'),
        ];
        $form = $this->createForm(new UserRegistrationStep2Type(), $data, [
            'locale' => $user->getLang(),
        ]);
        $form->handleRequest($request);
        if ($form->get('skip')->isClicked()) {
            if ($user->getRegistrationStep()) {
                $user->setRegistrationStep(4)->save();

                return $this->redirect($this->generateUrl('user_front_registration_step', ['step' => 4]));
            } else {
                return $this->redirect($this->generateUrl('home'));
            }
        } else if ($form->isValid()) {
            $data = $form->getData();
            $classroom->setAttribute('LEVEL', $data['level']);
            // update group name app + auth
            $this->get('bns.group_manager')->setGroup($classroom)->updateGroup(array('label' => $data['label']));
            if ($user->getRegistrationStep()) {
                $user->setRegistrationStep(3);
                $user->save();
            }
            $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('STEP_TWO_SUCCESS', array(), 'USER'));

            return $this->redirect($this->generateUrl('user_front_registration_step', array('step' => 3)));
        }

        return $this->render('BNSAppUserBundle:Front:registrationStep2.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    protected function doRegistrationStep3(Request $request, User $user)
    {
        $classroom = $this->getRegistrationClassroom();
        $school = $this->get('bns.group_manager')->setGroup($classroom)->getParent();
        $data = [
            'label' => $user->getRegistrationStep() ? null : $school->getLabel(),
            'zipcode' => $user->getRegistrationStep() ? null : $school->getAttribute('ZIPCODE'),
            'city' => $user->getRegistrationStep() ? null : $school->getAttribute('CITY'),
        ];
        $form = $this->createForm(new UserRegistrationStep3Type(), $data);
        $form->handleRequest($request);
        if ($form->get('skip')->isClicked()) {
            if ($user->getRegistrationStep()) {
                $user->setRegistrationStep(4)->save();

                return $this->redirect($this->generateUrl('user_front_registration_step', ['step' => 4]));
            } else {
                return $this->redirect($this->generateUrl('home'));
            }

        } else if ($form->isValid()) {
            $data = $form->getData();

            /** @var Group $classroom */
            $classroom->setAttribute('SCHOOL_LABEL', $data['label']);
            $classroom->setAttribute('ZIPCODE', $data['zipcode']);
            $classroom->setAttribute('CITY', $data['city']);
            $classroom->setLang($user->getLang());
            $this->get('bns.group_manager')->setGroup($classroom)->updateGroup([]);

            $school = $this->get('bns.group_manager')->setGroup($classroom)->getParent();
            if ($school && $school->getType() === 'SCHOOL') {
                $school->setAttribute('NAME', $data['label']);
                $school->setAttribute('ZIPCODE', $data['zipcode']);
                $school->setAttribute('CITY', $data['city']);
                $this->get('bns.group_manager')->setGroup($school)->updateGroup([]);
            }


            if ($user->getRegistrationStep()) {
                $user->setRegistrationStep(4);
                $user->save();
                $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('STEP_THREE_SUCCESS', array(), 'USER'));
            } else {
                $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('FLASH_SAVE_SHCOOL_DETAILS_SUCCESS', array(), 'USER'));
            }
            return $this->redirect($this->generateUrl('user_front_registration_step', array('step' => 4)));
        }

        return $this->render('BNSAppUserBundle:Front:registrationStep3.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    protected function doRegistrationStep4(Request $request, User $user)
    {
        $form = $this->createFormBuilder(null, ['translation_domain' => 'USER'])
            ->add('skip', 'submit')
            ->add('pupils', 'textarea')
            ->getForm()
        ;
        $form->handleRequest($request);
        if ($form->isValid()) {
            $pupils = $form->get('pupils')->getData();
            $pupilsList = $this->get('bns.user_manager')->textToUserArray($pupils);
            if (null != $pupilsList) {
                $result = $this->get('bns.classroom_manager')
                    ->setClassroom($this->get('bns.right_manager')->getCurrentGroup())
                    ->importPupilFromTextarea($pupilsList);

                if ($result['success_insertion_count'] == $result['user_count']) {
                    $msgType = 'toast-success';
                    $msg = $this->get('translator')->trans('FLASH_PROCESS_IMPORT_SUCCESS', array('%user%' => $result['user_count']), "CLASSROOM");
                } else {
                    $msgType = 'toast-error';
                    $msg =  $this->get('translator')->trans('FLASH_PROCESS_IMPORT_ERROR', array(
                        '%resultSuccess%' => $result['success_insertion_count'],
                        '%skiped%' => $result['skiped_count'],
                    ), "CLASSROOM");
                }
                $request->getSession()->getFlashBag()->add($msgType, $msg);
            }

            if ($user->getRegistrationStep()) {
                $user->setRegistrationStep(5);
                $user->save();

                return $this->redirect($this->generateUrl('user_front_registration_step', array('step' => 5)));
            } else {
                return $this->redirect($this->generateUrl('BNSAppClassroomBundle_front'));
            }
        }

        return $this->render('BNSAppUserBundle:Front:registrationStep4.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    protected function doRegistrationStep5(Request $request, User $user)
    {
        $user->setRegistrationStep(null)
            ->save()
        ;
        //To had doc link in welcome modal
        if (array_key_exists($request->getLocale(), $this->getParameter('bns_homepage_links'))) {
            $localLink = array_merge($this->getParameter('bns_homepage_links')['en'], $this->getParameter('bns_homepage_links')[$request->getLocale()]);
            $docLink = $localLink['child_account_guide'];
        } else {
            $localLink = $this->getParameter('bns_homepage_links')['en'];
            $docLink = $localLink['child_account_guide'];
        }

        return $this->render('BNSAppUserBundle:Front:registrationStep5.html.twig', [
            'doc_link' => $docLink,
        ]);
    }

    protected function autologUser(User $user)
    {
        $autologinToken = $this->get('bns.user_manager')->getAutologinToken(
            $user,
            $this->getParameter('security.oauth.client_id'),
            $this->generateUrl('home', array(), UrlGenerator::ABSOLUTE_URL)
        );
        $autologinUrl = $this->getParameter('oauth_host') . '/registration/autologin/' . $autologinToken;

        return $this->redirect($autologinUrl);
    }

    /**
     * Gets the classroom used in registration form: the current classroom user is in, or the first classroom user has.
     *
     * @return Group
     */
    protected function getRegistrationClassroom()
    {
        $classrooms = $this->get('bns.right_manager')->getUserManager()->getGroupsUserBelong('CLASSROOM');
        $currentGroup = $this->get('bns.right_manager')->getCurrentGroup();

        if ($currentGroup) {
            foreach ($classrooms as $classroom) {
                if ($classroom->getId() === $currentGroup->getId()) {
                    return $classroom;
                }
            }
        }

        if (!$classrooms->getFirst()) {
            // bypass groups by right
            foreach ($this->get('bns.user_manager')->getGroupsWhereRole('TEACHER') as $group) {
                if ($group->getType() === 'CLASSROOM') {
                    return $group;
                }
            }
        }

        return $classrooms->getFirst();
    }

}
