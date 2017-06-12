<?php
namespace BNS\App\UserDirectoryBundle\ApiController;

use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\UserDirectoryBundle\Form\Type\DistributionListGroupType;
use BNS\App\UserDirectoryBundle\Model\DistributionList;
use BNS\App\UserDirectoryBundle\Model\DistributionListGroup;
use BNS\App\UserDirectoryBundle\Model\DistributionListGroupQuery;
use BNS\App\UserDirectoryBundle\Model\DistributionListQuery;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Request;

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
     * @Rest\View(serializerGroups={"Default","list"})
     *
     *
     * @param Integer $groupId
     * @return array
     */
    public function getDistributionListsAction($groupId)
    {
        if (!$this->get('bns.right_manager')->hasRight('CAMPAIGN_ACCESS', $groupId)) {
            return $this->view(null, Codes::HTTP_FORBIDDEN);
        }

        return DistributionListQuery::create()
            ->filterByGroupId($groupId)
            ->find()
        ;
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

        // todo: check access to each list
        $lists = DistributionListQuery::create()
            ->filterById($ids)
            ->find()
        ;

        return $lists;
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
        if (!count($roles)) {
            return $this->view(null, Codes::HTTP_BAD_REQUEST);
        }

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

        // todo: check access
        $list = DistributionListQuery::create()
            ->filterById($id)
            ->findOne();
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
}
