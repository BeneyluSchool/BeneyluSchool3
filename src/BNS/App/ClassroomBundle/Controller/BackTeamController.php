<?php

namespace BNS\App\ClassroomBundle\Controller;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Model\Group;

/**
 * @author Eric Chau <eric.chau@pixel-cookers.com>
 */
class BackTeamController extends Controller
{
    /**
     * @Route("/", name="BNSAppClassroomBundle_back_team")
	 * @Rights("CLASSROOM_ACCESS_BACK, CREATE_TEAM")
	 * 
     * @param String $slug
     */
    public function indexAction()
    {
        $rightManager = $this->get('bns.right_manager');
        
        $rightManager->reloadRights();
		
		// On vérifie si l'utilisateur a les droits d'accès
        $rightManager->forbidIf(!$rightManager->isInClassroomGroup());

        $classroomManager = $this->get('bns.classroom_manager');
        // On initialise le BNSClassroomManager avec la classe courante pour ensuite pouvoir récupérer toutes les équipes de la classe;
        $classroom = $rightManager->getCurrentGroup();
        $classroomManager->setClassroom($classroom);
		$groupIdsForUserPicker = array($classroom->getId());
        $groupManager = $this->get('bns.group_manager');
        $teams = $classroomManager->getTeams();
        // On boucle sur toutes les équipes de la classe pour injecter les utilisateurs dans chaque sous-groupe
        foreach ($teams as $team)
        {
			$groupIdsForUserPicker[] = $team->getId();
            // On s'appuye sur le BNSGroupManager; on l'initialise avec l'équipe dont on souhaite récupérer les users
            $groupManager->setGroup($team);
            // Le paramètre $isLocale de la méthode getUsers() est setté à true pour récupérer des objets de type User (Propel)
            $team->setUsers($groupManager->getUsers(true));
        }

		return $this->render('BNSAppClassroomBundle:BackTeam:index.html.twig', array(
            'classroom'					=> $classroom,
            'teams'						=> $teams,
			'group_ids_for_userpicker'	=> $groupIdsForUserPicker
        ));
    }

    /**
     * @Route("/nouvelle-equipe/{name}", name="BNSAppClassroomBundle_back_new_team", options={"expose"=true})
	 * @Rights("CLASSROOM_ACCESS_BACK, CREATE_TEAM")
	 * 
     * @param String $name
     */
    public function newTeamAction($name)
    {
        // AJAX?
        if (false === $this->getRequest()->isXmlHttpRequest()) 
        {
                throw new NotFoundHttpException();
        }

        if ('' == $name)
        {
                throw new HttpException(500, 'Name given contains illegal value!');
        }

        $rightManager = $this->get('bns.right_manager');
        // Check des droits d'accès
        $rightManager->forbidIf(!$rightManager->isInClassroomGroup());

        $classroom = $rightManager->getCurrentGroup();
        $params = array(
            'label'             => $name,
            'attributes'        => array(),
            'group_parent_id'   => $classroom->getId(),
        );
        $team =  $this->get('bns.team_manager')->createTeam($params);
        $team->setUsers($this->get('bns.team_manager')->getUsers(true));
		
        return $this->render('BNSAppClassroomBundle:BackTeam:team_block.html.twig', array(
			'team'						=> $team, 
			'classroom'					=> $classroom, 
			'group_ids_for_userpicker'	=> $this->getClassroomAndHisTeamsIds($classroom)
		));
    }

    /**
     * @Route("/recharger-bloc-equipe/{teamSlug}", name="BNSAppClassroomBundle_back_team_reload_block", options={"expose"=true})
	 * @Rights("CLASSROOM_ACCESS_BACK, CREATE_TEAM")
	 * 
     * @param Request $request
     * @param type $teamSlug 
     */
    public function reloadTeamViewAction(Request $request, $teamSlug)
    {
        // AJAX?
        if (!$request->isXmlHttpRequest())
        {
                throw new NotFoundHttpException();
        }

        $rightManager = $this->get('bns.right_manager');
        $teamManager = $this->get('bns.team_manager');
        // Vérifie qu'il y a bien une équipe associé au slug fourni en paramètre
        $team = $teamManager->findBySlug($teamSlug);

        // Vérifie que l'on a bien un droit d'accès à cette équipe de travail
        $classroomManager = $this->get('bns.classroom_manager');
        $classroom = $rightManager->getCurrentGroup();
        $classroomManager->setClassroom($classroom);
        $rightManager->forbidIf(!$classroomManager->isOneOfMyTeams($team));
		
        // On injecte les utilisateurs de l'équipe dans l'objet du modèle
        $team->setUsers($teamManager->getUsers(true));
		
		return $this->render('BNSAppClassroomBundle:BackTeam:team_block.html.twig', array(
			'team'						=> $team, 
			'classroom'					=> $classroom, 
			'group_ids_for_userpicker'	=> $this->getClassroomAndHisTeamsIds($classroom)
		));
    }

