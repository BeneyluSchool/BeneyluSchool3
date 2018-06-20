<?php
namespace BNS\App\UserDirectoryBundle\ApiController;

use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\UserDirectoryBundle\Form\Type\DistributionListGroupType;
use BNS\App\UserDirectoryBundle\Model\DistributionList;
use BNS\App\UserDirectoryBundle\Model\DistributionListGroup;
use BNS\App\UserDirectoryBundle\Model\DistributionListGroupQuery;
use BNS\App\UserDirectoryBundle\Model\DistributionListQuery;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcherInterface;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @author Julie Boisnard <julie.boisnard@pixel-cookers.com>
 */
class DistributionListApiController extends BaseApiController
{
    /**
     * @ApiDoc(
     *  section="Annuaire utilisateurs - Listes de diffusion",
     *  resource = true,
     *  description="Get group's distribution list",
     * )
     *
     * @Rest\Get("/groups/{groupId}/distribution-lists")
     * @Rest\View(serializerGroups={"Default","list","detail"})
     *
     * @Rest\QueryParam("type", requirements="STRUCT|USER", nullable=true)
     *
     *
     * @param Integer $groupId
     * @return array
     */
    public function getDistributionListsAction($groupId, ParamFetcherInterface $paramFetcher)
    {
        if (!$this->canAccessGroup($groupId)) {
            throw $this->createAccessDeniedException();
        }

        $query = DistributionListQuery::create()
            ->filterByGroupId($groupId)
        ;

        $type = $paramFetcher->get('type', true);
        if ($type) {
            $query->filterByType($type);
        }
        return $query->find();
    }

    /**
     * @ApiDoc(
     *  section="Annuaire utilisateurs - Listes de diffusion",
     *  resource = true,
     *  description="Get multiple distribution lists",
     *  requirements = {
     *   {
     *     "name" = "ids",
     *     "dataType" = "array",
     *     "requirement" = "",
     *     "description" = "Distribution list ids"
     *   }
     *  },
     * )
     *
     * @Rest\Get("/distribution-lists/lookup")
     * @Rest\View(serializerGroups={"Default","list"})
     *
     * @param Request $request
     * @return array
     */
    public function getDistributionListLookupAction(Request $request)
    {
        $ids = explode(',', $request->get('ids', ''));

        $lists = DistributionListQuery::create()
            ->filterById($ids)
            ->find()
        ;

        $allowedGroupIds = [];
        $groupIds = $lists->toKeyValue('GroupId', 'GroupId');
        foreach ($groupIds as $groupId) {
            if ($this->canAccessGroup($groupId)) {
                $allowedGroupIds[$groupId] = $groupId;
            }
        }

        $allowedLists = [];
        foreach ($lists as $list) {
            if (isset($allowedGroupIds[$list->getGroupId()])) {
                $allowedLists[] = $list;
            }
        }

        return $allowedLists;
    }

    /**
     * @ApiDoc(
     *  section="Annuaire utilisateurs - Listes de diffusion",
     *  resource = true,
     *  description="Get current group structures",
     * )
     *
     * @Rest\Get("/distribution-lists/structures")
     * @Rest\View(serializerGroups={"Default","list"})
     *
     * @return array
     */
    public function getStructuresAction()
    {
        $currentGroup = $this->get('bns.right_manager')->getCurrentGroup();
        switch ($currentGroup->getType()) {
            case 'CLASSROOM':
            case 'SCHOOL':
                $permission = $currentGroup->getType().'_ACCESS_BACK';
                break;
            default:
                $permission = 'GROUP_ACCESS_BACK';
        }

        if (!$this->get('bns.right_manager')->hasRight($permission, $currentGroup->getId())) {
            throw new AccessDeniedHttpException('Cannot view structures of current group');
        }

        // remove disabled groups
        $structures = $this->get('bns.group_manager')->setGroup($currentGroup)->getAllSubgroups($currentGroup->getId());
        if ($this->container->hasParameter('check_group_enabled') && $this->getParameter('check_group_enabled')) {
            foreach ($structures as $key => $structure) {
                /** @var Group $structure */
                if (!$structure->isEnabled() || $structure->getType() === 'TEAM') {
                    $structures->remove($key);
                }

                if (!$this->get('bns.right_manager')->hasRight('CAMPAIGN_VIEW_CLASSROOM', $currentGroup->getId()) && $structure->getType() === 'CLASSROOM') {
                    $structures->remove($key);
                }
            }
        } else {
            foreach ($structures as $key => $structure) {
                /** @var Group $structure */
                if ($structure->getType() === 'TEAM') {
                    $structures->remove($key);
                }
                if (!$this->get('bns.right_manager')->hasRight('CAMPAIGN_VIEW_CLASSROOM', $currentGroup->getId()) && $structure->getType() === 'CLASSROOM') {
                    $structures->remove($key);
                }
            }
        }

        // array may now have holes due to removed groups, ensure it is continuous
        return array_values($structures->getArrayCopy());
    }

