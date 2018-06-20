<?php

namespace BNS\App\ProfileBundle\Controller;

use BNS\App\AdminBundle\Exception\InvalidCredentialException;
use BNS\App\AdminBundle\Exception\InvalidUsersForMergeException;
use \BNS\App\CoreBundle\Annotation\Rights;
use \BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\ProfileBundle\Form\Type\AuthenticationType;
use BNS\App\UserBundle\AccountLink\Exception\InvalidDataException;
use BNS\App\UserBundle\Model\UserMerge;
use BNS\App\UserBundle\Model\UserMergePeer;
use \Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use \Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use \Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use \Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Ouarour El Mehdi <el-mehdi.ouarour@worldline.com>
 */
class BackMergeAccountsController extends Controller
{

    /**
     * @Route("/", name="BNSAppProfileBundle_back_merge_accounts", options={"exposed"=true})
     * @Template()
     */
    public function indexAction()
    {
        $this->checkIfAdult();
        $form = $this->createForm(new AuthenticationType());

        return array(
            'user' => $this->getUser(),
            'form' => $form->createView()
        );
    }

    /**
     * @Route("/authentifier-utilisateur-cible", name="BNSAppProfileBundle_back_authenticate_target_user")
     * @Template()
     * @param Request $request
     */
    public function authenticateTargetUserAction(Request $request)
    {
        $this->checkIfAdult();
        //Vérification de la requete Ajax et des paramètres
        $this->checkAjaxRequests($request);
        $login = $request->get('target_user_login');
        $password = $request->get('target_user_password');

        $userSource = UserQuery::create()
            ->filterByLogin($login, \Criteria::EQUAL)
            ->findOne();

        $mergeAccountManager = $this->get('bns.user.account_merge_manager');

        if (!$userSource || !$mergeAccountManager->isUserAuthenticated($login, $password)) {
            return new JsonResponse([
                'error' => Response::HTTP_BAD_REQUEST,
                'message' => $this->get('translator')->trans('ERROR_INVALID_CREDENTIAL', [], 'PROFILE')
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($userSource->getId() === $this->getUser()->getId()) {
            return new JsonResponse([
                'error' => Response::HTTP_BAD_REQUEST,
                'message' => $this->get('translator')->trans('ERROR_SAME_USER_FOR_MERGE', [], 'PROFILE')
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!$mergeAccountManager->canMergeUsers($userSource, $this->getUser())) {
            return new JsonResponse([
                'error' => Response::HTTP_BAD_REQUEST,
                'message' => $this->get('translator')->trans('ERROR_INVALID_USER_FOR_MERGE', [], 'PROFILE')
            ], Response::HTTP_BAD_REQUEST);
        }

        $groupTypes = GroupTypeQuery::create()
            ->filterBySimulateRole(false)
            ->find();
        //Libellé des tpes de groupe
        $groupTypesLabel = array();
        $labels = array(
            'ENVIRONMENT' => 'LABEL_TYPE_ENVIRONMENT'
            ,'CLASSROOM' => 'LABEL_TYPE_CLASSROOM'
            ,'SCHOOL' => 'LABEL_TYPE_SCHOOL'
            ,'TEAM' => 'LABEL_TYPE_TEAM'
            ,'PARTNERSHIP' => 'LABEL_TYPE_PARTNERSHIP'
        );

        foreach($groupTypes as $groupType) {
            $groupTypesLabel[$groupType->getId()] = isset($labels[$groupType->getType()]) ? $labels[$groupType->getType()] : 'LABEL_TYPE_GROUP';
        }

        $userManager = $this->get('bns.user_manager');
        //Enfants de l'utilisateur par groupe
        $userChildren = $userSource->getActiveChildren();

        $userSourceGroupRoles = $mergeAccountManager->getUserSourceGroups($userSource);

        $userChildrenGroup = [];
        foreach($userSourceGroupRoles as $group) {
            foreach($userChildren as $child) {
                if ($userManager->userIdAlreadeyBelongToGroupId($child->getId(), $group['group']['id'])) {
                    $userChildrenGroup[$group['group']['id']][] = $child->getFullName();
                }
            }
        }

        return array(
            'target_user_roles' => $userSourceGroupRoles,
            'group_types_label' => $groupTypesLabel,
            'user_children_group' => $userChildrenGroup,
            'user_source_email' => $userSource->getEmail(),
        );
    }

    /**
     * @Route("/valider", name="BNSAppProfileBundle_back_merge_validate")
     * @param Request $request
     * @Template()
     */
    public function validateAction(Request $request)
    {
        $this->checkIfAdult();
        //Vérification de la requete Ajax et des paramètres
        $this->checkAjaxRequests($request);
        $login = $request->get('target_user_login');
        $password = $request->get('target_user_password');

        $userSource = UserQuery::create()
            ->filterByLogin($login, \Criteria::EQUAL)
            ->findOne();

        $mergeAccountManager = $this->get('bns.user.account_merge_manager');

        if (!$userSource || !$mergeAccountManager->isUserAuthenticated($login, $password)) {
            return new JsonResponse([
                'error' => Response::HTTP_BAD_REQUEST,
                'message' => $this->get('translator')->trans('ERROR_INVALID_CREDENTIAL', [], 'PROFILE')
            ], Response::HTTP_BAD_REQUEST);
        }

        $mergeEmail = 'false' !== (string)$request->get('merge_account_mail');
        $notify = 'false' !== (string)$request->get('merge_account_notification');

        if ($this->get('bns.user.account_merge_manager')->createMergeRequest($userSource, $this->getUser(), $mergeEmail, $notify)) {
            $this->get('session')->getFlashBag()->add('success',
                $this->get('translator')->trans('MERGE_ACCOUNT_TO_TREAT', array(), 'PROFILE')
            );
        } else {
            $this->get('session')->getFlashBag()->add('error',
                $this->get('translator')->trans('MERGE_ACCOUNT_FAIL', array(), 'PROFILE')
            );
        }

        return [];
    }

    /**
     * @return Response
     */
    public function sidebarAction()
    {
        if ($this->checkIfAdult($this->getUser(true))) {
            return $this->render('BNSAppProfileBundle:BackMergeAccounts:sidebar.html.twig');
        }

        return new Response();
    }

    /**
     * Check si l'utilisateur courant est un adulte
     * @param Boolean $sidebar
     *
     * @return Boolean
     *
     * @throws AccessDeniedHttpException
     */
    public function checkIfAdult($sidebar = false)
    {
        $user = $this->getUser();
        $userManager = $this->get('bns.user_manager')->setUser($user);
        $isAdult = $userManager->isAdult();

        if(! $sidebar) {
            // Check si l'utilisateur est adulte
            $this->get('bns.right_manager')->forbidIf(! $isAdult);
        }
        return $isAdult;
    }

    /**
     * Check si c'est un POST & Ajax
     * @param Request $request
     *
     * @throws NotFoundHttpException
     */
    public function checkAjaxRequests(Request $request)
    {
        if (false === $request->isXmlHttpRequest() || 'POST' !== $request->getMethod()) {
            throw new NotFoundHttpException("This page except an AJAX & POST header");
        }
    }

    /**
     * Get la valeur d'un paramètre à partir de son nom
     * @param Request $request
     * @param Type $paramName
     *
     * @throws InvalidArgumentException
     */
    public function getParameterByName(Request $request, $paramName)
    {
        //Récupération du login de l'utilisateur cible
        $paramValue = $request->get($paramName, null);

        if (null == $paramValue) {
            throw new \InvalidArgumentException('The parameter "'. $paramName .'" is missing !');
        }

        return $paramValue;
    }
}
