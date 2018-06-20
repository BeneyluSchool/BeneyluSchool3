<?php

namespace BNS\App\ClassroomBundle\Controller;

use BNS\App\CoreBundle\Model\User;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\ClassroomBundle\Form\Model\EditTeamFormModel;
use BNS\App\ClassroomBundle\Form\Type\EditTeamType;
use BNS\App\CoreBundle\Annotation\Rights;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

/**
 * @author Eric Chau <eric.chau@pixel-cookers.com>
 */
class BackTeamController extends Controller
{

    /**
     * @Route("/", name="BNSAppClassroomBundle_back_team")
     * @Rights("CLASSROOM_ACCESS_BACK")
     * @Template()
     *
     * @param String $slug
     */
    public function indexAction()
    {
        $rightManager = $this->get('bns.right_manager');
        $currentGroup = $this->get('bns.right_manager')->getCurrentGroup();

        $hasGroupBoard = $this->get('bns.group_manager')->setGroup($currentGroup)->getProjectInfo('has_group_blackboard');

        // On vérifie si l'utilisateur a les droits d'accès
        $rightManager->forbidIf(!$rightManager->isInClassroomGroup());

        $classroomManager = $this->get('bns.classroom_manager');
        // On initialise le BNSClassroomManager avec la classe courante pour ensuite pouvoir récupérer toutes les équipes de la classe;
        $classroom = $rightManager->getCurrentGroup();
        $classroomManager->setClassroom($classroom);

        $groupManager = $this->get('bns.group_manager');
        $teams = $classroomManager->getTeams();
        // On boucle sur toutes les équipes de la classe pour injecter les utilisateurs dans chaque sous-groupe
        foreach ($teams as $key => $team) {
            if ($team->getAttribute('EXPIRATION_DATE') !== null && $team->getAttribute('EXPIRATION_DATE') !== '' && \DateTime::createFromFormat('Y-m-d', $team->getAttribute('EXPIRATION_DATE')) < new \DateTime('now')) {
                $this->deleteTeam($team->getSlug());
                unset($teams[$key]);
            }
            // On s'appuye sur le BNSGroupManager; on l'initialise avec l'équipe dont on souhaite récupérer les users
            $groupManager->setGroup($team);
            // Le paramètre $isLocale de la méthode getUsers() est setté à true pour récupérer des objets de type User (Propel)
            $team->setUsers($groupManager->getUsers(true));
        }

        return array(
            'classroom' => $classroom,
            'teams' => $teams,
            'message' => $this->get('translator')->trans('GROUP_CREATE_SUCCESS', array(), 'CLASSROOM'),
            'hasGroupBoard' => $hasGroupBoard
        );
    }

    /**
     * @Route("/nouvelle-equipe", name="BNSAppClassroomBundle_back_new_team", options={"expose"=true})
     * @Rights("CLASSROOM_ACCESS_BACK")
     * @Template("BNSAppClassroomBundle:BackTeam:team_block.html.twig")
     *
     * @param Request $request
     */
    public function newTeamAction(Request $request)
    {
        // AJAX?
        if (false === $request->isXmlHttpRequest() || 'POST' !== $request->getMethod()) {
            throw new NotFoundHttpException("This page except an AJAX & POST header");
        }

        $name = $request->get('team_name', null);
        if (null == $name) {
            throw new HttpException(500, 'Name given contains illegal value!');
        }

        $rightManager = $this->get('bns.right_manager');
        // Check des droits d'accès
        $rightManager->forbidIf(!$rightManager->isInClassroomGroup());

        $classroom = $rightManager->getCurrentGroup();
        $params = array(
            'label' => $name,
            'attributes' => array(),
            'group_parent_id' => $classroom->getId(),
            'lang' => $classroom->getLang()
        );
        $team = $this->get('bns.team_manager')->createTeam($params);
        $teamManager = $this->get('bns.team_manager');
        $teamManager->setTeam($team);

        //On ajoute le créateur de la team
        $teamManager->addUser($this->get('bns.user_manager')->getUser());
        $team->setUsers($teamManager->getUsers(true));
        //statistic action
        $this->get("stat.classroom")->createGroup();
//        $this->get('session')->getFlashBag()->add('success', "Le groupe a été créé avec succès.");

        return array(
            'team' => $team,
            'classroom' => $classroom
        );
    }

