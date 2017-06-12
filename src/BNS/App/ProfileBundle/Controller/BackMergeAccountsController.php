<?php

namespace BNS\App\ProfileBundle\Controller;

use \BNS\App\CoreBundle\Annotation\Rights;
use \BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\ProfileBundle\Form\Type\AuthenticationType;
use \Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use \Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use \Symfony\Bundle\FrameworkBundle\Controller\Controller;
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
        $targetUsername = $this->getParameterByName($request, 'target_user_login');
        $targetPassword = $this->getParameterByName($request, 'target_user_password');
        $targetUser = \BNS\App\CoreBundle\Model\UserQuery::create()
            ->filterByLogin($targetUsername, \Criteria::EQUAL)
            ->findOne();
        //check si l'utilisateur est adulte
        $this->checkIfAdult();
        //Récupération des rôles de l'utilisateur distant
        $userManager = $this->get('bns.user_manager');
        $targetUserRoles = $userManager->canMergeUser($this->getUser(), $targetUsername, $targetPassword);
        $groupTypes = \BNS\App\CoreBundle\Model\GroupTypeQuery::create()
            ->filterBySimulateRole(0)
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

        //Enfants de l'utilisateur par groupe
        $userChildrens = $userManager->getUserChildren($targetUser);
        $userChildrenGroup = array();

        if(null !=$targetUserRoles) {
            foreach($targetUserRoles as $group) {
                foreach($userChildrens as $children) {
                    if($userManager->userIdAlreadeyBelongToGroupId($children->getId(), $group['group']['id'])) {
                        $userChildrenGroup[$group['group']['id']][] = $children->getFullName();
                    }
                }
            }
        }

        return array(
            'target_user_roles' => $targetUserRoles,
            'group_types_label' => $groupTypesLabel,
            'user_children_group' => $userChildrenGroup
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
        $targetUsername = $this->getParameterByName($request, 'target_user_login');
        $targetPassword = $this->getParameterByName($request, 'target_user_password');
        $targetUser = \BNS\App\CoreBundle\Model\UserQuery::create()
            ->filterByLogin($targetUsername, \Criteria::EQUAL)
            ->findOne();
        $askerUser = $this->getUser();
        //Récupération des rôles de l'utilisateur distant
        $userManager = $this->get('bns.user_manager');
        $targetUserRoles = $userManager->canMergeUser($askerUser, $targetUsername, $targetPassword);
        $mergeResult=false;

        if(null != $targetUserRoles) {
            $mergeResult = $userManager->mergeUsers($askerUser->getLogin(), $targetUsername);
        }

        if($mergeResult) {
            $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('ACCOUNTS_MERGED', array(), 'PROFILE') .
                $askerUser->getFirstName() . " " . $askerUser->getLastName() . " et "
                . $targetUser->getFirstName() . " " . $targetUser->getLastName());
        }

        return array();
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
