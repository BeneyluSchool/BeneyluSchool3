<?php
namespace BNS\App\CoreBundle\ApiController;

use BNS\App\CoreBundle\Controller\BaseApiController;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcherInterface;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class CacheApiController extends BaseApiController
{
    /**
     * @ApiDoc(
     *  section="Cache",
     *  resource = true,
     *  description="Reset user cache",
     * )
     *
     * @Rest\Post("/reset/users/{username}")
     */
    public function postResetCacheUserAction($username)
    {
        $this->get('bns.api')
            ->setClearExternalCache(false)
            ->resetUser($username)
        ;

        return View::create('', Codes::HTTP_OK);
    }

    /**
     * @ApiDoc(
     *  section="Cache",
     *  resource = true,
     *  description="Reset group cache",
     * )
     *
     * @Rest\QueryParam(name="with_parent", requirements="0|1", nullable=true)
     *
     * @Rest\Post("/reset/groups/{id}", requirements={"id":"\d+"})
     */
    public function postResetCacheGroupAction($id, ParamFetcherInterface $paramFetcher)
    {
        $this->get('bns.api')
            ->setClearExternalCache(false)
            ->resetGroup((int)$id, (boolean)$paramFetcher->get('with_parent', true))
        ;

        return View::create('', Codes::HTTP_OK);
    }

    /**
     * @ApiDoc(
     *  section="Cache",
     *  resource = true,
     *  description="Reset groups cache",
     * )
     *
     *
     * @Rest\Post("/reset/groups")
     */
    public function postResetCacheGroupsAction(Request $request)
    {
        $groupIds = $request->request->get('groupIds');
        if (!is_array($groupIds)) {
            return View::create('', Response::HTTP_BAD_REQUEST);
        }

        $this->get('bns.api')
            ->setClearExternalCache(false)
            ->resetGroups(array_map(function($item){
                return (int) $item;
            }, $groupIds));
        ;

        return View::create('', Codes::HTTP_OK);
    }

    /**
     * @ApiDoc(
     *  section="Cache",
     *  resource = true,
     *  description="Reset Group's users cache",
     * )
     *
     * @Rest\QueryParam(name="with_rights", requirements="0|1", nullable=true)
     * @Rest\QueryParam(name="with_sub_groups", requirements="0|1", nullable=true)
     *
     * @Rest\Post("/reset/groups/{id}/users", requirements={"id":"\d+"})
     */
    public function postResetCacheGroupUsersAction($id, ParamFetcherInterface $paramFetcher)
    {
        $this->get('bns.api')->setClearExternalCache(false)->resetGroupUsers(
            (int)$id,
            (boolean)$paramFetcher->get('with_rights', true),
            (boolean)$paramFetcher->get('with_sub_groups', true)
        );

        return View::create('', Codes::HTTP_OK);
    }

    /**
     * @ApiDoc(
     *  section="Cache",
     *  resource = true,
     *  description="Reset partnership cache",
     * )
     *
     * @Rest\Post("/reset/partnerships/{uid}")
     */
    public function postResetCachePartnershipAction($uid)
    {
        if (!preg_match('/^[a-zA-Z0-9]*$/', $uid)) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        $this->get('bns.api')
            ->setClearExternalCache(false)
            ->resetPartnership($uid)
        ;

        return View::create('', Codes::HTTP_OK);
    }

    /**
     * @ApiDoc(
     *  section="Cache",
     *  resource = true,
     *  description="Reset group type cache",
     * )
     *
     * @Rest\Post("/reset/group-types/{id}", requirements={"id":"\d+"})
     */
    public function postResetCacheGroupTypeAction($id)
    {
        $this->get('bns.api')
            ->setClearExternalCache(false)
            ->resetGroupType((int)$id)
        ;

        return View::create('', Codes::HTTP_OK);
    }

    /**
     * @ApiDoc(
     *  section="Cache",
     *  resource = true,
     *  description="Reset module cache",
     * )
     *
     * @Rest\Post("/reset/modules/{id}", requirements={"id":"\d+"})
     */
    public function postResetCacheModuleAction($id)
    {
        $this->get('bns.api')
            ->setClearExternalCache(false)
            ->resetModule((int)$id)
        ;

        return View::create('', Codes::HTTP_OK);
    }

    /**
     * @ApiDoc(
     *  section="Cache",
     *  resource = true,
     *  description="Reset rank cache",
     * )
     *
     * @Rest\Post("/reset/ranks/{uniqueName}")
     */
    public function postResetCacheRankAction($uniqueName)
    {
        if (!preg_match('/^[a-zA-Z_]*$/', $uniqueName)) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        $this->get('bns.api')->setClearExternalCache(false)->resetRank($uniqueName);

        return View::create('', Codes::HTTP_OK);
    }

    /**
     * @ApiDoc(
     *  section="Cache",
     *  resource = true,
     *  description="Reset rank cache",
     * )
     *
     * @Rest\Post("/reset/rules/{id}", requirements={"id":"\d+"})
     */
    public function postResetCacheRuleAction($id)
    {
        $this->get('bns.api')->setClearExternalCache(false)->resetRule((int) $id);

        return View::create('', Codes::HTTP_OK);
    }

    /**
     * @ApiDoc(
     *  section="Cache",
     *  resource = true,
     *  description="Reset partnership's belongs cache",
     * )
     *
     * @Rest\Post("/reset/partnerships/{id}/belongs", requirements={"id":"\d+"})
     */
    public function postResetCachePartnershipBelongAction($id)
    {
        $this->get('bns.api')
            ->setClearExternalCache(false)
            ->resetPartnershipsGroupBelongs((int) $id)
        ;

        return View::create('', Codes::HTTP_OK);
    }

    /**
     * @ApiDoc(
     *  section="Cache",
     *  resource = true,
     *  description="Reset partnership's members cache",
     * )
     *
     * @Rest\Post("/reset/partnerships/{uid}/members")
     */
    public function postResetCachePartnershipMembersAction($uid)
    {
        if (!preg_match('/^[a-zA-Z0-9]*$/', $uid)) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        $this->get('bns.api')
            ->setClearExternalCache(false)
            ->resetPartnershipsGroupBelongs($uid)
        ;

        return View::create('', Codes::HTTP_OK);
    }

    /**
     * @ApiDoc(
     *  section="Cache",
     *  resource = true,
     *  description="Reset partnership's members cache",
     * )
     *
     * @Rest\Post("/reset/partnerships/{uid}/read")
     */
    public function postResetCachePartnershipReadAction($uid)
    {
        if (!preg_match('/^[a-zA-Z0-9]*$/', $uid)) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }
        $this->get('bns.api')
            ->setClearExternalCache(false)
            ->resetPartnershipsGroupBelongs($uid)
        ;

        return View::create('', Codes::HTTP_OK);
    }

}
