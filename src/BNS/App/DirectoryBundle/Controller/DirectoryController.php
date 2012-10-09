<?php

namespace BNS\App\DirectoryBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\GroupPeer;

class DirectoryController extends Controller
{
	private $groupsCache = array();
	
    /**
     * @Route("/", name="BNSAppDirectoryBundle_front", options={"expose"=true})
     */
    public function indexAction()
    {
		if (!$this->getRequest()->isXmlHttpRequest()) {
			throw new HttpException(500, 'Must be XmlHttpRequest!');
		}
		
		$groupsICanView = $this->getGroupsWhereIHaveDirectoryAccessPermission();
		// On garde le groupe sur lequel on navigue comme groupe référence
		$currentGroup = null;
		$currentGroupId = $this->get('bns.right_manager')->getCurrentGroupId();
		
		$groups = array();
		// On boucle sur tous les groupes pour lesquels on a la permission 'DIRECTORY_ACCESS' pour leur injecter les sous-groupes et les utilisateurs
		foreach ($groupsICanView as $group) {
			$group = $this->hydrateGroupWithUserAndSubgroups($group, array('TEACHER', 'PUPIL', 'PARENT'));
			// Si le groupe a le même id que l'id du groupe référence alors on garde le groupe dans une variable à part
			if ($currentGroup == null && !$group->hasSubgroup() && $currentGroupId == $group->getId()) {
				$currentGroup = $group;
			}
			else {
				$groups[] = $group;
			}
		}

		return $this->render('BNSAppDirectoryBundle:Directory:index.html.twig', array(
			'groups'			=> $groups,
			'current_group'		=> $currentGroup,
			'is_userpicker'		=> false
		));
    }
	
	/**
	 * @Route("/afficher-selection-utilisateur", name="display_userpicker", options={"expose"=true})
	 */
	public function activateUserPickerAction()
	{
		$request = $this->getRequest();
		// On vérifie que la requête a bien été soumise en AJAX avec la méthode POST
		if (!$request->isXmlHttpRequest() || !$request->isMethod('POST')) {
			throw new HttpException(500, 'Request must be: `POST` method and XmlHttpRequest!');
		}
		
		// On récupère l'id de la modal
		$modalId = $request->get('modal_id', null);
		if ($modalId == null) {
			throw new HttpException(500, 'modal_id parameter\'s is missing!');
		}
		
		// On récupère la liste des types de groupe à afficher si l'utilisateur a voulu les restreindre
		$groupTypesFilter = $request->get('group_types_filter', array());
		// On récupère la liste des rôles utilisateurs à afficher si l'utilisateur a voulu les restreindre
		$rolesFilter = $request->get('roles_filter', array());
		// On vérifie si l'utilisateur souhaite ajouter des utilisateurs dans un groupe existant ou non (qu'on appellera groupe cible)
		$groupTargetSlug = $request->get('group_target_slug', null);
		//Module si spécifique
		$module = $request->get('module',null);
		
		$groupTarget = null;
		if ($groupTargetSlug != null) {
			$groupTarget = $this->get('bns.group_manager')->findGroupBySlug($groupTargetSlug);
		}
		
		// On vérifie si l'utilisateur a fourni une liste de group_id pour lesquels il veut afficher les groupes
		$groupIds = $this->getRequest()->get('group_ids', null);
		$groupsICanView = array();
		// Si l'utilisateur a fourni aucun group_id, on affiche l'UserPicker avec les groupes dont il a accès depuis l'annuaire
		if (0 >= count($groupIds)) {
			$groupsICanView = $this->getGroupsWhereIHaveDirectoryAccessPermission($groupTypesFilter);
		}
		else {
			// Arrivé ici, il y a bien une liste de group_id fourni par l'utilisateur, on boucle sur toutes les informations du tableau
			foreach ($groupIds as $groupId) {
				// Si un des id fournis n'est pas une valeur numérique alors on lève une exception
				if (!is_numeric($groupId)) {
					throw new HttpException(500, 'Every id given in array $groupIds must be numeric!');
				}
			}
			// Les group_ids fournis satisfont aux tests, on récupére les objet Group associés
			$groupsICanView = GroupQuery::create()
				->add(GroupPeer::ID, $groupIds, \Criteria::IN)
			->find();
		}
		
		$groups = array();
		$currentGroup = null;
		$currentGroupId = $this->get('bns.right_manager')->getCurrentGroupId();
		// On boucle sur tous les groupes pour leur injecter les sous-groupes et les utilisateurs
		foreach ($groupsICanView as $group) {
			$group = $this->hydrateGroupWithUserAndSubgroups($group, $rolesFilter);
			// Si le groupe a le même id que l'id du groupe référence alors on garde le groupe dans une variable à part
			if ($currentGroup == null && !$group->hasSubgroup() && $currentGroupId == $group->getId()) {
				$currentGroup = $group;
			}
			elseif ($groupTarget != null && $groupTarget->getId() == $group->getId()) {
				$groupTarget = $group;
			}
			else {
				$groups[] = $group;
			}
		}
		
		return $this->render('BNSAppDirectoryBundle:Directory:index.html.twig', array(
			'modal_id'				=> $modalId,
			'groups'				=> $groups,
			'current_group'			=> $currentGroup,
			'group_target'			=> $groupTarget,
			'is_userpicker'			=> true,
			'module'				=> $module
		));
	}
	
