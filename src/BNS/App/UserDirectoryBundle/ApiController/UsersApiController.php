<?php

namespace BNS\App\UserDirectoryBundle\ApiController;

use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Model\User;
use BNS\App\UserDirectoryBundle\Manager\UserDirectoryManager;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcherInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Class UsersApiController
 *
 * @package BNS\App\UserDirectoryBundle\ApiController
 */
class UsersApiController extends BaseUserDirectoryApiController
{

    /**
     * @ApiDoc(
     *  section = "Annuaire utilisateurs - Utilisateurs",
     *  resource = true,
     *  description = "Récupère les info utilisateurs correspondant aux IDs donnés",
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
     * @return array|User[]
     */
    public function lookupAction(Request $request)
    {
        $view = $request->get('view');
        $this->checkUserDirectoryAccess($view);

        $ids = $request->get('ids', array());
        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }

        if ($groupId = $request->get('group_id')) {
            $group = GroupQuery::create()->filterByArchived(0)->findPk($groupId);
        }

        // If group given, check access + specific rights. Else get all groups where user has access
        if (isset($group)) {
            $this->checkGroupAccess($group, $view);
            $groups = [$group];
            $hasProfileAccess = $this->get('bns.user_manager')->setUser($this->getUser())->hasRight('PROFILE_ACCESS', $groupId);

            // no direct profile access, maybe it's a partnership?
            if (!$hasProfileAccess) {
                /** @var Group[] $partnerships */
                $partnerships = $this->get('bns.partnership_manager')->getPartnershipsGroupBelongs($group->getId());
                foreach ($partnerships as $partnership) {
                    if ($this->get('bns.user_manager')->hasRight('PROFILE_ACCESS', $partnership->getId())) {
                        $hasProfileAccess = true;
                        break;
                    }
                }
            }

            if ($hasProfileAccess) {
                $this->get('hateoas.expression.evaluator')->setContextVariable('profile_link', true);
            }
        } else {
            $groups = $this->get('bns.user_directory.manager')->getGroupsWhereAccess($this->getUser());
        }

        // check that requested ids are visibile, ie are in groups where user has access
        $idsToCheck = $ids;
        foreach ($groups as $group) {
            // cannot see users of this group, ignore it
            if (!$this->get('bns.user_directory.right_manager')->areGroupUsersVisible($group, $view)) {
                continue;
            }

            $idsInGroup = $this->get('bns.group_manager')->setGroup($group)->getUsersIds();
            $idsToCheck = array_diff($idsToCheck, $idsInGroup);

            if (!count($idsToCheck)) {
                break;
            }
        }

        // there are still ids to check: either they do not exist, or user has no access to them
        if (count($idsToCheck)) {
            throw new AccessDeniedHttpException();
        }

        return UserQuery::create()
            ->filterByArchived(0)
            ->orderByLastName()
            ->orderByFirstName()
            ->findPks($ids)
        ;
    }

    /**
     * @ApiDoc(
     *  section = "Annuaire utilisateurs - Utilisateurs",
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
     * @return array|User[]
     */
    public function searchAction($search, Request $request)
    {
        $view = $request->get('view');
        $this->checkUserDirectoryAccess($view);

        $ids = [];
        foreach ($this->get('bns.user_directory.manager')->getGroupsWhereAccess($this->getUser(), $view) as $group) {
            $recipients = $this->get('bns.user_directory.manager')->getUserIdsByRoles($group, $view);
            foreach ($recipients as $idsByRole) {
                $ids = array_merge($ids, $idsByRole);
            }
        }

        $ids = array_unique($ids);

        $excludeIds = $request->get('exclude_ids');
        if (is_array($excludeIds)) {
            $excludeIds = array_map(function($id) {
                return (int) $id;
            }, $excludeIds);
            $ids = array_diff($ids, $excludeIds);
        }

        return UserQuery::create()
            ->filterById($ids)
            ->filterByFirstName("%$search%", \Criteria::LIKE)
            ->_or()
            ->filterByLastName("%$search%", \Criteria::LIKE)
            ->find()
        ;
    }

    /**
     * @ApiDoc(
     *  section = "Annuaire utilisateurs - Utilisateurs",
     *  resource = true,
     *  description = "Récupère les informations d'un utilisateur",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Erreur",
     *      403 = "Pas accès à l'annuaire",
     *      404 = "La page n'a pas été trouvée"
     *  }
     * )
     *
     * @Rest\Get("/{id}")
     * @Rest\View(serializerGroups={"Default","list"})
     *
     * @param User $user
     * @return User
     */
    public function getAction(User $user)
    {
        $this->checkUserDirectoryAccess();

        // TODO: check access to user

        return $user;
    }

}
