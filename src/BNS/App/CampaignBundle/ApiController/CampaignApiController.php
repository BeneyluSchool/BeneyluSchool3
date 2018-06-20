<?php
namespace BNS\App\CampaignBundle\ApiController;

use BNS\App\CampaignBundle\Form\Type\CampaignType;
use BNS\App\CampaignBundle\Model\Campaign;
use BNS\App\CampaignBundle\Model\CampaignDistributionList;
use BNS\App\CampaignBundle\Model\CampaignDistributionListQuery;
use BNS\App\CampaignBundle\Model\CampaignPeer;
use BNS\App\CampaignBundle\Model\CampaignRecipient;
use BNS\App\CampaignBundle\Model\CampaignRecipientGroup;
use BNS\App\CampaignBundle\Model\CampaignRecipientGroupQuery;
use BNS\App\CampaignBundle\Model\CampaignRecipientQuery;
use BNS\App\CampaignBundle\Model\CampaignQuery;
use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\UserDirectoryBundle\Model\DistributionList;
use BNS\App\UserDirectoryBundle\Model\DistributionListGroup;
use BNS\App\UserDirectoryBundle\Model\DistributionListGroupQuery;
use BNS\App\UserDirectoryBundle\Model\DistributionListQuery;
use Doctrine\Common\Collections\Criteria;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcherInterface;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Hateoas\Configuration\Route;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Julie Boisnard <julie.boisnard@pixel-cookers.com>
 */
class CampaignApiController extends BaseApiController
{
    /**
     * @ApiDoc(
     *  section="Campaigns",
     *  resource = true,
     *  description="Get group's campaigns",
     * )
     *
     * @Rest\QueryParam(name="page", requirements="\d+", description="current page", default="1")
     * @Rest\QueryParam(name="limit", requirements="\d+", description="number of elements per page", default="10")
     * @Rest\Get("/groups/{groupId}/campaigns")
     * @Rest\View(serializerGroups={"Default","detail"})
     *
     *
     * @param Integer $groupId
     * @param ParamFetcherInterface $paramFetcher
     *
     * @return array
     */
    public function getCampaignsAction($groupId, ParamFetcherInterface $paramFetcher)
    {
        //did this user has right to access the campaign module?
        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('CAMPAIGN_ACCESS', $groupId)) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

           /*@var CampaignQuery $query*/
        $query = CampaignQuery::create()
            ->filterByGroupId($groupId)
            ->filterByArchived(false)
            ->orderById(Criteria::DESC)
        ;