    /**
     * @Route("/supprimer-eleve/equipe", name="BNSAppClassroomBundle_back_team_remove_pupil", options={"expose"=true})
     * @Rights("CLASSROOM_ACCESS_BACK")
     *
     * @param Request $request
     * @throws NotFoundHttpException
     */
    public function removeUserFromTeamAction(Request $request)
    {
        // AJAX?
        if (false === $request->isXmlHttpRequest()) {
            throw new NotFoundHttpException();
        }

        $userIds = $request->get('user_ids');
        if (null == $userIds) {
            $userIds = array();
        }
        // On vérifie que les utilisateurs et l'équipe en question appartiennent bien à la classe
        $this->isCurrentActionValid($request->get('team_slug'), $userIds);

        $teamManager = $this->get('bns.team_manager');

        // Toutes les vérifications sont effectuées et passées avec succès, on procède maintenant à la suppression des utilisateurs dans l'équipe
        $userManager = $this->get('bns.user_manager');

        foreach ($userManager->retrieveUsersById($userIds) as $user) {
            $teamManager->removeUser($user);
        }

        return new Response(json_encode(true));
    }

    /**
     * @Route("/gestion-utilisateurs", name="BNSAppClassroomBundle_back_team_add_remove_users", options={"expose"=true})
     * @Rights("CLASSROOM_ACCESS_BACK")
     *
     * @param Request $request
     */
    public function addTeamUsersAction(Request $request)
    {
        // AJAX?
        if (false === $request->isXmlHttpRequest()) {
            throw new NotFoundHttpException();
        }

        $userIds = $request->get('user_ids');
        if (null == $userIds) {
            $userIds = array();
        }

        // On vérifie que les utilisateurs et l'équipe en question appartiennent bien à la classe
        $this->isCurrentActionValid($request->get('team_slug'), $userIds);

        $teamManager = $this->get('bns.team_manager');
        // L'action en cours est autorisé; on extrait les informations concernant les utilisateurs à supprimer et/ou à ajouter à l'équipe
        $currentUsersOfTeamId = array();
        foreach ($teamManager->getTeamUsers() as $teamUser) {
            if(isset($teamUser['user_id']))
            {
                $currentUsersOfTeamId[] = $teamUser['user_id'];
            }
        }

        $usersToAdd = array_diff($userIds, $currentUsersOfTeamId);
        if (0 == count($usersToAdd)) {
            return new Response(json_encode(false));
        }

        // Toutes les vérifications sont effectuées et passées avec succès, on procède maintenant à l'ajout des utilisateurs dans l'équipe

        $rightManager = $this->get('bns.right_manager');
        $currentGroupId = $rightManager->getCurrentGroup()->getId();

        $userManager = $this->get('bns.user_manager');
        $parentRole = GroupTypeQuery::create()->findOneByType('PARENT');
        /** @var User $user */
        foreach ($userManager->retrieveUsersById($usersToAdd) as $user) {
            $userManager->setUser($user);
            //On set comme rôle dans l'équipe le rôle qu'a l'utilisateur dans le groupe en cours
            $roles = $userManager->getRolesByGroup(true);
            $teamManager->addUser($user,isset($roles[$currentGroupId][0]) ? GroupTypeQuery::create()->findOneByType($roles[$currentGroupId][0]) : null);

            if ($user->isChild()) {
                $parents = $userManager->getUserParent($user);
                foreach ($parents as $parent) {
                    $teamManager->addUser($parent, $parentRole);
                }
            }
        }

        return new Response(json_encode(true));
    }

    private function isCurrentActionValid($teamSlug, array $userIdsToCheck)
    {
        $rightManager = $this->get('bns.right_manager');
        $classroomManager = $this->get('bns.classroom_manager');
        $teamManager = $this->get('bns.team_manager');

        // On vérifie si l'équipe à laquelle on souhaite ajouter des utilisateurs existe ou non
        $team = $teamManager->findBySlug($teamSlug);

        // On vérifie si l'équipe en question appartient à la classe de l'utilisateur courant
        $classroomManager->setClassroom($rightManager->getCurrentGroup());
        $rightManager->forbidIf(!$classroomManager->isOneOfMyTeams($team));

        // On vérifie que tous les id fournis en paramètre sont valides (que les utilisateurs font bien partis de la classe)
        $classroomUserIds = array();
        foreach ($classroomManager->getUsers(true) as $classroomUser) {
            $classroomUserIds[] = $classroomUser->getId();
        }

        foreach ($userIdsToCheck as $userId) {
            if (!in_array($userId, $classroomUserIds)) {
                // Si l'utilisateur ne fait pas partie de la classe, on arrête l'action en cours
                $this->get('bns.right_manager')->forbidIf(true);
            }
        }
    }