    /**
     * @Route("/ajouter-eleve/{userId}/equipe/{teamSlug}", name="BNSAppClassroomBundle_back_team_add_pupil", options={"expose"=true})
	 * @Rights("CLASSROOM_ACCESS_BACK, CREATE_TEAM")
	 * 
     * @param unknown_type $teamSlug
     * @param unknown_type $userId
     * @throws NotFoundHttpException
     */
    public function addUserToTeamAction($teamSlug, $userId)
    {
        // AJAX?
        if (false === $this->getRequest()->isXmlHttpRequest())
        {
            throw new NotFoundHttpException();
        }

        $rightManager = $this->get('bns.right_manager');
        // Check des droits

        // On vérifie que les utilisateurs et l'équipe en question appartiennent bien à la classe
        $this->isCurrentActionValid($teamSlug, array($userId));

        // On récupère l'objet du model Group à partir du slug fourni en paramètre
        $teamManager = $this->get('bns.team_manager');
        $team = $teamManager->findBySlug($teamSlug);
        $teamManager->setTeam($team);
        // Les vérifications sont finies, on procède à l'ajout de l'utilisateur
        $user = $this->get('bns.user_manager')->findUserById($userId);
        $teamManager->addUser($user);

        return new Response(json_encode(true));
    }

    /**
     * @Route("/supprimer-eleve/{userId}/equipe/{teamSlug}", name="BNSAppClassroomBundle_back_team_remove_pupil", options={"expose"=true})
	 * @Rights("CLASSROOM_ACCESS_BACK, CREATE_TEAM")
	 * 
     * @param unknown_type $teamSlug
     * @param unknown_type $userId
     * @throws NotFoundHttpException
     */
    public function removeUserFromTeamAction(Request $request, $teamSlug, $userId)
    {
        // AJAX?
        if (false === $this->getRequest()->isXmlHttpRequest())
        {
            throw new NotFoundHttpException();
        }

        $rightManager = $this->get('bns.right_manager');
        // Check des droits

            // On vérifie que les utilisateurs et l'équipe en question appartiennent bien à la classe
        $this->isCurrentActionValid($teamSlug, array($userId));

        // Supprime l'utilisateur $userId du groupe $teamSlug
        $user = $this->get('bns.user_manager')->findUserById($userId);
        $team = $this->get('bns.group_manager')->findGroupBySlug($teamSlug);
        $teamManager = $this->get('bns.team_manager');
        $teamManager->setTeam($team);
        $teamManager->removeUser($user);

        return new Response(json_encode(true));
    }

    /**
     * @Route("/gestion-utilisateurs", name="BNSAppClassroomBundle_back_team_add_remove_users", options={"expose"=true})
	 * @Rights("CLASSROOM_ACCESS_BACK, CREATE_TEAM")
	 * 
     * @param Request $request 
     */
    public function addAndRemoveTeamUsersAction()
    {
		$request = $this->getRequest();
        // AJAX?
        if (false === $request->isXmlHttpRequest())
        {
            throw new NotFoundHttpException();
        }

        // On vérifie si l'utilisateur courant a les droits d'action
		$userIds = $request->get('user_ids');
        $userIds = (null != $userIds ? $userIds : array());
        // On vérifie que les utilisateurs et l'équipe en question appartiennent bien à la classe
        $this->isCurrentActionValid($request->get('team_slug'), $userIds);

        $teamManager = $this->get('bns.team_manager');
        // L'action en cours est autorisé; on extrait les informations concernant les utilisateurs à supprimer et/ou à ajouter à l'équipe
        $currentUsersOfTeamId = array();
        foreach ($teamManager->getUsers(true) as $teamUser)
        {
            $currentUsersOfTeamId[] = $teamUser->getId();
        }

        $usersToAdd = array_diff($userIds, $currentUsersOfTeamId);
        $usersToDelete = array_diff($currentUsersOfTeamId, $userIds);
        if (0 == count($usersToAdd) && 0 == count($usersToDelete))
        {
            return new Response(json_encode(false));
        }

        // Toutes les vérifications sont effectuées et passées avec succès, on procède maintenant à l'ajout des utilisateurs dans l'équipe
        $userManager = $this->get('bns.user_manager');
        foreach ($userManager->retrieveUsersById($usersToAdd) as $user)
        {
            $teamManager->addUser($user);
        }

        // on procède maintenant à la suppression des utilisateurs
        foreach ($userManager->retrieveUsersById($usersToDelete) as $user)
        {
            $teamManager->removeUser($user);
        }

        return new Response(json_encode(true));
    }

	private function getClassroomAndHisTeamsIds(Group $classroom = null)
	{
		if (null == $classroom) {
			$classroom = $this->get('bns.classroom_manager')->getCurrentGroup();
		}
		
		$classroomManager = $this->get('bns.classroom_manager');
        $classroomManager->setClassroom($classroom);
		$groupIdsForUserPicker = array($classroom->getId());
        $teams = $classroomManager->getTeams();
		foreach ($teams as $team) {
			$groupIdsForUserPicker[] = $team->getId();
		}
		
		return $groupIdsForUserPicker;
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
        foreach($classroomManager->getUsers(true) as $classroomUser)
        {
            $classroomUserIds[] = $classroomUser->getId();
        }

        foreach ($userIdsToCheck as $userId)
        {
            if (!in_array($userId, $classroomUserIds))
            {
                // Si l'utilisateur ne fait pas partie de la classe, on arrête l'action en cours
                $this->get('bns.right_manager')->forbidIf(true);
            }
        }
    }
}