	public function renderUserPickerAction($groups, $modalId, Group $groupTarget = null, array $rolesFilter = array())
	{
		$currentGroupId = $this->get('bns.right_manager')->getCurrentGroupId();
		$currentGroup = null;
		foreach ($groups as $group) {
			if ($group instanceof Group) {
				if (!$group->hasSubgroup() && $currentGroupId == $group->getId()) {
					$currentGroup = $group;
					break;
				}
				continue;
			}
			
			throw new HttpException(500, 'Every group given in array $group must be instance of BNS\App\CoreBundle\Model\Group!');
		}
		
		if (null != $groupTarget && !($groupTarget instanceof Group)) {
			throw new HttpException(500, 'Group target given must be instance of BNS\App\CoreBundle\Model\Group!');
		}
		
		if (null == $currentGroup) {
			$currentGroup = $this->get('bns.right_manager')->getCurrentGroup();
			if ('CLASSROOM' == $currentGroup->getGroupType()->getType() || 'TEAM' == $currentGroup->getGroupType()->getType()) {
				$currentGroup = $this->hydrateGroupWithUserAndSubgroups($currentGroup, $rolesFilter);
			}
			else {
				$currentGroup = null;
			}
		}
		
		return $this->render('BNSAppDirectoryBundle:Directory:index.html.twig', array(
			'modal_id'			=> $modalId,
			'groups'			=> $groups,
			'current_group'		=> $currentGroup,
			'group_target'		=> $groupTarget,
			'is_userpicker'		=> true
		));
	}
	
	/**
	 * Méthode qui permet de récupérer tous les groupes où l'utilisateur courant a la permission DIRECTORY_ACCESS
	 * 
	 * @param array<string> $groupTypesFilter tableau contenant les type de groupes que vous autorisez à être affiché pour la sélection
	 * d'utilisateur; paramètre facultatif; par défaut, tous les type de groupe sont affichés
	 * @return array<Group> tableau contenant tous les groupes dont l'utilisateur courant a la permission DIRECTORY_ACCESS
	 */
	private function getGroupsWhereIHaveDirectoryAccessPermission(array $groupTypesFilter = array())
	{
		// On récupère tous les groupes où on a la permission de voir le groupe en question dans l'annuaire
		$groupsICanView = array();
		// On vérifie s'il y a un filtre par type de groupe à appliquer ou non
		if (count($groupTypesFilter) > 0) {
			foreach($this->get('bns.user_manager')->setUser($this->getUser())->getGroupsWherePermission('DIRECTORY_ACCESS') as $groupICanView) {
				if (in_array($groupICanView->getGroupType()->getType(), $groupTypesFilter)) {
					$groupsICanView[] = $groupICanView;
				}
			}
		}else{
			$groupsICanView = $this->get('bns.user_manager')->setUser($this->getUser())->getGroupsWherePermission('DIRECTORY_ACCESS');
		}
		
		return $groupsICanView;
	}
	
	/**
	 * Méthode qui, pour le groupe passé en paramètre $group, hydrate ses utilisateurs répartis en rôle dans ses sous-groupes; les sous-groupes
	 * sont également setté pour le groupe, de manière récursive tant que le groupe est ni une Classe, ni une Equipe.
	 * 
	 * @param \BNS\App\CoreBundle\Model\Group $group le groupe dont on souhaite fixé ses sous-groupes et ses utilisateurs
	 * @param array<string> $rolesFilter tableau contenant des unique name des type de groupe type rôle dont on souhaite afficher
	 * @return type
	 */
	private function hydrateGroupWithUserAndSubgroups(Group $group, array $rolesFilter)
	{
		$isGroupInCache = false;
		// On recherche si on a déjà le groupe en cache (ce qui signifie que l'on a déjà fait les requêtes pour le récupérer et l'hydrater)
		foreach ($this->groupsCache as $groupCache) {
			// Si oui on retourne le groupe et on a plus besoin de refaire les requêtes
			if ($groupCache->getId() == $group->getId()) {
				$group = $groupCache;
				$isGroupInCache = true;
			}
		}
		
		if (!$isGroupInCache) {
			// On récupère la liste des utilisateurs du groupe
			$group->setSubgroupsRoleWithUsers($this->get('bns.group_manager')->setGroup($group)->getSubgroupRoleWithUsers($rolesFilter));
			// Si le groupe $group est ni une Classe, ni une Equipe alors on va chercher tous ses sous-groupes
			if ('CLASSROOM' != $group->getGroupType()->getType() && 'TEAM' != $group->getGroupType()->getType()) {
				$subgroups = array();
				// On veut seulement les sous-groupes qui ne simulent pas des rôles
				foreach ($this->get('bns.group_manager')->setGroup($group)->getSubgroups(true, false) as $subgroup) {
					// Si le sous-groupe a le même id que le groupe référence alors on ne l'ajoute pas parmis les sous-groupes
					if ($this->get('bns.right_manager')->getCurrentGroupId() == $subgroup->getId()) {
						continue;
					}
					
					// On effectue un appel résursif pour hydrater à son tour le sous-groupe avec ses utilisateurs et ses sous-groupes s'il satisfait aux conditions
					$subgroups[] = $this->hydrateGroupWithUserAndSubgroups($subgroup, $rolesFilter);
				}
				
				// On set les sous-groupes que l'on a récupéré au group $group
				$group->setSubgroups($subgroups);
			}
			
			// On enregistre le groupe en cache
			$this->groupsCache[] = $group;
		}
		
		// Finally
		return $group;
	}
}