    /**
     * @ApiDoc(
     *  section="Annuaire utilisateurs - Listes de diffusion",
     *  resource = true,
     *  description="Get one group's distribution list",
     * )
     *
     * @Rest\Get("/distribution-lists/{id}")
     * @Rest\View(serializerGroups={"Default","detail"})
     *
     *
     * @param Integer $id
     * @return array
     */
    public function getDistributionListAction($id)
    {
        $list = DistributionListQuery::create()
            ->filterById($id)
            ->findOne();

        if (!$list) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        return $list;
    }

    /**
     * <pre>
     * {"ids" : [20,23,24,25,26,27,28]}
     * </pre>
     *
     * @ApiDoc(
     *  section="Annuaire utilisateurs - Listes de diffusion",
     *  resource = true,
     *  description="Delete distribution lists",
     * )
     *
     * @Rest\Delete("/distribution-lists")
     * @Rest\View(serializerGroups={"Default"})
     *
     * @param Request $request
     *
     * @return array
     */
    public function deleteDistributionListsAction(Request $request)
    {
        $ids = $request->get('ids', []);

        // todo: check rights
        DistributionListQuery::create()
            ->filterById($ids)
            ->delete()
        ;

        return View::create('', Codes::HTTP_OK);
    }

    /**
     * <pre>
     * {"name" : "My awesome Distribution List",
     * "group_ids" : [1, 2, 3],
     * "role_ids" : [1,7]}
     * </pre>
     *
     * @ApiDoc(
     *  section="Annuaire utilisateurs - Listes de diffusion",
     *  resource = true,
     *  description="Create one group's distribution list",
     * )
     *
     * @Rest\Post("/groups/{groupId}/distribution-lists")
     * @Rest\View(serializerGroups={"Default","detail"})
     *
     * @param Integer $groupId
     * @param Request $request
     *
     * @return array
     */
    public function postAddDistributionListAction($groupId, Request $request)
    {
        $listName = $request->get('name');
        $groupIds = $request->get('group_ids', []);
        $roles = $request->get('roles', []);

        if (!$listName) {
            return $this->view(null, Codes::HTTP_BAD_REQUEST);
        }
        // todo: check that $groupIds are subgroups (or same group) of given $groupId
        if (!count($groupIds)) {
            return $this->view(null, Codes::HTTP_BAD_REQUEST);
        }
        // todo: check that given roles are valid

        $list = $this->get('bns.user_directory.distribution_list_manager')->create($groupId, $groupIds, $roles);

        // no groups were added, some params are wrong
        if (!$list->getDistributionListGroups()->count()) {
            return $this->view(null, Codes::HTTP_BAD_REQUEST);
        }

        $list->setName($listName);
        $list->save();
        //ToDo add nb of users

        return $this->view($list, Codes::HTTP_OK);
    }

    /**
     * <pre>
     * {"name" : "My wonderful Distribution List",
     * "group_ids" : [1, 2, 3],
     * "roles" : ["TEACHER", "DIRECTOR"]}
     * </pre>
     *
     * @ApiDoc(
     *  section="Annuaire utilisateurs - Listes de diffusion",
     *  resource = true,
     *  description="Edit a group's distribution list",
     * )
     *
     * @Rest\Patch("/distribution-lists/{id}")
     * @Rest\View(serializerGroups={"Default","detail"})
     *
     * @param Integer $id
     * @param Request $request
     *
     * @return array
     */
    public function editDistributionListAction($id, Request $request)
    {
        $newName = $request->get('name');
        $groupIds = $request->get('group_ids', []);
        $roles = $request->get('roles', []);

        if ($this->container->hasParameter('check_group_enabled') && $this->getParameter('check_group_enabled')) {
            $groupIds = GroupQuery::create()
                ->filterByEnabled(true)
                ->filterById($groupIds)
                ->select(['Id'])
                ->find()->toArray();
        }

        $list = DistributionListQuery::create()
            ->filterById($id)
            ->findOne();
        if (!$this->canAccessGroup($list->getGroupId())) {
            throw $this->createAccessDeniedException();
        }
        //ToDo add nb of users

        if (!$list) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        if ($newName) {
            $list->setName($newName)
                ->save();
        }

        // todo: check that $groupIds are subgroups (or same group) of list's group
        // todo: check that given roles are valid
        $this->get('bns.user_directory.distribution_list_manager')->edit($list, $groupIds, $roles);

        return $list;
    }

    protected function canAccessGroup($groupId)
    {
        return $this->get('bns.right_manager')->hasRight('CAMPAIGN_ACCESS', $groupId)
            || $this->get('bns.right_manager')->hasRight('PORTAL_ACCESS_BACK', $groupId)
            || $this->get('bns.right_manager')->hasRight('MINISITE_ACCESS_BACK', $groupId)
        ;
    }
}
