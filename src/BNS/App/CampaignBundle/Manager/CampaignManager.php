<?php
namespace BNS\App\CampaignBundle\Manager;

use BNS\App\CampaignBundle\Exception\ActionDeniedException;
use BNS\App\CampaignBundle\Model\Campaign;
use BNS\App\CampaignBundle\Model\CampaignPeer;
use BNS\App\CampaignBundle\Model\CampaignRecipient;
use BNS\App\CampaignBundle\Model\CampaignRecipientQuery;
use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\UserDirectoryBundle\Manager\DistributionListManager;
use BNS\App\UserDirectoryBundle\Model\DistributionList;
use BNS\App\UserDirectoryBundle\Model\DistributionListGroup;
use BNS\App\UserDirectoryBundle\Model\DistributionListGroupQuery;
use BNS\App\UserDirectoryBundle\Model\DistributionListQuery;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use Psr\Log\LoggerInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class CampaignManager
{
    /** @var  Producer */
    protected $campaignProducer;

    /** @var Producer  */
    protected $campaignMessageProducer;

    /** @var BNSGroupManager  */
    protected $groupManager;

    /** @var LoggerInterface  */
    protected $logger;

    /** @var DistributionListManager  */
    protected $distributionListManager;

    public function __construct(Producer $campaignProducer, Producer $campaignMessageProducer, BNSGroupManager $groupManager, LoggerInterface $logger, DistributionListManager $distributionListManager)
    {
        $this->campaignProducer = $campaignProducer;
        $this->campaignMessageProducer = $campaignMessageProducer;
        $this->groupManager = $groupManager;
        $this->logger = $logger;
        $this->distributionListManager = $distributionListManager;
    }

    public function send(Campaign $campaign)
    {
        // Check Campaign valid status
        if ($campaign->getStatus() !== CampaignPeer::STATUS_DRAFT) {
            $this->logger->error(sprintf('Campaign "%s" with wrong status "%s" cannot be sent', $campaign->getId(), $campaign->getStatus()));

            throw new ActionDeniedException('invalid campaign status');
        }

        if (null !== $campaign->getScheduledAt() && $campaign->getScheduledAt('U') > date('U')) {
            // we have a schedule campaign, we flag it as scheduled and let the cron to trigger it
            $campaign->setStatus(CampaignPeer::STATUS_SCHEDULED);

            return $campaign->save();
        }

        // Create a new Rabbit Message
        $message = [
            'campaign_id' => $campaign->getId()
        ];

        $campaign->setStatus(CampaignPeer::STATUS_WAITING);
        if ($campaign->save()) {
            // Send it
            $this->campaignProducer->publish(serialize($message));

            return true;
        }

        return false;
    }

    public function prepareRecipients(Campaign $campaign)
    {
        if ($campaign->getStatus() !== CampaignPeer::STATUS_WAITING) {
            throw new ActionDeniedException('invalid campaign status');
        }

        // get users from groups/role
        foreach ($campaign->getCampaignRecipientGroups() as $campaignRecipientGroup) {
            $groupId = $campaignRecipientGroup->getGroupId();
            $role = $campaignRecipientGroup->getGroupType()->getType();

            $userIds = $this->groupManager->setGroupById($groupId)->getUsersByRoleUniqueNameIds($role);
            foreach ($userIds as $userId) {
                try {
                    $recipient = new CampaignRecipient();
                    $recipient->setCampaignId($campaign->getId());
                    $recipient->setUserId($userId);
                    $recipient->setIsDirect(false);
                    $recipient->save();
                } catch (\PropelException $e) {
                    // duplicate
                }
            }
        }
        $distributionLists = DistributionListQuery::create()->useCampaignDistributionListQuery()->filterByCampaignId($campaign->getId())->endUse()->find();
        foreach ($distributionLists as $distributionList) {
                $userIds = $this->distributionListManager->getUserIds($distributionList);
            foreach ($userIds as $userId) {
                try {
                    $recipient = new CampaignRecipient();
                    $recipient->setCampaignId($campaign->getId());
                    $recipient->setUserId($userId);
                    $recipient->setIsDirect(false);
                    $recipient->save();
                } catch (\PropelException $e) {
                    // duplicate
                }
            }
        }

        // filter unique recipient base on campaign type
        $userIds = $this->getUniqueUserIds(
            $campaign->getType(),
            CampaignRecipientQuery::create()
                ->filterByCampaignId($campaign->getId(), \Criteria::EQUAL)
                ->select('UserId')
                ->find()
                ->getArrayCopy()
        );

        // Update duplicate status
        CampaignRecipientQuery::create()
            ->filterByCampaignId($campaign->getId(), \Criteria::EQUAL)
            ->update(array('IsDuplicate' => 1));

        CampaignRecipientQuery::create()
            ->filterByCampaignId($campaign->getId(), \Criteria::EQUAL)
            ->filterByUserId($userIds, \Criteria::IN)
            ->update(array('IsDuplicate' => 0));
    }

    /**
     * @param Campaign $campaign
     * @param array|CampaignRecipient[] $recipients
     */
    public function sendMessage(Campaign $campaign, array $recipients)
    {
        if (!in_array($campaign->getStatus(), [CampaignPeer::STATUS_WAITING, CampaignPeer::STATUS_PENDING])) {
            throw new ActionDeniedException('invalid campaign status');
        }

        $recipientIds = [];

        foreach ($recipients as $recipient) {
            if (false === $recipient->getIsSent()) {
                $recipientIds[] = $recipient->getUserId();
            }
        }

        if (count($recipientIds) > 0) {
            $message = [
                'campaign_id' => $campaign->getId(),
                'recipient_ids' => $recipientIds
            ];

            $this->campaignMessageProducer->publish(serialize($message));
        }
    }

    /**
     * get the unique recipient's ids of the campaign
     * @param Campaign $campaign
     * @return
     * @throws \PropelException
     */
    public function getUniqueRecipientIds(Campaign $campaign)
    {
        $userIds = [];
        $distributionRoleGroups = DistributionListGroupQuery::create()
            ->useDistributionListQuery()
                ->useCampaignDistributionListQuery()
                    ->filterByCampaign($campaign)
                ->endUse()
            ->endUse()
            ->find();
        // distribution list recipients
        /** @var DistributionListGroup $distributionRoleGroup */
        foreach ($distributionRoleGroups as $distributionRoleGroup) {
            $groupId = $distributionRoleGroup->getGroupId();
            $role = $distributionRoleGroup->getGroupType()->getType();

            $userIds = array_merge($userIds, $this->groupManager->setGroupById($groupId)->getUsersByRoleUniqueNameIds($role));
        }

        // group role recipients
        foreach ($campaign->getCampaignRecipientGroups() as $campaignRecipientGroup) {
            $groupId = $campaignRecipientGroup->getGroupId();
            $role = $campaignRecipientGroup->getGroupType()->getType();

            $userIds = array_merge($userIds, $this->groupManager->setGroupById($groupId)->getUsersByRoleUniqueNameIds($role));
        }

        // individual recipients
        $userIds = array_merge(
            $userIds,
            CampaignRecipientQuery::create()
                ->filterByCampaignId($campaign->getId(), \Criteria::EQUAL)
                ->select('UserId')
                ->find()
                ->getArrayCopy()
        );

        // filter unique recipient base on campaign type
        $userIds = $this->getUniqueUserIds(
            $campaign->getType(),
            $userIds
        );

        return $userIds;
    }

    /**
     * update the number of unique recipients of the campaign
     * @param Campaign $campaign
     * @return
     * @throws \PropelException
     */
    public function updateUniqueRecipients(Campaign $campaign)
    {
        $campaign->setNbRecipient(count($this->getUniqueRecipientIds($campaign)));

        return $campaign;
    }


    /**
     * @param int $type a campaign class key
     * @param array|int[] $userIds
     * @return array
     * @throws \PropelException
     */
    public function getUniqueUserIds($type, array $userIds)
    {
        switch ((string)$type) {
            case CampaignPeer::CLASSKEY_CAMPAIGNEMAIL:
                $res = UserQuery::create()
                    ->filterById($userIds, \Criteria::IN)
                    ->withColumn('IF (email_private is not null, email_private, email)', 'email_to_use')
                    ->groupBy('email_to_use')
                    ->having('email_to_use IS NOT NULL')
                    ->select(array('Id', 'email_to_use'))
                    ->find()->getArrayCopy()
                ;


                return array_map(function($item) {
                    return isset($item['Id']) ? $item['Id'] : null;
                }, $res);
            case CampaignPeer::CLASSKEY_CAMPAIGNSMS:
                return UserQuery::create()
                    ->filterById($userIds, \Criteria::IN)
                    ->filterByPhone(null, \Criteria::ISNOTNULL)
                    ->groupByPhone()
                    ->select('Id')
                    ->find()->getArrayCopy()
                    ;
        }

        return array_unique($userIds);
    }
}
