<?php

namespace BNS\App\MainBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

use BNS\App\CoreBundle\Model\Group;

class UserPickerController extends Controller
{
    /**
     * Fourni le contenu du modal d'UserPicker
     * 
     * @Route("/selection-utilisateur", name="BNSAppMainBundle_user_picker", options={"expose"=true})
     */
    public function indexAction()
    {
		$request = $this->getRequest();		
        //$roleUniqueName = $request->get('role_unique_name');
        //$groupTypeId = $request->get('group_type_id');
        $groupContextSlug = $request->get('group_context_slug');
        $currentGroupSlug = $request->get('current_group_slug');
        
        
        $groupManager = $this->get('bns.group_manager');

        $groupContext = null;
        // On vérifie que le groupe de contexte est bien renseigné
        if (null != $groupContextSlug)
        {
            // On vérifie que le slug du groupe de contexte est bien existant
            $groupContext = $groupManager->findGroupBySlug($groupContextSlug);
            if (null == $groupContext)
            {
                throw new HttpException('500', 'Group context\'s slug given does not exist: '. $groupContextSlug .'.');
            }
            
            // On récupère la liste des utilisateurs du groupe de contexte
            $groupManager->setGroup($groupContext);
            $groupContext->setUsers($groupManager->getUsers(true));
            //$groups[] = $currentGroupParent;
        }
        else
        {
            throw new HttpException('500', 'You have to provide the group parent\'s slug!');
        }
        
        // On vérifie si un groupe courant a été fourni
        $currentGroup = null;
        if (null != $currentGroupSlug)
        {
            // Si oui on vérifie que le slug fournit correspond bien à un groupe
            $currentGroup = $groupManager->findGroupBySlug($currentGroupSlug);
            if (null == $currentGroup)
            {
                throw new HttpException('500', 'Group\'s slug given does not exist: '. $currentGroupSlug .'.');
            }
            
            // On récupère sa liste d'utilisateur qu'on lui injecte
            $groupManager->setGroup($currentGroup);
            $currentGroup->setUsers($groupManager->getUsers(true));
        }
        
        // On injecte tous les sous-groupes avec leurs utilisateurs au groupe de contexte
        $groupContext = $this->hydrateAllSubgroups($groupContext, $currentGroup->getId());
        
        // Pour le moment on met juste un seul élément dans la tableau
        $groupsContext = array($groupContext);
        
        return $this->render('BNSAppMainBundle:UserPicker:index.html.twig', array(
            'currentGroup'  => $currentGroup,
            'groupsContext'  => $groupsContext,
        ));
    }
    
    /**
     * Méthode récursive qui permet d'hydrater tous les sous-groups du groupe donné en paramètre ($group), les utilisateurs
     * de chaque sous-groupe inclus
     * 
     * @param Group $group
     * @param int $currentGroupId id du sous-groupe pour lequel on souhaite 
     * @return Group 
     */
    private function hydrateAllSubgroups(Group $group, $currentGroupId = null)
    {
        $groupManager = $this->get('bns.group_manager');
        $groupManager->setGroup($group);
        // On récupère les utilisateurs du groupe $group et on les lui injecte
        $group->setUsers($groupManager->getUsers(true));
        $subgroups = $groupManager->getSubgroups(true, false);
        
        // On vérifie si le groupe possède des sous-groupes
        if (0 != count($subgroups))
        {
            // On trie les sous-groupes et on ignore le groupe courant s'il est parmi la liste des sous-groupes
            $subgroupsSorted = array();
            foreach($subgroups as $subgroup)
            {
                if ($currentGroupId != null && $currentGroupId == $subgroup->getId())
                {
                    continue;
                }
                
                // Appel récursif : on hydrate tous les sous-groupe potentiel du sous-groupe courant
                $subgroupsSorted[] = $this->hydrateAllSubgroups($subgroup, $currentGroupId);
            }
            
            // On set la nouvelle liste des sous-groupes (avec les utilisateurs) au groupe $group
            $group->setSubgroups($subgroupsSorted);
        }   
        
        return $group;
    }
}