<?php

namespace BNS\App\UserDirectoryBundle\ApiController;

use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\GroupType;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\Module;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\UserDirectoryBundle\Form\Api\ApiGroupType;
use BNS\App\UserDirectoryBundle\Manager\UserDirectoryManager;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class GroupsApiController
 *
 * @package BNS\App\UserDirectoryBundle\ApiController
 */
class GroupsApiController extends BaseUserDirectoryApiController
{

    /**
     * @ApiDoc(
     *  section = "Annuaire utilisateurs - Groupes",
     *  resource = true,
     *  description = "Récupère les info groupes correspondant aux IDs donnés",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Erreur",
     *      403 = "Pas accès à l'annuaire",
     *      404 = "La page n'a pas été trouvée"
     *  },
     *  requirements = {
     *      {
     *          "name" = "ids",
     *          "dataType" = "array",
     *          "requirement" = "",
     *          "description" = "Tableau d'ids des utilisateurs à récupérer"
     *      }, {
     *          "name" = "group_id",
     *          "dataType" = "integer",
     *          "description" = "ID du groupe dans lequel chercher les utilisateurs. Utilisé également pour les vérifications de droits"
     *      }
     *  },
     * )
     *
     * @Rest\Get("/lookup")
     * @Rest\View(serializerGroups={"Default","user_list", "detail"})
     *
     * @param Request $request
     * @return array|Group[]
     */
    public function lookupAction(Request $request)
    {
        $view = $request->get('view');
        $this->checkUserDirectoryAccess($view);

        $groupIdsHaveRightToSee = array_keys($this->get('bns.user_directory.manager')->getGroupsWhereAccess($this->getUser(), $view));
        $ids = $request->get('ids', array());
        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }
        $groupIdsCanSee = array_intersect($ids,$groupIdsHaveRightToSee);
        return GroupQuery::create()
            ->filterByArchived(0)
            ->orderByLabel()
            ->findPks($groupIdsCanSee)
            ;
    }

    /**
     * @ApiDoc(
     *  section = "Annuaire utilisateurs - Groupes",
     *  resource = true,
     *  description = "Récupère les groupes d'utilisateurs visibles par l'utilisateur connecté",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Erreur",
     *      403 = "Pas accès à l'annuaire",
     *      404 = "La page n'a pas été trouvée"
     *  }
     * )
     *
     * @Rest\Get("")
     * @Rest\View(serializerGroups={"Default","list"})
     *
     * @param Request $request
     * @return Group[]
     */
    public function indexAction(Request $request)
    {
        $view = $request->get('view');
        $this->checkUserDirectoryAccess($view);
        $this->get('hateoas.expression.evaluator')->setContextVariable('view', $view);

        // fetch the flat group collection
        $groups = $this->get('bns.user_directory.manager')->getGroupsWhereAccess($this->getUser(), $view);

        return array_values($groups);
    }

    /**
     * @ApiDoc(
     *  section = "Annuaire utilisateurs - Groupes",
     *  resource = true,
     *  description = "Récupère un groupe d'utilisateurs",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Erreur",
     *      403 = "Pas accès à l'annuaire",
     *      404 = "La page n'a pas été trouvée"
     *  }
     * )
     *
     * @Rest\Get("/{id}")
     * @Rest\View(serializerGroups={"Default","list","detail", "detail2"})
     *
     * @param int $id
     * @param Request $request
     * @return Group
     */
    public function getAction($id, Request $request)
    {
        $view = $request->get('view');
        $this->get('hateoas.expression.evaluator')->setContextVariable('view', $view);
        $this->checkUserDirectoryAccess($view);
        $group = GroupQuery::create()->findPk($id);
        $this->checkGroupAccess($group, $view);

        // special case for campaign: do not see individual users, but counts
        if ($view === UserDirectoryManager::VIEW_CAMPAIGN_RECIPIENTS) {
            if (!$this->get('bns.right_manager')->hasRight('CAMPAIGN_VIEW_INDIVIDUAL_USER', $this->get('bns.right_manager')->getCurrentGroupId())) {
                $this->get('hateoas.expression.evaluator')->setContextVariable('count_users', true);
            }
        }

        return $group;
    }

    /**
     * @ApiDoc(
     *  section = "Annuaire utilisateurs - Groupes",
     *  description = "Supprime un groupe",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Erreur",
     *      403 = "Pas accès à l'annuaire",
     *      404 = "La page n'a pas été trouvée"
     *  }
     * )
     *
     * @Rest\Delete("/{id}")
     * @Rest\View(serializerGroups={"Default","list"})
     *
     * @param int $id
     * @return Response
     */
    public function deleteAction($id)
    {
        $this->checkUserDirectoryAccess();
        $group = GroupQuery::create()->findPk($id);
        $this->checkGroupAccess($group);

        // TODO check droit de suppr dans groupe parent

        if ($group->getType() !== 'TEAM') {
            throw new AccessDeniedHttpException('Cannot delete this group');
        }

        $this->get('bns.group_manager')->deleteGroup($group->getId());

        return new Response('');
    }

    /**
     * @ApiDoc(
     *  section = "Annuaire utilisateurs - Groupes",
     *  description = "Edite un groupe",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Erreur",
     *      403 = "Pas accès à l'annuaire",
     *      404 = "La page n'a pas été trouvée"
     *  }
     * )
     *
     * @Rest\Patch("/{id}")
     * @Rest\View(serializerGroups={"Default","list","detail"})
     *
     * @param int $id
     * @return Response
     */
    public function patchAction($id)
    {
        $this->checkUserDirectoryAccess();
        $group = GroupQuery::create()->findPk($id);
        $this->checkGroupAccessBack($group);

        return $this->restForm(new ApiGroupType(), $group, array(
            'csrf_protection' => false, // TODO
        ));
    }

    /**
     * @ApiDoc(
     *  section = "Annuaire utilisateurs - Groupes",
     *  resource = true,
     *  description = "Récupère les sous-groupes de travail d'un groupe donné",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Erreur",
     *      403 = "Pas accès à l'annuaire",
     *      404 = "La page n'a pas été trouvée"
     *  }
     * )
     *
     * @Rest\Get("/{id}/subgroups")
     * @Rest\View(serializerGroups={"Default","list"})
     *
     * @param int $id
     * @param Request $request
     * @return Group[]
     */
    public function getTeamsAction($id, Request $request)
    {
        $view = $request->get('view');
        $group = GroupQuery::create()->findPk($id);
        $subgroups = $this->get('bns.user_directory.manager')->getSubgroupsWhereAccess($group, $this->getUser(), $view);

        return array_values($subgroups);
    }

    /**
     * @ApiDoc(
     *  section = "Annuaire utilisateurs - Groupes",
     *  resource = true,
     *  description = "Crée un groupe de travail en tant que sous-group du groupe donné",
     *  requirements = {
     *      {
     *          "name" = "users",
     *          "dataType" = "array",
     *          "requirement" = "",
     *          "description" = "Tableau d'ids des utilisateurs à ajouter au groupe"
     *      }
     *  },
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Erreur",
     *      403 = "Pas accès à l'annuaire",
     *      404 = "La page n'a pas été trouvée"
     *  }
     * )
     *
     * @Rest\Post("/{id}/team")
     * @Rest\View(serializerGroups={"Default","list"})
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function createTeamAction(Request $request, $id)
    {
        $this->checkUserDirectoryAccess();
        $group = GroupQuery::create()->findPk($id);
        $this->checkGroupAccess($group);
        $this->checkGroupAccessBack($group);

        $label = $request->get('label', '');

        // from submitted ids, keep only those already in parent group
        $users = array();
        $userIds = $this->get('bns.group_manager')->setGroup($group)->getUsersIds();
        $userManager = $this->get('bns.user_manager');
        foreach ($request->get('users', array()) as $id) {
            if (in_array($id, $userIds)) {
                $users[] = $id;

                // add parent user if necessary
                $user = $userManager->setUserById($id)->getUser();
                $mainRole = strtoupper($userManager->getMainRole());
                if ($mainRole == 'PUPIL') {
                    $parents = $userManager->getUserParent($user);
                    foreach ($parents as $parent) {
                        if (in_array($parent->getId(), $userIds)) {
                            $users[] = $parent->getId();
                        }
                    }
                }
            }
        }

        // add current user if not already present
        $user = $this->getUser();
        if (!in_array($user->getId(), $users)) {
            $users[] = $user->getId();
        }

        $subgroup = $this->get('bns.user_directory.group_manager')->createTeamGroup($group, $label, $users);

        return $this->generateLocationResponse('user_directory_api_groups_get', $subgroup);
    }

    /**
     * @ApiDoc(
     *  section = "Annuaire utilisateurs - Groupes",
     *  resource = true,
     *  description = "Ajoute une sélection d'utilisateurs au groupe donné",
     *  requirements = {
     *      {
     *          "name" = "users",
     *          "dataType" = "array",
     *          "requirement" = "",
     *          "description" = "Tableau d'ids des utilisateurs à ajouter au groupe"
     *      }
     *  },
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Erreur",
     *      403 = "Pas accès à l'annuaire",
     *      404 = "La page n'a pas été trouvée"
     *  }
     * )
     *
     * @Rest\Patch("/{id}/users/add")
     * @Rest\View(serializerGroups={"Default","list","detail"})
     *
     * @param Request $request
     * @param int $id
     * @return Group
     * @throws \Exception
     */
    public function addUsersAction(Request $request, $id)
    {
        $this->checkUserDirectoryAccess();
        $group = GroupQuery::create()->findPk($id);
        $this->checkGroupAccess($group);
        $this->checkGroupAccessBack($group);

        if (!$this->get('bns.user_directory.group_manager')->isSubgroup($group)) {
            throw new AccessDeniedHttpException("Cannot add users to " . $group->getType());
        }

        $userManager = $this->get('bns.user_manager');
        $groupManager = $this->get('bns.group_manager')->setGroup($group);
        $roleManager = $this->get('bns.role_manager');
        foreach ($request->get('users', array()) as $userId) {
            try {
                $user = $userManager->setUserById($userId)->getUser();
                $mainRole = strtoupper($userManager->getMainRole());
                if($mainRole == 'PUPIL') {
                    $parents = $userManager->getUserParent($user);
                    foreach ($parents as $parent){
                        $groupManager->addUser($parent);
                        $roleManager->setGroupTypeRoleFromType('PARENT')->assignRole($parent, $group->getId());
                    }
                }
                $groupManager->addUser($user);
                $roleManager->setGroupTypeRoleFromType($mainRole)->assignRole($user, $group->getId());
            } catch (\Exception $e) {
                throw $e;
            }
        }

        return $group;
    }

    /**
     * @ApiDoc(
     *  section = "Annuaire utilisateurs - Groupes",
     *  resource = true,
     *  description = "Retire une sélection d'utilisateurs du groupe donné",
     *  requirements = {
     *      {
     *          "name" = "users",
     *          "dataType" = "array",
     *          "requirement" = "",
     *          "description" = "Tableau d'ids des utilisateurs à retirer du groupe"
     *      }
     *  },
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Erreur",
     *      403 = "Pas accès à l'annuaire",
     *      404 = "La page n'a pas été trouvée"
     *  }
     * )
     *
     * @Rest\Patch("/{id}/users/remove")
     * @Rest\View(serializerGroups={"Default","list","detail"})
     *
     * @param Request $request
     * @param int $id
     * @return Group
     * @throws \Exception
     */
    public function removeUsersAction(Request $request, $id)
    {
        $this->checkUserDirectoryAccess();
        $group = GroupQuery::create()->findPk($id);
        $this->checkGroupAccess($group);
        $this->checkGroupAccessBack($group);

        if (!$this->get('bns.user_directory.group_manager')->isSubgroup($group)) {
            throw new AccessDeniedHttpException("Cannot remove users from " . $group->getType());
        }
        $userManager = $this->get('bns.user_manager');
        $parentsIds = [];
        foreach ($request->get('users', array()) as $userId) {
            $user = $userManager->setUserById($userId)->getUser();
            $mainRole = strtoupper($userManager->getMainRole());
            if ($mainRole == 'PUPIL') {
                $parents = $userManager->getUserParent($user);
                foreach ($parents as $parent) {
                    $parentsIds[] = $parent->getId();
                }
            }
        }
        $this->get('bns.group_manager')->setGroup($group)->removeUsers($parentsIds);
        $userIds = $request->get('users', array());
        $this->get('bns.group_manager')->setGroup($group)->removeUsers($userIds);

        return $group;
    }

    /**
     * @ApiDoc(
     *  section = "Annuaire utilisateurs - Groupes",
     *  resource = true,
     *  description = "Récupère la matrice des modules d'un groupe",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Erreur",
     *      403 = "Pas accès à l'annuaire",
     *      404 = "La page n'a pas été trouvée"
     *  }
     * )
     *
     * @Rest\Get("/{id}/modules")
     * @Rest\View(serializerGroups={"Default","list"})
     *
     * @param Group $group
     * @return array
     */
    public function getModulesAction(Group $group)
    {
        $this->checkGroupAccessBack($group);

        return $this->get('bns.user_directory.right_manager')->getModules($group);
    }

    /**
     * @ApiDoc(
     *  section = "Annuaire utilisateurs - Groupes",
     *  resource = true,
     *  description = "Toggle l'état d'activation d'un module pour une catégorie d'utilisateurs dans le groupe donné",
     *  requirements = {
     *      {
     *          "name" = "role",
     *          "dataType" = "string",
     *          "requirement" = "",
     *          "description" = "Rôle pour lequel le toggle doit se faire"
     *      }
     *  },
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Erreur",
     *      403 = "Pas accès à l'annuaire",
     *      404 = "La page n'a pas été trouvée"
     *  }
     * )
     *
     * @Rest\Get("/{id}/modules/{moduleUniqueName}/toggle")
     * @Rest\View(serializerGroups={"Default","list"})
     *
     * @param Request $request
     * @param Group $group
     * @param string $moduleUniqueName
     * @return array
     * @throws \Exception
     */
    public function toggleModuleAction(Request $request, Group $group, $moduleUniqueName)
    {
        $this->checkGroupAccessBack($group);

        $groupId = $group->getId();
        $groupTypeRole = GroupTypeQuery::create()->findOneByType($request->get('role', ''));
        if (!$groupTypeRole) {
            throw new NotFoundHttpException("No role found for '".$request->get('role')."'");
        }

        // parent cannot have messaging in partnerships
        if ($group->isPartnerShip() && 'MESSAGING' === $moduleUniqueName && 'PARENT' === $groupTypeRole->getType()) {
            throw new AccessDeniedHttpException("Cannot do that in a partnership");
        }

        $isActive = $this->checkIsModuleActive($group, $moduleUniqueName, $groupTypeRole);
        $requestedValue = !$isActive;

        $this->get('bns.right_manager')->toggleModule($groupId, $moduleUniqueName, $groupTypeRole, $requestedValue);

        return array(
            'group_id' => $group->getId(),
            'module_unique_name' => $moduleUniqueName,
            'role' => $groupTypeRole->getType(),
            'state' => $this->checkIsModuleActive($group, $moduleUniqueName, $groupTypeRole),
        );
    }

    protected function checkIsModuleActive(Group $group, $moduleUniqueName, GroupType $groupTypeRole) {
        /** @var Module $activeModule */
        foreach ($this->get('bns.group_manager')->setGroup($group)->getActivatedModules($groupTypeRole) as $activeModule) {
            if ($moduleUniqueName === $activeModule->getUniqueName()) {
                return true;
            }
        }

        return false;
    }


    /**
     * @ApiDoc(
     *  section = "Annuaire utilisateurs - Groupes",
     *  resource = true,
     *  description = "Recherche des utilisateurs par nom ou prénom",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Erreur",
     *      403 = "Pas accès à l'annuaire",
     *      404 = "La page n'a pas été trouvée"
     *  },
     * )
     *
     * @Rest\Get("/search/{search}")
     * @Rest\View(serializerGroups={"Default","user_list"})
     *
     * @param string $search
     * @param Request $request
     * @return array|Group[]
     */
    public function searchAction($search, Request $request)
    {
        $view = $request->get('view');
        $this->checkUserDirectoryAccess($view);
        $ids = $this->get('bns.right_manager')->getGroupIdsWherePermission('MESSAGING_ACCESS');

        $excludeIds = $request->get('exclude_ids');
        if (is_array($excludeIds)) {
            $excludeIds = array_map(function($id) {
                return (int) $id;
            }, $excludeIds);
            $ids = array_diff($ids, $excludeIds);
        }

        return GroupQuery::create()
            ->filterById($ids)
            ->filterByLabel("%$search%", \Criteria::LIKE)
            ->find();
    }

}
