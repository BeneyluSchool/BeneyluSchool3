<?php

namespace BNS\App\PaasBundle\Manager;

use BNS\App\CoreBundle\Api\BNSApi;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\CommonBundle\Redis\PredisClient;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\CacheItemInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class LicenceManager
{
    /**
     * @var string|null global licence (EXPRESS, CLASSIC, SCHOOL, INFINITY)
     */
    protected $globalLicence;

    /**
     * @var BNSApi
     */
    protected $api;

    /**
     * @var PaasSubscriptionManager
     */
    protected $paasSubscriptionManager;

    /**
     * @var CacheItemPoolInterface
     */
    private $cacheItemPool;

    /**
     * @var CacheItemInterface[]|
     */
    private $groupCache = [];

    /**
     * @var array|int[]
     */
    private $parentIdCache = [];

    public function __construct(
        BNSApi $api,
        PaasSubscriptionManager $paasSubscriptionManager,
        CacheItemPoolInterface $cacheItemPool,
        $globalLicence = null
    ) {
        $this->api = $api;
        $this->paasSubscriptionManager = $paasSubscriptionManager;
        $this->cacheItemPool = $cacheItemPool;
        $this->globalLicence = $globalLicence ? strtoupper($globalLicence) : null;
    }

    /**
     * Get group licence data
     * @param Group|int $groupOrGroupId Group object or id
     *
     * @return string|null group licence (EXPRESS, CLASSIC, SCHOOL, INFINITY)
     */
    public function getLicence($groupOrGroupId)
    {
        if ($this->globalLicence) {
            return $this->globalLicence;
        }

        $data = $this->getLicenceArray($groupOrGroupId);

        return $data['licence'] ?? null;
    }

    /**
     * @param $groupOrGroupId
     * @return null|false|\DateTime null if no data, false if unlimited or a \DateTime object
     */
    public function getLicenceExpirationDate($groupOrGroupId)
    {
        if ($this->globalLicence) {
            return false;
        }

        $data = $this->getLicenceArray($groupOrGroupId);

        return isset($data['licence']) ? ($data['life_time'] ? false : $data['end']) : null;
    }

    /**
     * /!\ this is for analytics data only
     * unlimited => +10 Years
     * @param Group|int $groupOrId
     * @return array ['licence' => 'SCHOOL', 'end' => 1223333]
     */
    public function getLicenceAnalyticsData($groupOrId)
    {
        $licenceData = $this->getLicenceArray($groupOrId);
        $licence = $licenceData['licence'] ?? null;
        $end = null;
        if ($licence) {
            $licence = 'SCHOOL' === $licence ? 'Beneylu School' : 'Beneylu School ' . ucfirst(strtolower($licence));
            if ($licenceData['life_time']) {
                // unlimited
                $end = strtotime('+10 year');
            } elseif ($licenceData['end'] instanceof \DateTime) {
                $end = $licenceData['end']->getTimestamp();
            }
        } else {
            $licence = 'None';
        }

        return ['licence' => $licence, 'end' => $end];
    }

    public function resetLicence($groupId, $withSubgroup = false)
    {
        if ($this->globalLicence) {
            return;
        }

        // reset licence cache entry
        $ids = [];
        $keys = [];
        if ($withSubgroup) {
            $ids = $this->getAllSubgroupIds($groupId);
            $keys = array_map(function ($item) {
               return 'l_' . $item;
            }, $ids);
        }
        $keys[] = 'l_' . $groupId;
        // remove licence cache
        $this->cacheItemPool->deleteItems($keys);

        $ids[] = $groupId;
        // reset users rights
        $this->api->resetGroupUsers($groupId);
        // reset groups cache
        $this->api->resetGroups($ids);
    }

    /**
     * warm up the cache to prevent multiple redis call
     * @param $groupIds
     */
    public function warmCache($groupIds)
    {
        if ($this->globalLicence || 0 === count($groupIds)) {
            return;
        }
        // preload cache from redis pool
        $this->cacheItemPool->getItems(array_map([$this, 'getCacheKey'], $groupIds));
    }

    /**
     * Get group licence data
     * @param Group|int $groupOrGroupId Group object or id
     *
     * @return array group licence data
     */
    public function getLicenceArray($groupOrGroupId)
    {
        if ($this->globalLicence) {
            return [ 'licence' => $this->globalLicence, 'life_time' => true];
        }

        if ($groupOrGroupId instanceof Group) {
            $group = $groupOrGroupId;
            $groupId = $group->getId();
        } else {
            $group = null;
            $groupId = (int)$groupOrGroupId;
        }

        $cache = $this->getCache($groupId);
        if (true) { //if (!$cache->isHit()) {
            $data = false;
            if (!$group) {
                $group = GroupQuery::create()
                    ->filterById($groupId)
                    ->filterByArchived(false)
                    ->joinWith('GroupType')
                    ->findOne();
            }
            if ($group) {
                if ('PARTNERSHIP' === $group->getType()) {
                    $data = $this->getPartnershipLicenceData($group);
                } else {
                    $data = $this->getLicenceData($group);
                }
            }

            $this->setCache($cache, $data);
            $this->cacheItemPool->commit();
        }

        return $cache->get() ? : null;
    }

    protected function getPartnershipLicenceData(Group $group)
    {
        $partnerIds = $this->getPartnerIds($group->getId());

        return $this->getMaxLicenceData($this->getLicenceDataGroupIds($partnerIds));
    }

    protected function getLicenceData(Group $group)
    {
        $groupId = $group->getId();
        $cache = $this->getCache($groupId);
        if (true) { //if (!$cache->isHit()) {
            // ask paas for licences
            $licenceSubscriptions = $this->paasSubscriptionManager->getLicences($group);

            // get parents subscriptions
            $parentIds = $this->getParentIds($groupId);
            $licenceSubscriptions = array_merge($licenceSubscriptions, $this->getLicenceDataGroupIds($parentIds));

            $licenceSubscription = $this->getMaxLicenceData($licenceSubscriptions);

            $this->setCache($cache, $licenceSubscription);
        }

        return $cache->get();
    }

    protected function getLicenceDataGroupIds($groupIds)
    {
        $licenceSubscriptions = [];
        if (count($groupIds)) {
            $notCachedGroupIds = [];
            /**
             * @var string $key
             * @var CacheItemInterface $cache
             */
            foreach ($this->cacheItemPool->getItems(array_map([$this, 'getCacheKey'], $groupIds)) as $key => $cache) {
                if (!$cache->isHit()) {
                    $notCachedGroupIds[] = (int)str_replace('l_', '', $key);
                } elseif ($data = $cache->get()) {
                    $licenceSubscriptions[] = $data;
                }
            }

            if (count($notCachedGroupIds) > 0) {
                $groups = GroupQuery::create()
                    ->filterById($notCachedGroupIds)
                    ->filterByArchived(false)
                    ->find();
                foreach ($groups as $group) {
                    if ($data = $this->getLicenceData($group)) {
                        $licenceSubscriptions[] = $data;
                    }
                }
            }
        }

        return $licenceSubscriptions;
    }

    protected function getMaxLicenceData(array $licenceSubscriptions)
    {
        $licenceSubscription = false;
        if (count($licenceSubscriptions) > 0) {
            // order licences with max first
            usort($licenceSubscriptions, [$this, 'compareLicence']);
            $licenceSubscription = $licenceSubscriptions[0];
        }

        return $licenceSubscription;
    }

    /**
     * @param $groupId
     * @return CacheItemInterface
     */
    protected function getCache($groupId)
    {
        return $this->cacheItemPool->getItem($this->getCacheKey($groupId));
    }

    protected function setCache(CacheItemInterface $item, $licenceData)
    {
        $item->set($licenceData);
        $this->setCacheExpire($item);
        $this->cacheItemPool->saveDeferred($item);
    }

    protected function setCacheExpire(CacheItemInterface $item)
    {
        $data = $item->get();
        $ex = $data ? 2592000 : 3600;
        if (is_array($data) && !$data['life_time'] && $data['end']) {
            $ex = $data['end']->getTimestamp() - time();
            if ($ex < 900) {
                $ex = 900;
            }
        }
        $item->expiresAfter($ex);
    }

    protected function isMaxLicence($licence)
    {
        return in_array($licence, ['SCHOOL', 'INFINITY']);
    }

    protected function compareLicence($a, $b)
    {
        $licences = [1 => 'EXPRESS', 2 => 'CLASSIC', 3 => 'SCHOOL', 4 => 'INFINITY'];

        $rankA = array_search($a['licence'], $licences, true) ?: 0;
        $rankB = array_search($b['licence'], $licences, true) ?: 0;

        if ($rankA === $rankB) {
            if ($a['life_time'] && !$b['life_time']) {
                return -1;
            } elseif (!$a['life_time'] && $b['life_time']) {
                return 1;
            } elseif ($a['life_time'] && $b['life_time']) {
                return 0;
            }

            return $a['end'] < $b['end'] ? 1 : -1;
        }

        return $rankA < $rankB ? 1 : -1;
    }

    protected function getCacheKey($groupId)
    {
        return 'l_' . $groupId;
    }

    protected function getParentIds($groupId)
    {
        if (!isset($this->parentIdCache[$groupId])) {
            $parents = $this->api->send(
                'group_parent',
                [
                    'route' => [
                        'id' => (int)$groupId,
                    ]
                ]
            );

            $this->parentIdCache[$groupId] = [];
            if (is_array($parents)) {
                foreach ($parents as $parent) {
                    if (isset($parent['id'])) {
                        $this->parentIdCache[$groupId][] = (int)$parent['id'];
                    }
                }
            }
        }

        return $this->parentIdCache[$groupId];
    }

    /**
     * get partnerIds
     *
     * extracted from PartnershipManager to prevent to cycle dependencies
     *
     * @param $partnershipId
     * @return array
     */
    protected function getPartnerIds($partnershipId)
    {
        $response = $this->api->send(
            'partnership_members',
            [
                'route' => ['partnership_id' => $partnershipId]
            ],
            true
        );

        $partnerIds = [];
        if ($response && is_array($response)) {
            foreach ($response as $group) {
                $partnerIds[] = (int)$group['friend_id'];
            }
        }

        return $partnerIds;
    }

    /**
     * get subAllGroupIds
     *
     * @param $groupId
     * @return array|mixed
     */
    protected function getAllSubgroupIds($groupId)
    {
        $response = $this->api->send(
            'group_allsubgroupids',
            [
                'route' => [
                    'id' => (int)$groupId,
                ]
            ]
        );
        if ($response && is_array($response)) {
            return $response;
        }

        return [];
    }
}