    /**
     * @Route("/editer/{slug}", name="BNSAppClassroomBundle_back_team_edit_team")
     * @Rights("CLASSROOM_ACCESS_BACK")
     * @Template("BNSAppClassroomBundle:BackTeam:team_edit_text_form.html.twig")
     *
     * @param unknown_type $slug
     * @throws NotFoundHttpException
     */
    public function editTeamAction($slug)
    {
        $rightManager = $this->get('bns.right_manager');
        $teamManager = $this->get('bns.team_manager');
        // Vérifie qu'il y a bien une équipe associé au slug fourni en paramètre
        $team = $teamManager->findBySlug($slug);

        // Vérifie que l'on a bien un droit d'accès à cette équipe de travail
        $classroomManager = $this->get('bns.classroom_manager');
        $classroom = $rightManager->getCurrentGroup();
        $classroomManager->setClassroom($classroom);
        $rightManager->forbidIf(!$classroomManager->isOneOfMyTeams($team));

        // On injecte les utilisateurs de l'équipe dans l'objet du modèle
        $team->setUsers($teamManager->getUsers(true));

        $form = $this->createForm(new EditTeamType(), new EditTeamFormModel($team));
        if ('POST' == $this->getRequest()->getMethod()) {
            $form->bind($this->getRequest());

            if ($form->isValid()) {
                $teamText = $form->getData();

                $teamText->save();

                return $this->redirect($this->generateUrl('BNSAppClassroomBundle_back_team_details',
                            array(
                            'slug' => $slug)));
            }
        }

        return array(
            'team' => $team,
            'classroom' => $classroom,
            'teachers' => $teamManager->getTeachers(),
            'pupils' => $teamManager->getPupils(),
            'parents' => $teamManager->getPupilsParents(),
            'form' => $form->createView()
        );
    }

    /**
     * @Route("/details/{slug}", name="BNSAppClassroomBundle_back_team_details")
     * @Rights("CLASSROOM_ACCESS_BACK")
     * @Template("BNSAppClassroomBundle:BackTeam:team_details.html.twig")
     *
     * @param unknown_type $slug
     * @throws NotFoundHttpException
     */
    public function teamDetailsAction($slug)
    {
        $rightManager = $this->get('bns.right_manager');
        $teamManager = $this->get('bns.team_manager');
        // Vérifie qu'il y a bien une équipe associé au slug fourni en paramètre
        $team = $teamManager->findBySlug($slug);

        // Vérifie que l'on a bien un droit d'accès à cette équipe de travail
        $classroomManager = $this->get('bns.classroom_manager');
        $classroom = $rightManager->getCurrentGroup();
        $classroomManager->setClassroom($classroom);
        $rightManager->forbidIf(!$classroomManager->isOneOfMyTeams($team));

        // On injecte les utilisateurs de l'équipe dans l'objet du modèle
        $team->setUsers($teamManager->getUsers(true));

        //liste des modules du groupe
        $activationRoles = GroupTypeQuery::create()->filterBySimulateRole(true)->orderByType(\Criteria::DESC)
            ->findByType(array('TEACHER', 'PUPIL', 'PARENT'));

        return array(
            'team' => $team,
            'classroom' => $classroom,
            'teachers' => $teamManager->getTeachers(),
            'pupils' => $teamManager->getPupils(),
            'parents' => $teamManager->getPupilsParents(),
            'activationRoles' => $activationRoles,
            'teamManager' => $teamManager,
            'groups_for_directory' => array($classroom->getId(), $team->getId())
        );
    }

    /**
     * @Route("/supprimer-groupe/{teamSlug}", name="BNSAppClassroomBundle_back_delete_team")
     * @Rights("CLASSROOM_ACCESS_BACK")
     *
     * @param String $teamSlug
     */
    public function deleteTeamAction($teamSlug)
    {
        $this->deleteTeam($teamSlug);

        $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('GROUP_DELETE_SUCCESS', array(), 'CLASSROOM'));

        return $this->redirect($this->generateUrl('BNSAppClassroomBundle_back_team'));

    }

    public function deleteTeam($teamSlug) {

        $gm = $this->get('bns.group_manager');
        $team = $gm->findGroupBySlug($teamSlug);
        //ckeck si le slug correspond à un groupe de type equipe
        if ('TEAM' != $team->getGroupType()->getType() || null == $team) {
            throw new NotFoundHttpException('The team with slug : '.$teamSlug.' is NOT found !');
        }

        $centralGroup = $gm->getGroupFromCentral($team->getId());
        //check si on est pas dans le groupe a supprimer
        $currentGroupId = $this->get('bns.right_manager')->getCurrentGroup()->getId();

        $gm->deleteGroup($centralGroup['id'], $currentGroupId);
    }

}