        return $this->getPaginator($query,
            new Route('campaign_api_get_campaigns', [
                'groupId' => $groupId,
                'version' => $this->getVersion()
            ], true), $paramFetcher);

    }

    /**
     * @ApiDoc(
     *  section="Campaigns",
     *  resource = true,
     *  description="Get group's campaigns",
     * )
     *
     * @Rest\QueryParam(name="page", requirements="\d+", description="current page", default="1")
     * @Rest\QueryParam(name="limit", requirements="\d+", description="number of elements per page", default="10")
     * @Rest\QueryParam(name="status",  description="filter campaign by status")
     * @Rest\QueryParam(name="types",  description="filter campaign by types")
     * @Rest\Get("/groups/{groupId}/campaigns/search")
     * @Rest\View(serializerGroups={"Default","detail"})
     *
     *
     * @param Integer $groupId
     * @param ParamFetcherInterface $paramFetcher
     * @param String $search
     * @param array $types
     *
     * @return array
     */
    public function searchCampaignsAction($groupId, ParamFetcherInterface $paramFetcher, Request $request)
    {
        $status = $paramFetcher->get('status');
        $types = $paramFetcher->get('types');
        $search = $request->get('search');
        //did this user has right to access the campaign module?
        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('CAMPAIGN_ACCESS', $groupId)) {
            return $this->view('Forbidden', Codes::HTTP_BAD_REQUEST);
        }


        /** @var CampaignQuery $query */
        $query = CampaignQuery::create()
            ->_if($search)
            ->filterByName('%'.$search.'%')
            ->_or()
            ->filterByTitle('%'.$search.'%')
            ->_or()
            ->filterByMessage('%'.$search.'%')
            ->_endif()
            ->filterByGroupId($groupId)
            ->filterByArchived(false)

        ;

        if ($types) {
            $campaignType = explode(',', $types);

            $constant = [];
            foreach ($campaignType as $type) {
                $constant[] = constant('BNS\App\CampaignBundle\Model\CampaignPeer::CLASSKEY_CAMPAIGN'.strtoupper($type));
            }
            $query->filterByType($constant);
        }

        if ($status) {
            $campaignStatus = explode(',', $status);

            $query->filterByStatus($campaignStatus);
        }

        return $this->getPaginator($query->orderById(Criteria::DESC),
            new Route('campaign_api_search_campaigns', [
                'groupId' => $groupId,
                'version' => $this->getVersion()
            ], true), $paramFetcher);

    }

    /**
     * <pre>
     * { "type" : "email" }
     * </pre>
     *
     * @ApiDoc(
     *  section="Campaigns",
     *  resource = true,
     *  description="Create a group's campaign and return campaign object",
     *
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Les données soumises sont invalides"
     *  }
     * )
     *
     * @Rest\Post("/groups/{groupId}/campaigns")
     * @Rest\View(serializerGroups={"Default","detail"})
     *
     * @param Integer $groupId
     * @param Request $request
     *
     * @return array
     */
    public function postAddCampaignAction($groupId, Request $request)
    {
        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('CAMPAIGN_ACCESS', $groupId)) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        $type = $request->get('type');

        if (!$type) {
            return View::create('', Codes::HTTP_BAD_REQUEST);
        }
        $constant = 'BNS\App\CampaignBundle\Model\CampaignPeer::CLASSKEY_CAMPAIGN'.strtoupper($type);
        if ($type && !defined($constant)) {
            return View::create('', Codes::HTTP_BAD_REQUEST);
        }

        if ('EMAIL' === $type && !$this->hasFeature('campaign_email')) {
            throw $this->createAccessDeniedException();
        } elseif ('SMS' === $type && !$this->hasFeature('campaign_sms')) {
            throw $this->createAccessDeniedException();
        }

        $campaign = new Campaign();
        $campaign->setGroupId($groupId)
            ->setStatus(CampaignPeer::STATUS_DRAFT)
            ->setType(constant($constant))
            ->save();

        return $campaign;
    }

    /**
     * <pre>
     * {"ids" : [20,23,24,25,26,27,28]}
     * </pre>
     *
     * @ApiDoc(
     *  section="Campaigns",
     *  resource = true,
     *  description="Delete group's campaigns",
     * )
     *
     * @Rest\Delete("/groups/{groupId}/campaigns")
     * @Rest\View(serializerGroups={"Default","detail"})
     *
     * @param Integer $groupId
     * @param Request $request
     *
     * @return array
     */
    public function deleteCampaignsAction($groupId, Request $request)
    {
        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('CAMPAIGN_ACCESS', $groupId)) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        $ids = $request->get('ids');

        if ($ids && is_array($ids)) {
            foreach ($ids as $id) {
                $campaign = CampaignQuery::create()
                    ->filterByGroupId($groupId)
                    ->filterById($id)
                    ->findOne();
                $campaign->setArchived(true)
                    ->save();
            }

            return View::create('', Codes::HTTP_OK);
        }

        return View::create('', Codes::HTTP_NOT_FOUND);
    }

    /**
     * @ApiDoc(
     *  section="Campaigns",
     *  resource = true,
     *  description="Get campaigns's credit",
     * )
     *
     * @Rest\Get("/groups/{groupId}/campaigns/credit")
     * @Rest\View(serializerGroups={"Default","detail"})
     *
     * @param Integer $groupId
     *
     * @return array
     */
    public function getCampaignsCreditAction($groupId)
    {
        $group = GroupQuery::create()->findPk($groupId);
        if (!$group) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('CAMPAIGN_ACCESS', $group->getId())) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        $res = $this->get('bns_app_campain.paas_sms_manager')->getSmsCredit($group);
        if ($res && isset($res->balance)) {
            return (array) $res;
        }

        return View::create('', Codes::HTTP_BAD_REQUEST);
    }

    /**
     * @ApiDoc(
     *  section="Campaigns",
     *  resource = true,
     *  description="Get campaigns's credit cost",
     * )
     *
     * @Rest\QueryParam(name="ids", requirements="\d+", description="users ids")
     * @Rest\QueryParam(name="message", requirements="\w+", description="campaing message")
     * @Rest\Post("/campaigns/{campaignId}/cost")
     * @Rest\View(serializerGroups={"Default","detail"})
     *
     * @param Integer $campaignId
     *
     * @return array
     */
    public function getCampaignsCreditCostAction($campaignId, Request $request)
    {
        $campaign = CampaignQuery::create()->findPk($campaignId);
        if (!$campaign) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        $message = $request->get('message');
        $campaign->setMessage($message);

        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('CAMPAIGN_ACCESS', $campaign->getGroupId())) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }
        $country = $this->get('bns.group_manager')->setGroup($campaign->getGroup())->getCountry();

        // TODO use cache
        $ids = $this->get('bns_app_campaign.campaign_manager')->getUniqueRecipientIds($campaign);

        $res = $this->get('bns_app_campain.paas_sms_manager')->getSmsCost($campaign, $ids, $country);
        if ($res) {
            return (array) $res;
        }

        return View::create('', Codes::HTTP_BAD_REQUEST);
    }



    /**
     * @ApiDoc(
     *  section="Campaigns",
     *  resource = true,
     *  description="Get one group's campaign",
     * )
     *
     * @Rest\Get("/campaigns/{campaignId}")
     * @Rest\View(serializerGroups={"Default", "list", "campaign_attachment", "user_detail", "campaign_detail"})
     *
     * @param Integer $campaignId
     *
     * @return array
     */
    public function getCampaignAction($campaignId)
    {
        $campaign = CampaignQuery::create()
            ->filterByArchived(false)
            ->filterById($campaignId)
            ->findOne();

        if (!$campaign) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        $campaign->getStatus();

        $groupId = $campaign->getGroupId();

        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('CAMPAIGN_ACCESS', $groupId)) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        if ('EMAIL' === $campaign->getTypeName() && !$this->hasFeature('campaign_email')) {
            throw $this->createAccessDeniedException();
        } elseif ('SMS' === $campaign->getTypeName() && !$this->hasFeature('campaign_sms')) {
            throw $this->createAccessDeniedException();
        }

        $recipients = UserQuery::create()
            ->useCampaignRecipientQuery()
                ->filterByIsDirect(true)
                ->filterByCampaignId($campaign->getId())
            ->endUse()
            ->find()
        ;

        $messagingAccess = true;
        foreach ($recipients as $user) {
            if (!$this->get('bns.user_manager')->setUser($user)->hasRightSomeWhere('MESSAGING_ACCESS')) {
                $messagingAccess = false;
                break;
            }
        }

        $recipientGroups = CampaignRecipientGroupQuery::create()
            ->filterByCampaignId($campaign->getId())
            ->joinGroupType()
            ->joinWith('Group')
            ->withColumn('GroupType.Type', 'role')
            ->find()
            ->getArrayCopy()
        ;

        $recipientGroups = array_map(function($item){
            return [
                'id' => $item->getGroupId().$item->getRole(),
                'group' => $item->getGroup(),
                'type' => $item->getRole(),
            ];
        }, $recipientGroups);


        return array(
            'campaign' => $campaign,
            'recipients' => $recipients,
            'recipient_groups' => $recipientGroups,
            'recipient_lists' => CampaignDistributionListQuery::create()
                ->filterByCampaignId($campaign->getId())
                ->find(),
            'messaging_access' => $messagingAccess
        );
    }

    /**
     * <pre>
     * {"message" : "My awesome message",
     * "scheduled_at" : "2016-03-15 11:52:22" }
     * </pre>
     *
     * @ApiDoc(
     *  section="Campaigns",
     *  resource = true,
     *  description="Edit one group's campaign",
     *
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Les données soumises sont invalides"
     *  }
     * )
     *
     * @Rest\Patch("/campaigns/{campaignId}")
     * @Rest\View(serializerGroups={"Default","detail"})
     *
     * @param Integer $campaignId
     *
     *
     */
    public function editCampaignAction(Request $request, $campaignId)
    {
        $campaign = CampaignQuery::create()
            ->filterByArchived(false)
            ->filterById($campaignId)
            ->findOne();

        if (!$campaign) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        $groupId = $campaign->getGroupId();

        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('CAMPAIGN_ACCESS', $groupId)) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        if ('EMAIL' === $campaign->getTypeName() && !$this->hasFeature('campaign_email')) {
            throw $this->createAccessDeniedException();
        } elseif ('SMS' === $campaign->getTypeName() && !$this->hasFeature('campaign_sms')) {
            throw $this->createAccessDeniedException();
        }

        $campaignStatus = $campaign->getStatus();

        if ($campaignStatus != 'DRAFT' && $campaignStatus != 'SCHEDULED') {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        $mediaManager = $this->get('bns.media.manager');

        return $this->restForm(new CampaignType(), $campaign, array(
            'csrf_protection' => false,
        ), null, function($object) use ($request, $mediaManager) {
            /** @var Campaign $object */
            $object->save();
            $mediaManager->saveAttachments($object, $request);
        });
    }

    /**
     * @ApiDoc(
     *  section="Campaigns",
     *  resource = true,
     *  description="Duplicate one group's campaign",
     * )
     *
     * @Rest\Post("/campaigns/{campaignId}/copy")
     * @Rest\View(serializerGroups={"Default","detail"})
     *
     * @param Integer $campaignId
     *
     * @return array
     */
    public function copyCampaignAction($campaignId)
    {
        $campaign = CampaignQuery::create()
            ->filterByArchived(false)
            ->filterById($campaignId)
            ->findOne();

        if (!$campaign) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        $groupId = $campaign->getGroupId();

        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('CAMPAIGN_ACCESS', $groupId)) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        if ('EMAIL' === $campaign->getTypeName() && !$this->hasFeature('campaign_email')) {
            throw $this->createAccessDeniedException();
        } elseif ('SMS' === $campaign->getTypeName() && !$this->hasFeature('campaign_sms')) {
            throw $this->createAccessDeniedException();
        }

        $campaignRecipients = CampaignRecipientQuery::create()
            ->filterByCampaignId($campaignId)
            ->filterByIsDirect(true)
            ->find();

        $campaignRecipientGroups = CampaignRecipientGroupQuery::create()
            ->filterByCampaignId($campaignId)
            ->find();

        $campaignLists = CampaignDistributionListQuery::create()
            ->filterByCampaignId($campaignId)
            ->find();

        $campaignCopy = $campaign->copy();
        $campaignCopy->setStatus(CampaignPeer::STATUS_DRAFT)->save();

        if ($campaign->getType() != 'SMS') {
            $campaignCopy->setTitle($campaign->getTitle());
        }

        $campaignCopy->save();

        if ($campaign->getType() != 'SMS') {
            // add attachment after save, so our campaign copy has an id
            foreach ($campaign->getResourceAttachments() as $media) {
                $attachment = $campaignCopy->addResourceAttachment($media->getId());
                $attachment->save();
            }
        }

        if ($campaignRecipients) {
            foreach ($campaignRecipients as $campaignRecipient) {
                $campaignRecipientCopy = new CampaignRecipient();
                $campaignRecipientCopy->setUserId($campaignRecipient->getUserId())
                    ->setCampaignId($campaignCopy->getId())
                    ->save();
            }
        }
        if ($campaignRecipientGroups) {
            foreach ($campaignRecipientGroups as $campaignRecipientGroup) {
                $campaignRecipientGroupCopy = new CampaignRecipientGroup();
                $campaignRecipientGroupCopy->setGroupId($campaignRecipientGroup->getGroupId())
                    ->setCampaignId($campaignCopy->getId())
                    ->setRoleId($campaignRecipientGroup->getRoleId())
                    ->save();
            }
        }

        if ($campaignLists) {
            foreach ($campaignLists as $campaignList) {
                $campaignListCopy = new CampaignDistributionList();
                $campaignListCopy->setCampaignId($campaignCopy->getId())
                    ->setDistributionListId($campaignList->getDistributionListId())
                    ->save();
            }
        }

        return array (
            'campaign_id' => $campaignCopy->getId()
        );
    }

    /**
     * @ApiDoc(
     *  section="Campaigns",
     *  resource = true,
     *  description="Send one group's campaign",
     * )
     *
     * @Rest\Post("/campaigns/{campaignId}/send")
     * @Rest\View(serializerGroups={"Default","detail"})
     *
     * @param Integer $campaignId
     *
     * @return array
     */
    public function sendCampaignAction($campaignId)
    {
        $campaign = CampaignQuery::create()
            ->filterByStatus(CampaignPeer::STATUS_DRAFT)
            ->findPk($campaignId)
        ;
        if (!$campaign) {
            return View::create(null, Codes::HTTP_NOT_FOUND);
        }
        if (!$this->get('bns.right_manager')->hasRight('CAMPAIGN_ACCESS', $campaign->getGroupId())) {
            return View::create(null, Codes::HTTP_FORBIDDEN);
        }

        if ('EMAIL' === $campaign->getTypeName() && !$this->hasFeature('campaign_email')) {
            throw $this->createAccessDeniedException();
        } elseif ('SMS' === $campaign->getTypeName() && !$this->hasFeature('campaign_sms')) {
            throw $this->createAccessDeniedException();
        }

        $this->get('bns_app_campaign.campaign_manager')->send($campaign);

        return View::create(null, Codes::HTTP_NO_CONTENT);
    }

    /**
     * <pre>
     * { "users_id" : [1, 2] }
     * </pre>
     *
     * @ApiDoc(
     *  section="Campaigns",
     *  resource = true,
     *  description="Add campaign's recipients",
     * )
     *
     * @Rest\Post("/campaigns/{campaignId}/recipients")
     * @Rest\View(serializerGroups={"Default","detail"})
     *
     * @param Integer $campaignId
     * @param Request $request
     *
     * @return array
     */
    public function postAddCampaignRecipientsAction($campaignId, Request $request)
    {
        $recipientIds = $request->get('users_id');

        $campaign = CampaignQuery::create()
            ->filterByArchived(false)
            ->filterById($campaignId)
            ->findOne();

        if (!$campaign) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        $groupId = $campaign->getGroupId();

        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('CAMPAIGN_ACCESS', $groupId) || !$rightManager->hasRight('CAMPAIGN_VIEW_INDIVIDUAL_USER', $groupId)) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        $campaignStatus = $campaign->getStatus();

        if ($campaignStatus != 'DRAFT' && $campaignStatus != 'SCHEDULED') {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        $groupManager = $this->get('bns.group_manager');
        $groupManager->setGroupById($groupId);
        $userIds = $groupManager->getUsersIds();

        // allow only users that are in the group
        $recipientIds = array_intersect($recipientIds, $userIds);

        if ($recipientIds && is_array($recipientIds)) {
            foreach ($recipientIds as $id) {
                $recipient = CampaignRecipientQuery::create()
                    ->filterByUserId($id)
                    ->filterByCampaignId($campaignId)
                    ->findOneOrCreate();

                if ($recipient->isNew()) {
                    $recipient->save();
                }
            }

            $this->get('bns_app_campaign.campaign_manager')->updateUniqueRecipients($campaign);
            $campaign->save();

            return View::create('', Codes::HTTP_OK);
        }

        return View::create('', Codes::HTTP_NOT_FOUND);
    }

    /**
     * <pre>
     * { "users_id" : [1, 2] }
     * </pre>
     *
     * @ApiDoc(
     *  section="Campaigns",
     *  resource = true,
     *  description="Delete campaign's recipients",
     * )
     *
     * @Rest\Delete("/campaigns/{campaignId}/recipients")
     * @Rest\View(serializerGroups={"Default","detail"})
     *
     * @param Integer $campaignId
     * @param Request $request
     *
     * @return array
     */
    public function deleteCampaignRecipientsAction($campaignId, Request $request)
    {
        $recipientIds = $request->get('users_id');

        $campaign = CampaignQuery::create()
            ->filterByArchived(false)
            ->filterById($campaignId)
            ->findOne();

        if (!$campaign) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        $groupId = $campaign->getGroupId();

        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('CAMPAIGN_ACCESS', $groupId) || !$rightManager->hasRight('CAMPAIGN_VIEW_INDIVIDUAL_USER', $groupId)) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        $campaignStatus = $campaign->getStatus();

        if ($campaignStatus != 'DRAFT' && $campaignStatus != 'SCHEDULED') {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        if ($recipientIds && is_array($recipientIds)) {
            CampaignRecipientQuery::create()
                ->filterByUserId($recipientIds)
                ->filterByCampaignId($campaignId)
                ->delete();

            $this->get('bns_app_campaign.campaign_manager')->updateUniqueRecipients($campaign);
            $campaign->save();

            return View::create('', Codes::HTTP_OK);
        }

        return View::create('', Codes::HTTP_NOT_FOUND);
    }

    /**
     * <pre>
     * { "groups" : [ { "group_id" : 1 , "role" : DIRECTOR } ,
     * { "group_id" : 1 , "role" : TEACHER } ] }
     * </pre>
     *
     * @ApiDoc(
     *  section="Campaigns",
     *  resource = true,
     *  description="Add campaign's recipients_groups",
     * )
     *
     * @Rest\Post("/campaigns/{campaignId}/recipients_groups")
     * @Rest\View(serializerGroups={"Default","detail"})
     *
     * @param Integer $campaignId
     * @param Request $request
     *
     * @return array
     */
    public function postAddCampaignRecipientsGroupsAction($campaignId, Request $request)
    {
        $groups = $request->get('groups');

        $campaign = CampaignQuery::create()
            ->filterByArchived(false)
            ->filterById($campaignId)
            ->findOne();

        if (!$campaign) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        $groupId = $campaign->getGroupId();

        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('CAMPAIGN_ACCESS', $groupId)) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        $campaignStatus = $campaign->getStatus();

        if ($campaignStatus != 'DRAFT' && $campaignStatus != 'SCHEDULED') {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        $groupManager = $this->get('bns.group_manager')->setGroupById($groupId);
        $subgroupIds = $groupManager->getOptimisedAllSubGroupIds($groupId);
        $subgroupIds[] = $groupId;
        // limit only subgroup of $groupId and restrict CLASSROOM / TEAM (Allow classroom only if has right "CAMPAIGN_VIEW_CLASSROOM"

        $restrictedGroupType = ['TEAM'];
        if (!$rightManager->hasRight('CAMPAIGN_VIEW_CLASSROOM', $groupId)) {
            $restrictedGroupType[] = 'CLASSROOM';
        }
        $excludedRoleIds = GroupTypeQuery::create()
            ->filterBySimulateRole(true)
            ->filterByType(['PUPIL'])
            ->select(array('Id'))
            ->find()
            ->getArrayCopy()
        ;

        $roles = GroupTypeQuery::create()
            ->filterBySimulateRole(true)
            ->select(['Id', 'Type'])
            ->find()
            ->getArrayCopy('Type')
        ;

        if ($groups && is_array($groups)) {
            foreach ($groups as $group) {
                if ($group['role'] && array_key_exists($group['role'], $roles)) {
                    $group['role_id'] = $roles[$group['role']]['Id'];
                } else {
                    // no valid role name
                    continue;
                }
                if (!in_array($group['group_id'], $subgroupIds)
                    || in_array($group['role_id'], $excludedRoleIds)
                    || GroupQuery::create()
                        ->filterById($group['group_id'])
                        ->useGroupTypeQuery()
                            ->filterByType($restrictedGroupType, \Criteria::NOT_IN)
                        ->endUse()
                    ->count() <= 0
                ) {
                    continue;
                }

                $recipient_group = CampaignRecipientGroupQuery::create()
                    ->filterByGroupId($group['group_id'])
                    ->filterByRoleId($group['role_id'])
                    ->filterByCampaignId($campaignId)
                    ->findOneOrCreate();

                if ($recipient_group->isNew()) {
                    $recipient_group->save();
                }
            }

            $this->get('bns_app_campaign.campaign_manager')->updateUniqueRecipients($campaign);
            $campaign->save();

            return View::create('', Codes::HTTP_OK);
        }

        return View::create('', Codes::HTTP_NOT_FOUND);
    }

    /**
     * <pre>
     *{ "groups" : [ { "group_id" : 1 , "role_id" : 7 } ,
     * { "group_id" : 1 , "role_id" : 5 } ] }
     * </pre>
     *
     * @ApiDoc(
     *  section="Campaigns",
     *  resource = true,
     *  description="Delete campaign's recipients_groups",
     * )
     *
     * @Rest\Delete("/campaigns/{campaignId}/recipients_groups")
     * @Rest\View(serializerGroups={"Default","detail"})
     *
     * @param Integer $campaignId
     * @param Request $request
     *
     * @return array
     */
    public function deleteCampaignRecipientsGroupsAction($campaignId, Request $request)
    {
        $groups = $request->get('groups');

        $campaign = CampaignQuery::create()
            ->filterByArchived(false)
            ->filterById($campaignId)
            ->findOne();

        if (!$campaign) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        $groupId = $campaign->getGroupId();

        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('CAMPAIGN_ACCESS', $groupId)) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        $campaignStatus = $campaign->getStatus();

        if ($campaignStatus != 'DRAFT' && $campaignStatus != 'SCHEDULED') {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        if ($groups && is_array($groups)) {
            foreach ($groups as $group) {
                list($groupId, $role) = explode('_', $group);
                $roleId = GroupTypeQuery::create()
                    ->filterBySimulateRole(true)
                    ->filterByType($role)
                    ->select('Id')
                    ->findOne()
                ;

                CampaignRecipientGroupQuery::create()
                    ->filterByGroupId($groupId)
                    ->filterByRoleId($roleId)
                    ->filterByCampaignId($campaignId)
                    ->delete();
            }

            $this->get('bns_app_campaign.campaign_manager')->updateUniqueRecipients($campaign);
            $campaign->save();

            return View::create('', Codes::HTTP_OK);
        }

        return View::create('', Codes::HTTP_NOT_FOUND);
    }

    /**
     * <pre>
     * { "list_ids" : [1, 2] }
     * </pre>
     *
     * @ApiDoc(
     *  section="Campaigns",
     *  resource = true,
     *  description="Add campaign's diffusion list",
     * )
     *
     * @Rest\Post("/campaigns/{campaignId}/recipients_lists")
     * @Rest\View(serializerGroups={"Default","detail"})
     *
     * @param Integer $campaignId
     * @param Request $request
     *
     * @return array
     */
    public function postAddCampaignRecipientsListsAction($campaignId, Request $request)
    {
        $lists = $request->get('list_ids');

        $campaign = CampaignQuery::create()
            ->filterByArchived(false)
            ->filterById($campaignId)
            ->findOne();

        if (!$campaign) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        $groupId = $campaign->getGroupId();

        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('CAMPAIGN_ACCESS', $groupId)) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        $campaignStatus = $campaign->getStatus();

        if ($campaignStatus != 'DRAFT' && $campaignStatus != 'SCHEDULED') {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        // Allow only distribution list that are for this group or child group
        $groupIds = $this->get('bns.group_manager')->getOptimisedAllSubGroupIds($groupId);
        $groupIds[] = $groupId;

        // allow only distribution list if we have rights for their group
        $groupIds = array_intersect($rightManager->getGroupIdsWherePermission('CAMPAIGN_ACCESS'), $groupIds);

        if ($lists && is_array($lists)) {
            $lists = DistributionListQuery::create()
                ->filterById($lists, \Criteria::IN)
                ->filterByGroupId($groupIds)
                ->select(array('Id'))
                ->find()->getArrayCopy();

            foreach ($lists as $listId) {
                $distributionList = CampaignDistributionListQuery::create()
                    ->filterByDistributionListId($listId)
                    ->filterByCampaignId($campaignId)
                    ->findOneOrCreate();

                if ($distributionList->isNew()) {
                    $distributionList->save();
                }
            }

            $this->get('bns_app_campaign.campaign_manager')->updateUniqueRecipients($campaign);
            $campaign->save();

            return View::create('', Codes::HTTP_OK);
        }

        return View::create('', Codes::HTTP_NOT_FOUND);
    }

    /**
     * <pre>
     * { "list_ids" : [1, 2] }
     * </pre>
     *
     * @ApiDoc(
     *  section="Campaigns",
     *  resource = true,
     *  description="Delete campaign's diffusion list",
     * )
     *
     * @Rest\Delete("/campaigns/{campaignId}/recipients_lists")
     * @Rest\View(serializerGroups={"Default","detail"})
     *
     * @param Integer $campaignId
     * @param Request $request
     *
     * @return array
     */
    public function deleteCampaignRecipientsListsAction($campaignId, Request $request)
    {
        $lists = $request->get('list_ids');

        $campaign = CampaignQuery::create()
            ->filterByArchived(false)
            ->filterById($campaignId)
            ->findOne();

        if (!$campaign) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        $groupId = $campaign->getGroupId();

        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('CAMPAIGN_ACCESS', $groupId)) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        $campaignStatus = $campaign->getStatus();

        if ($campaignStatus != 'DRAFT' && $campaignStatus != 'SCHEDULED') {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        if ($lists && is_array($lists)) {
            CampaignDistributionListQuery::create()
                ->filterByDistributionListId($lists)
                ->filterByCampaignId($campaignId)
                ->delete();

            $this->get('bns_app_campaign.campaign_manager')->updateUniqueRecipients($campaign);
            $campaign->save();

            return View::create('', Codes::HTTP_OK);
        }

        return View::create('', Codes::HTTP_NOT_FOUND);
    }

    /**
     * @ApiDoc(
     *  section="Campaigns",
     *  resource = true,
     *  description="Get distribution list and group details of a campaign",
     * )
     *
     * @Rest\Get("/campaigns/{id}/details")
     * @Rest\View(serializerGroups={"detail", "list"})
     *
     * @param Campaign $campaign
     *
     * @return array
     */
    public function getDetailsAction(Campaign $campaign)
    {
        $rightManager = $this->get('bns.right_manager');
        $groupManager = $this->get('bns.group_manager');

        if ($campaign && !$campaign->getArchived()) {
            $groupId = $campaign->getGroupId();
            if (!$rightManager->hasRight('CAMPAIGN_ACCESS', $groupId)) {
                return View::create('', Codes::HTTP_FORBIDDEN);
            }

            $groupIds = [];
            $listGroups = [];

            //For Recipient lists
            $recipientLists = CampaignDistributionListQuery::create()
                ->filterByCampaignId($campaign->getId())
                ->find();

            /*@var CampaignDistributionList $list*/
            foreach ($recipientLists as $list) {
                $listGroups = DistributionListGroupQuery::create()
                    ->filterByDistributionList($list->getDistributionList())
                    ->find();

                /*@var DistributionListGroup $listGroup*/
                foreach ($listGroups as $listGroup) {
                    $groupIds[$listGroup->getGroupId()]['roles'][$listGroup->getRoleId()] = $listGroup->getRoleId();
                }
            }

            //For Recipient groups
            $recipientGroups = CampaignRecipientGroupQuery::create()
                ->filterByCampaignId($campaign->getId())
                ->find();

            foreach ($recipientGroups as $recipientGroup) {
                $groupIds[$recipientGroup->getGroupId()]['roles'][$recipientGroup->getRoleId()] = $recipientGroup->getRoleId();
            }


            //Merge group and role
            foreach ($groupIds as $key=>$id) {
                $groupIds[$key]['roles'] = array_unique($groupIds[$key]['roles']);
            }

            foreach ($listGroups as $listGroup) {
                $groupIds[$listGroup->getGroupId()]['group'] = $listGroup->getGroup();
            }

            foreach ($recipientGroups as $recipientGroup) {
                $groupIds[$recipientGroup->getGroupId()]['group'] = $recipientGroup->getGroup();
            }
            $messagingAccess = true;
            foreach ($groupIds as $key => $groupData) {
                foreach ($groupIds[$key]['roles'] as $roleId) {
                    $role = GroupTypeQuery::create()
                        ->findPk($roleId);

                    $listUsers = [];

                    $groupManager->setGroup($groupIds[$key]['group']);
                    $usersFromCentralIds = $groupManager->getUsersByRoleUniqueNameIds($role->getType());
                    $nbUsers = count($usersFromCentralIds);
                    if($rightManager->hasRight('CAMPAIGN_VIEW_INDIVIDUAL_USER', $key)) {
                        $listUsers = UserQuery::create()
                            ->findPks($usersFromCentralIds);
                    }

                    $groupIds[$key]['roles'][$roleId] = [
                        'role' => $role->getType(),
                        'users' => $listUsers,
                        'nb_users' => $nbUsers
                    ];

                    foreach ($listUsers as $user) {
                        if (!$this->get('bns.user_manager')->setUser($user)->hasRightSomeWhere('MESSAGING_ACCESS')) {
                            $messagingAccess = false;
                        }
                    }
                }
            }
            if ($groupIds) {
                array_push($groupIds, ['messaging_access' => $messagingAccess]);
            }
            return array_values($groupIds);
        }

        return View::create('', Codes::HTTP_NOT_FOUND);
    }

    /**
     * @ApiDoc(
     *  section="Campaigns",
     *  resource = true,
     *  description="Get number of recipients of a campaign",
     * )
     *
     * @Rest\Get("/campaigns/{id}/recipients")
     * @Rest\View()
     *
     * @param Campaign $campaign
     *
     * @return array
     */
    public function getRecipientsAction(Campaign $campaign)
    {
        $rightManager = $this->get('bns.right_manager');
        $groupManager = $this->get('bns.group_manager');

        if ($campaign && !$campaign->getArchived()) {
            $groupId = $campaign->getGroupId();
            if (!$rightManager->hasRight('CAMPAIGN_ACCESS', $groupId)) {
                return View::create('', Codes::HTTP_FORBIDDEN);
            }

            $this->get('bns_app_campaign.campaign_manager')->updateUniqueRecipients($campaign);
            $campaign->save();

            return [
                'nb_recipient' => $campaign->getNbRecipient(),
            ];
        }

        return View::create('', Codes::HTTP_NOT_FOUND);
    }
}
