<?php

namespace BNS\App\PaasBundle\Manager;

use BNS\App\CoreBundle\Analytics\BNSAnalyticsManager;
use BNS\App\CoreBundle\Application\ApplicationManager;
use BNS\App\CoreBundle\Events\ActivityUninstallEvent;
use BNS\App\CoreBundle\Events\ApplicationUninstallEvent;
use BNS\App\CoreBundle\Exception\InvalidApplication;
use BNS\App\CoreBundle\Exception\InvalidInstallApplication;
use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\Model\Activity;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupQuery;

use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Right\BNSRightManager;
use BNS\App\CoreBundle\User\BNSUserManager;
use BNS\App\MailerBundle\Mailer\BNSMailer;
use BNS\App\MediaLibraryBundle\Manager\MediaCreator;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaPeer;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use BNS\App\PaasBundle\Activities\ActivityManager;
use BNS\App\PaasBundle\Client\PaasClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Buzz\Browser;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Translation\TranslatorInterface;

class PaasManager
{

    CONST PREMIUM_SUBSCRIPTION = 'BENEYLU_PREMIUM_SUBSCRIPTION';
    CONST CORDIAL_CORRECTION_OFFER_UNIQUE_NAME = 'CORDIAL_COMPLETE';
    CONST SESSION_TINY_NAME = 'tiny_mce_plugins';

    CONST PAAS_SUBSCRIPTION_ACCEPT = 'accept';
    CONST PAAS_SUBSCRIPTION_REFUSE = 'refuse';

    const PAAS_OFFSET = 100000000;

    //Clé secrète pour communqiuer avec le Paas
    protected $secretKey;

    /** @var  string */
    protected $paasUrl;

    //Url de vérification des abonnements
    protected $checkUrl;

    /** @var  int */
    protected $originId;

    /** @var Browser $buzz */
    protected $buzz;

    /** @var  BNSUserManager $userManager */
    protected $userManager;

    /** @var  BNSRightManager $rightManager */
    protected $rightManager;

    /** @var  BNSGroupManager $groupManager */
    protected $groupManager;

    /** @var  MediaCreator $mediaCreator */
    protected $mediaCreator;

    /** @var  RouterInterface $router */
    protected $router;

    protected static $cacheTypes = array('subscriptions', 'resources', 'resources_sync', 'resources_sync_ent');

    /**
     * @var \Redis
     */
    protected $redis;

    /**
     * @var ApplicationManager
     */
    protected $applicationManager;


    /** @var  BNSMailer $mailer */
    protected $mailer;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var BNSAnalyticsManager
     */
    protected $analyticsManager;

    /** @var NathanResourceManager  */
    protected $nathanResourceManager;

    public function __construct(
        $buzz,
        $userManager,
        $groupManager,
        $mailer,
        $secretKey,
        $paasUrl,
        $redis,
        $mediaCreator,
        $rightManager,
        ApplicationManager $applicationManager,
        ActivityManager $activityManager,
        LoggerInterface $logger,
        RouterInterface $router,
        TranslatorInterface $translator,
        BNSAnalyticsManager $analyticsManager,
        $originId,
        NathanResourceManager $nathanResourceManager
    ) {
        $this->secretKey = $secretKey;
        $this->paasUrl = $paasUrl;
        $this->checkUrl = $paasUrl . '/order/take';
        $this->subscriptionUrl = $paasUrl . '/subscription/expose';
        $this->processUrl = $paasUrl . '/subscription/process';
        $this->resourceUrl = $paasUrl . '/subscription/resource/expose';
        $this->resourceActivityUrl = $paasUrl . '/subscription/resource/activity/{activity}';
        $this->singleResourceUrl = $paasUrl . '/subscription/singleResource/expose';
        $this->generateSubscriptionUrl = $paasUrl . '/subscription/generate';
        $this->applicationUninstallUrl = $paasUrl . '/subscription/applications/{application}/uninstall';
        $this->activityUninstallUrl = $paasUrl . '/subscription/activities/{activity}/uninstall';
        $this->premiumUninstallUrl = $paasUrl . '/subscription/premium/uninstall';
        $this->buzz = $buzz;
        $this->userManager = $userManager;
        $this->rightManager = $rightManager;
        $this->groupManager = $groupManager;
        $this->redis = $redis;
        $this->mediaCreator = $mediaCreator;
        $this->applicationManager = $applicationManager;
        $this->activityManager = $activityManager;
        $this->logger = $logger;
        $this->router = $router;
        $this->translator = $translator;
        $this->analyticsManager = $analyticsManager;
        $this->originId = $originId;
        $this->nathanResourceManager = $nathanResourceManager;
    }

    public function getPaasUrl()
    {
        return $this->paasUrl;
    }

    public function getClient($clientType, $clientIdentifier)
    {
        $client = null;
        switch (strtoupper($clientType)) {
            case 'SCHOOL':
                // TODO remove UAI try when not needed
                // Try UAI first
                $client = GroupQuery::create()
                    ->useGroupTypeQuery()
                        ->filterByType($clientType)
                    ->endUse()
                    ->filterBySingleAttribute('UAI', $clientIdentifier)
                    ->findOne();
                if (!$client && is_numeric($clientIdentifier)) {
                    $client = GroupQuery::create()
                        ->useGroupTypeQuery()
                            ->filterByType($clientType)
                        ->endUse()
                        ->findPk($clientIdentifier)
                    ;
                }
                break;
            case 'CITY':
            case 'CLASSROOM':
                if (is_numeric($clientIdentifier)) {
                    $client = GroupQuery::create()
                        ->useGroupTypeQuery()
                            ->filterByType($clientType)
                        ->endUse()
                        ->findPk($clientIdentifier)
                    ;
                }
                break;
            case 'USER':
                if (is_numeric($clientIdentifier)) {
                    $client = UserQuery::create()->findPk($clientIdentifier);
                } else {
                    $client = UserQuery::create()->filterByLogin($clientIdentifier, \Criteria::EQUAL)->findOne();
                }
            break;
        }

        return $client;
    }

    public function getClientType($client)
    {
        switch(get_class($client))
        {
            case "BNS\\App\\CoreBundle\\Model\\Group":
                return 'group';
                break;
            case "BNS\\App\\CoreBundle\\Model\\User":
                return 'user';
                break;
        }
    }

    public function getClientCacheKey($client, $type)
    {
        return 'paas_' . $type . '_' . $this->getClientType($client) . '_' . $client->getId();
    }

    public function resetClient($client)
    {
        foreach (self::$cacheTypes as $type) {
            $this->redis->del($this->getClientCacheKey($client, $type));
        }

        $subscriptions = $this->getSubscriptions($client);
        if (!isset($subscriptions['currentSubscriptions'])) {
            $subscriptions['currentSubscriptions'] = [];
        }

        $subscriptions = $subscriptions['currentSubscriptions'];
        $hasPremium = false;

        $subscriptionUniqueNames = array();
        $this->logger->debug('resetClient', array(
            'subscriptions' => $subscriptions,
            'ClientId' => $client->getId()
        ));

        foreach ($subscriptions as $subscription) {
            if ($subscription['offer']['unique_name'] == self::PREMIUM_SUBSCRIPTION) {
                $hasPremium = true;

                if ($subscription['delivered'] == false) {
                    if ((isset($subscription['end']) && time() <= strtotime($subscription['end'])) ||
                        (isset($subscription['life_time']) && true === $subscription['life_time'])
                    ) {
                        $this->handlePremiumSubscription($subscription, $client);
                    }
                }
            } elseif (isset($subscription['offer']['type']) && in_array($subscription['offer']['type'], array('APPLICATION', 'FEATURE', 'ACTIVITY'))) {
                $this->handleApplicationFeatureSubscription($subscription, $client);

                $subscriptionUniqueNames[$subscription['offer']['type']][] = $subscription['offer']['unique_name'];
            } else {
                //Livraison des autres abonnements ici
                $this->processSubscription(self::PAAS_SUBSCRIPTION_ACCEPT, $client, $subscription['id']);
            }
        }

        //Traitement ici des désabonnements ayant des conséquences dans l'ENT (uniquement premium pour l'instant)
        if($client->isPremium() && !$hasPremium)
        {
            $this->handlePremiumUnsubscription($client);
        }

        $this->handleAvailableApplicationsFeatures($subscriptionUniqueNames, $client);

    }

    public function getSubscriptions($client, $clearCache = false)
    {
        $key = $this->getClientCacheKey($client, 'subscriptions');
        if (!$this->redis->exists($key) || $clearCache)
        {
            $informations = array(
                'clientType' => $client->getPaasType(),
                'clientIdentifier' => $client->getPaasIdentifier()
            );
            $url = $this->subscriptionUrl . '?' . $this->generateQueryString($informations);
            /** @var \Buzz\Message\Response $response */
            $response = $this->buzz->get($url);

            if ($response->isSuccessful()) {
                $this->redis->set($key, $response->getContent());
                $this->redis->expire($key, 3600 * 24);
            } else {
                // TODO throw exception
                return array();
            }
        }
        return json_decode($this->redis->get($key), true);
    }

    public function getUserSubscriptions(User $user, $clearCache = false)
    {
        $key = $this->getClientCacheKey($user, 'user-subscriptions');
        if (!$this->redis->exists($key) || $clearCache) {
            $informations = array(
                'clientType' => $user->getPaasType(),
                'clientIdentifier' => $user->getPaasIdentifier(),
                'username' => $user->getUsername()
            );
            $url = $this->subscriptionUrl . '?' . $this->generateQueryString($informations);
            /** @var \Buzz\Message\Response $response */
            $response = $this->buzz->get($url);

            if ($response->isSuccessful()) {
                $this->redis->set($key, $response->getContent());
                $this->redis->expire($key, 3600 * 24);
            } else {
                // TODO throw exception
                return array();
            }
        }

        return json_decode($this->redis->get($key), true);
    }

    public function getFormattedSubscriptions($client)
    {
        $result = array();
        $subscriptions = array();

        if ($client instanceof User) {
            $subscriptions = $this->getUserSubscriptions($client, true);
        } else {
            $data = $this->getSubscriptions($client);
            if (isset($data['subscriptions'])) {
                $subscriptions = $data['subscriptions'];
            }
        }
        if (!is_array($subscriptions)) {
            return $result;
        }

        foreach ($subscriptions as $subscription) {

            if (!is_array($subscription) || !isset($subscription['offer']) || !isset($subscription['life_time'])) {
                continue;
            }

            $res = array(
                'name'      => $subscription['offer']['name'],
                'code'      => $subscription['offer']['unique_name'],
                'life_time' => $subscription['life_time'],
                'percent'   => 100,
                'color'     => 'green',
                'status'    => 'current',
                'renew'     => false,
            );

            if (isset($subscription['offer']['type']) && $subscription['offer']['type'] === 'APPLICATION') {
                $res['name'] = $this->translator->trans($subscription['offer']['unique_name'], [], 'MODULE');
            }

            if (true !== $subscription['life_time']) {
                $begin = new \DateTime($subscription['begin']);
                $end = new \DateTime(isset($subscription['end']) ? $subscription['end'] : 'now');
                $now = new \DateTime();
                $duration = $begin->diff($end);
                $over = $begin->diff($now);

                if ($duration->days) {
                    $percent = ($over->days / $duration->days) * 100;
                } else {
                    $percent = 0;
                }
                if ($percent < 0) {
                    $percent = 0;
                } elseif ($percent > 100) {
                    $percent = 100;
                }

                $res['begin']     = $begin;
                $res['end']       = $end;
                $res['percent']   = $percent;
                $res['color']     = $percent < 75 ? 'green' : ($percent < 100 ? 'orange' : 'red');
                $res['status']    = $percent < 75 ? 'current' : ($percent < 100 ? 'ending' : 'ended');
                $res['renew']     = $percent < 75 ? false : true;
            }

            if (isset($subscription['client'])) {
                $res['client'] = $this->getClient($subscription['client']['type'], $subscription['client']['identifier']);
            }

            $result[] = $res;
        }

        // Sort array by life time (first), percent DESC, label ASC
        usort($result, function($a, $b) {
            if ($a['life_time'] && $b['life_time']) {
                return strcmp($a['name'], $b['name']);
            } elseif ($a['life_time']) {
                return -1;
            } elseif ($b['life_time']) {
                return 1;
            }

            $res = strnatcmp($a['percent'], $b['percent']);

            if ($res === 0) {
                return strcmp($a['name'], $b['name']);
            }

            return $res;
        });

        return $result;
    }

    /**
     * Passe à chaque connexion - pour traitement spécifiques dans ENT (TinyMCE ...)
     */
    public function initSubscriptionForSession()
    {
//        $resources = $this->getMediaLibraryResources($this->rightManager->getModelUser());
//        foreach($resources as $resource)
//        {
//            //Rien pour l'instant
//        }

        $offers = $this->getCurrentSubscriptionsForUser($this->rightManager->getModelUser());
        if ($offers) {
            foreach ($offers as $offer) {
                $offerUniqueName = $offer['offer']['unique_name'];
                switch ($offerUniqueName) {
                    /**
                     * On pousse en session le paramètre d'autorisation
                     */
                    case self::CORDIAL_CORRECTION_OFFER_UNIQUE_NAME:
                        $session = $this->rightManager->getSession();
                        $tinyPlugins = $session->get('tiny_mce_plugins', array());
                        $tinyPlugins[] = 'correction';
                        $session->set(self::SESSION_TINY_NAME, $tinyPlugins);
                        break;
                    default:
                        break;
                }
            }
        }
    }

    public function getPremiumInformations($group)
    {
        if($group->getGroupType()->getType() == 'CLASSROOM')
        {
            $this->groupManager->setGroup($group);
            $group = $this->groupManager->getParent();
        }

        if($group->getGroupType()->getType() == 'SCHOOL')
        {
            $subscriptions = $this->getSubscriptions($group);
            if(isset($subscriptions['currentSubscriptions']))
            {
                $subscriptions = $subscriptions['currentSubscriptions'];
                foreach($subscriptions as $subscription)
                {
                    if(isset($subscription['offer']['unique_name']) && $subscription['offer']['unique_name'] == self::PREMIUM_SUBSCRIPTION)
                    {
                        return $subscription;
                    }
                }
            }
        }
        return false;
    }

    public function getResources($client, $clearCache = false)
    {
        $key = $this->getClientCacheKey($client, 'resources');
        $syncKey = $this->getClientCacheKey($client, 'resources_sync');

        if ($clearCache) {
            $this->redis->del($key);
            $this->redis->del($syncKey);
        }

        if ($clearCache || !$this->redis->exists($key)) {
            $informations = array(
                'clientType' => $client->getType(),
                'clientIdentifier' => $client->getPaasIdentifier()
            );
            $url = $this->resourceUrl . '?' . $this->generateQueryString($informations);

            /** @var \Buzz\Message\Response $paasResponse */
            $paasResponse = $this->buzz->get($url);
            if ($paasResponse->isSuccessful()) {
                $this->redis->set($key, $paasResponse->getContent());
                $this->redis->expire($key, 3600 * 3);
                $this->redis->set($syncKey, json_encode(time()));
            } else {
                // Log error handle this case
                return array();
            }
        }

        return json_decode($this->redis->get($key), true);
    }

    /**
     * @param User $user
     * @param Group $group
     * @return array|Media[]
     */
    public function getMediaLibraryResources($user, $group = null)
    {
        $this->userManager->setUser($user);
        //On traite les écoles
        $hydratedResources = array();
        //Pour toutes mes écoles

        if ($group) {
            // get resources of given group only
            $groups = [$group];
        } else {
            // get resources of all groups
            $groups = $this->userManager->getSimpleGroupsAndRolesUserBelongs(true,array(2,3));
        }

        // get resources of all groups
        /** @var Group $group */
        foreach ($groups as $group) {
            $externalFolder = $group->getExternalFolder();

            // get all existing paas medias
            $allMedias = [];
            // keep trace of existing medias not matched with a resource
            $unmatchedMedias = [];
            $medias = MediaQuery::create()
                ->filterByMediaFolderId($externalFolder->getId())
                ->filterByMediaFolderType('GROUP')
                ->find();
            foreach ($medias as $media) {
                if ($media->isFromPaas()) {
                    $allMedias[$media->getExternalId()] = $media;
                    $unmatchedMedias[$media->getId()] = true;
                }
            }

            // from paas: refresh existing medias and create new ones
            $key = $this->getClientCacheKey($group, 'resources');
            $ttl = $this->redis->ttl($key);
            $entSync = json_decode($this->redis->get($this->getClientCacheKey($group, 'resources_sync_ent')), true);
            $paasSync = json_decode($this->redis->get($this->getClientCacheKey($group, 'resources_sync')), true);
            if (!$paasSync || !$entSync || $paasSync > $entSync || $ttl < 2) {// cache is about to expire or has expired, refresh
                foreach ($this->getResources($group) as $resource) {
                    $refresh = isset($allMedias[$resource['id']]);
                    $media = $this->hydrateResourceFromPaas(
                        $resource,
                        $refresh ? $allMedias[$resource['id']] : null
                    );
                    $media->setMediaFolderId($externalFolder->getId());
                    $media->setMediaFolderType($externalFolder->getType());
                    $media->setUserId($user->getId());
                    $media->save();

                    if (isset($unmatchedMedias[$media->getId()])) {
                        unset($unmatchedMedias[$media->getId()]);
                    }
                }
                $this->redis->set($this->getClientCacheKey($group, 'resources_sync_ent'), json_encode(time()));

                // if there are medias without resource, expire them
                if (count($unmatchedMedias)) {
                    $expirationDate = new \DateTime('yesterday');
                    MediaQuery::create()
                        ->filterById(array_keys($unmatchedMedias))
                        ->update([
                            'ExpiresAt' => $expirationDate,
                        ]);
                }
            }

            // request all medias as normal => access/visibility check
            foreach ($externalFolder->getMedias() as $media) {
                if ($media->isFromPaas()) {
                    $hydratedResources[] = $media;
                }
            }

            // from nathan
            $nathanResources = $this->nathanResourceManager->getResources($user, $group);
            if ($nathanResources !== false) {
                foreach ($nathanResources as $resource) {
                    $hydratedResources[] = $resource;
                }
            }
        }

        return $hydratedResources;
    }

    /**
     * Gets user resources, grouped by group, as an array with format:
     * [
     *   groupId => [ 'group' => $group, 'resources' => $resources ],
     * ]
     *
     * @param $user
     * @return array
     */
    public function getMediaLibraryResourcesByGroup($user, $hydrateRessources = true)
    {
        $this->userManager->setUser($user);
        $data = array();

        foreach ($this->userManager->getSimpleGroupsAndRolesUserBelongs(true,array(2,3)) as $group) {
            $resources = array();
            $hasResources = false;
            if ($hydrateRessources) {
                foreach ($this->getResources($group) as $resource) {
                    $resources[] = $this->hydrateResourceFromPaas($resource);
                }
            } else {
                $hasResources = count($this->getResources($group)) > 0;
            }

            if ($hasResources) {
                $data[$group->getId()] = array(
                    'group' => $group,
                    'resources' => $resources,
                );
            }
        }

        return $data;
    }

    public function refreshMedia(Media $media)
    {
        if ($media->getCopyFromId()) {
            return $this->refreshMedia($media->getOriginal());
        }
        if (!('GROUP' === $media->getMediaFolderType() && $media->isFromPaas())) {
            throw new \InvalidArgumentException('Cannot refresh non-paas medias');
        }

        $group = $media->getMediaFolder()->getGroup();
        foreach ($this->getResources($group) as $resource) {
            if ($resource['id'] == $media->getExternalId()) {
                $media = $this->hydrateResourceFromPaas($resource, $media);
                if ($copies = $media->getCopies()) {
                    foreach ($copies as $copy) {
                        $this->hydrateResourceFromPaas($resource, $copy);
                    }
                }

                return $media;
            }
        }

        throw new NotFoundHttpException('PAAS resource not found');
    }

    public function getCurrentSubscriptionsForUser(User $user)
    {
        $this->userManager->setUser($user);
        $subscriptions = array();
        // TODO replace 2, 3 with appropriate const for CLASSROOM / SCHOOL
        foreach ($this->userManager->getSimpleGroupsAndRolesUserBelongs(true, array(2, 3)) as $group) {
            $clientSubscriptions = $this->getSubscriptions($group);
            if (isset($clientSubscriptions['currentSubscriptions'])) {
                foreach ($clientSubscriptions['currentSubscriptions'] as $subscription) {
                    $this->handleUndeliveredSubscription($subscription, $group);
                    $subscriptions[] = $subscription;
                }

            }
        }

        return $subscriptions;
    }

    public function checkResourceAccess($paasId)
    {
        $user = $this->rightManager->getUserSession();
        if ($user) {
            foreach ($this->getMediaLibraryResources($user) as $resource) {
                if ($resource->getExternalId() == $paasId) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getUrlFromPaasId($paasId)
    {
        if($this->checkResourceAccess($paasId))
        {
            return $this->paasUrl . '/subscription/resource/download/' . $paasId . '?' . $this->generateQueryString(array('id' => $paasId));
        }else{
            return false;
        }
    }

    public function getHtmlBaseUrlFromPaasId($paasId, $urlPattern)
    {
        if($this->checkResourceAccess($paasId))
        {
            return $this->singleResourceUrl . '?' . $this->generateQueryString(array('id' => $paasId));
        }
    }

    public function hydrateResourceFromPaas($resource, Media $media = null)
    {
        if (!$media) {
            $media = new Media();

            // Default to private for external medias from subscriptions created after this feature went live.
            // Medias from previous subscriptions will still be public, for bw compatibility.
            if (isset($resource['begin_date'])) {
                $begin = new \DateTime($resource['begin_date']);
                if ($begin > new \DateTime('2016-10-19 00:00:00')) {
                    $media->setIsPrivate(true);
                }
            }
        }

        $media->setLabel($resource['name']);
        $media->setExternalSource(MediaPeer::EXTERNAL_SOURCE_PAAS);
        $media->setExternalId($resource['id']);
        if(isset($resource['description']))
        {
            $media->setDescription($resource['description']);
        }
        if(isset($resource['mime_type']))
        {
            $media->setFileMimeType($resource['mime_type']);
        }
        $media->setTypeUniqueName($resource['type']);
        $media->setSize(0);

        switch($resource['type'])
        {
            case 'HTML':
                $media->setValue(serialize(array('content' => $resource['html_content'])));
                break;
            case 'FILE':
                $media->setDownloadUrl($resource['download_url']);
                break;
            case 'DOCUMENT':
                $media->setDownloadUrl($resource['download_url']);
                break;
            case 'IMAGE':
                $media->setDownloadUrl($resource['download_url']);
                break;
            case 'HTML_BASE':
                $media->setHtmlBase($resource['html_base']);
                break;
            case 'LINK':
                $media->setValue( $resource['value']);
                break;
        }

        $media->setImageMediumUrl($this->paasUrl . $resource['image_medium_url']);
        $media->setImageThumbnailUrl($this->paasUrl . $resource['image_thumbnail_url']);
        $media->setProvider($resource['provider']);
        $media->setIsDownloadable(isset($resource['downloadable']) ? (boolean) $resource['downloadable'] : true);

        if (isset($resource['end_date'])) {
            $media->setExpiresAt($resource['end_date']);
        } else {
            $media->setExpiresAt(null);
        }

        return $media;
    }

    public function handlePremiumSubscription($subscriptionInformations, $client)
    {
        if($subscriptionInformations['delivered'] == false) {
            $username = isset($subscriptionInformations['subscriber_username']) ? $subscriptionInformations['subscriber_username'] : null;
            //Vérification concordance client et username

            if ($username) {
                $user = UserQuery::create()->filterByArchived(false)->findOneByLogin($username);
                if (!$user) {
                    $this->logger->error(sprintf('[paas] Premium subscription with an invalid user "%s"', $username), array(
                        'client' => $client,
                        'subscription' => $subscriptionInformations
                    ));
                    $allowed = false;
                } else {
                    $this->userManager->setUser($user);
                    $allowed = true;
                }
            } else {
                $this->groupManager->setGroup($client);
                if ($this->groupManager->isOnPublicVersion() === true) {
                    $allowed = false;
                } else {
                    $allowed = true;
                }
            }



            // TODO put back security check if user has an account in the group
//            $groups = $this->userManager->getGroupsWhereRole('TEACHER');
//            $allowed = true;
            /*foreach($groups as $group)
            {
                if($group->getId() == $client->getId())
                {
                    //On s'assure que l'utilisateur fait partie d'une classe confirmée
                    $this->groupManager->setGroupById($client->getId());

                    foreach($this->groupManager->getSubgroups(true) as $classroom)
                    {
                        if($classroom->isValidated())
                        {
                            foreach($groups as $groupForClassroom)
                            {
                                if($groupForClassroom->getId() == $classroom->getId())
                                {
                                    $allowed = true;
                                }
                            }
                        }
                    }
                }
            }*/

            if ($allowed) {
                //Réèl traitement de l'ajout du premium
                $classroom = null;
                /* @var Group $client */
                if ($client->getGroupType()->getType() == 'CLASSROOM') {
                    $classroom = $client;

                    $this->groupManager->setGroup($client);
                    $parent = $this->groupManager->getParent();
                    if ($parent->getGroupType()->getType() != 'SCHOOL') {
                        $values = array(
                            'label' => $client->getAttribute('SCHOOL_LABEL'),
                            'type' => 'SCHOOL',
                            'attributes' => array(
                                'CITY' => $client->getAttribute('CITY'),
                                'ZIPCODE' => $client->getAttribute('ZIPCODE')
                            )
                        );
                        $newSchool = $this->groupManager->createGroup($values);
                        $this->groupManager->deleteParent($client->getId(), $parent->getId());
                        $this->groupManager->addParent($newSchool->getId(), $parent->getId());
                        $this->groupManager->addParent($client->getId(), $newSchool->getId());
                        $client = $newSchool;

                    } else {
                        $client = $parent;
                    }
                }

                //Placement de l'utilisateur en REF ENT
                if ($username) {
                    $this->userManager->linkUserWithGroup(
                        $this->userManager->getUser(),
                        $client,
                        GroupTypeQuery::create()->findOneByType('ENT_REFERENT'));
                    $this->userManager->resetRights();
                }

                //Marquage de l'école en premium
                $school = GroupQuery::create()->findOneById($client->getId());
                $school->tagPremium();


                // Ok Traitement opéré
                // Envoi d'information au paas pour le traitement de l'abonnement
                $this->processSubscription(self::PAAS_SUBSCRIPTION_ACCEPT, $client, $subscriptionInformations['id'], $classroom);

            } else {
                // Erreur traitement refus
                // Envoi d'informations au paas pour l'avertir de l'échec
                $this->processSubscription(self::PAAS_SUBSCRIPTION_REFUSE, $client ,$subscriptionInformations['id']);
            }
        }
    }

    public function handlePremiumUnsubscription($client)
    {
        $school = GroupQuery::create()->findOneById($client->getId());
        $school->untagPremium();
    }


    /**
     * @param $status
     * @param PaasClientInterface $client
     * @param int $subscriptionId
     * @param PaasClientInterface|null $oldClient use to migrate subscription on school creation
     */
    public function processSubscription($status, $client, $subscriptionId, PaasClientInterface $oldClient = null)
    {
        $requestInformations = array(
            'clientIdentifier' => $client->getPaasIdentifier(),
            'clientType' => $client->getPaasType(),
            'statusKey' => $status,
            'subscriptionId' => $subscriptionId
        );
        // set data for subscription migration classroom => school on school creation (premium only)
        if ($oldClient && $client instanceof Group) {
            $requestInformations['clientName'] = $client->getLabel();
            $requestInformations['oldClientIdentifier'] = $oldClient->getPaasIdentifier();
            $requestInformations['oldClientType'] = $oldClient->getPaasType();
        }

        $this->buzz->get($this->processUrl . "?" . $this->generateQueryString($requestInformations));
    }

    protected function forwardDenied($message = "Erreur")
    {
        throw new AccessDeniedException($message);
    }

    public function generateQueryString($parameters)
    {
        $str = '';

        $parameters['time'] = time();

        foreach($parameters as $key => $parameter)
        {
            $str .= $parameter;
        }

        $key = $this->secretKey;

        $secretKey = md5($str . $key);

        $parameters = array_merge($parameters,array('key' => $secretKey));

        return http_build_query($parameters);
    }

    /**
     * Règles de fonctionnement des requêtes ouvertess
     *
     *  - Tous les paramètres en GET
     *  - Paramètres key, time obligatoires
     *  - Concaténation de tous les attributs dans l'ordre "d'apparition"
     *  - Si client la clé est la clé de son domaine d'origine
     *  - La clé secrète globale peut être utilisée dans le cadre de call sans identifiants clients
     *  - Un client est identifié sur les paramètres clientType et clientIdentifier
     *
     * @param Request $request
     * @param bool $fromSpot
     * @return bool|Response
     */
    public function checkRequest(Request $request, $fromSpot = false )
    {
        $parameters = $request->query;

        if(!$parameters->has('time'))
        {
            $this->forwardDenied('Parametre time manquant');
        }

        if(!$parameters->has('key'))
        {
            $this->forwardDenied('Parametre key manquant');
        }

        if(time() - $parameters->get('time') > 3600)
        {
            $this->forwardDenied('Parametre time trop ancien');
        }

        $str = '';

        foreach($parameters as $key => $value)
        {
            if($key != 'key')
            {
                $str .= $value;
            }
        }

        $key = $this->secretKey;

        $encodedKey = md5($str . $key);

        if($encodedKey != $parameters->get('key'))
        {
            $this->forwardDenied("La clé n'est pas bonne");
        }
        return true;
    }

    public function createMediaFromPaasId($paasId)
    {
        foreach($this->getMediaLibraryResources($this->userManager->getUser()) as $resource)
        {
            if($resource->getId() - self::PAAS_OFFSET == $paasId)
            {
                $media = new Media();
                $media->setLabel($resource->getLabel());
                $media->setFromPaas(true);
                $media->setFromPaasId($paasId);
                $media->setValue($resource->getValue());
                $media->setDescription($resource->getDescription());
                $media->setFileMimeType($resource->getFileMimeType());
                $media->setTypeUniqueName($resource->getTypeUniqueName());
                $media->setSize(0);
                $media->setFilename('paas.jpeg');
                $media->setUserId($this->userManager->getUser()->getId());
                if(isset($resource->htmlBase))
                {
                    $urlPattern = str_replace($this->paasUrl,'',$resource->htmlBase);
                    //$urlPattern = substr($urlPattern,0,strpos($urlPattern,'?'));
                    $media->setValue(serialize(array('url_pattern' => $urlPattern)));
                }
                $media->save();
                $content = $this->curl_get_contents(str_replace('resolve/','',$resource->imageThumbnailUrl));
                $this->mediaCreator->writeFile($media->getFilePath(),$content);
                return $media;
            }
        }
        return false;
    }

    public function curl_get_contents($url)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $url);

        $data = curl_exec($ch);
        curl_close($ch);

        return $data;
    }

    public function generateSubscription($school, $offerUniqueName, $duration, $username = null, $email = null, $lang = null)
    {
        $requestInformations = array(
            'clientIdentifier' => $school->getPaasIdentifier(),
            'clientType' => $school->getPaasType(),
            'offerUniqueName' => $offerUniqueName,
            'duration' => $duration,
            'clientName' => $school->getLabel(),
            'clientLanguage' => $lang
        );
        if($username != null)
        {
            $requestInformations['username'] = $username;
        }
        if($email != null)
        {
            $requestInformations['email'] = $email;
        }
        $this->buzz->get($this->generateSubscriptionUrl . "?" . $this->generateQueryString($requestInformations));
    }

    public function endPremiumSubscription($school) {
        $requestInformations = array(
            'clientIdentifier' => $school->getPaasIdentifier(),
            'clientType' => $school->getPaasType(),
            'duration' => null,
            'clientName' => $school->getLabel()
        );

        $this->buzz->get($this->premiumUninstallUrl . "?" . $this->generateQueryString($requestInformations));
    }

    /**
     * Send paas information for uninstalled application
     * @param ApplicationUninstallEvent $event
     */
    public function onApplicationUninstallEvent(ApplicationUninstallEvent $event)
    {
        $application = $event->getApplication();
        $group = $event->getGroup();

        $url = str_replace('{application}', $application->getUniqueName(), $this->applicationUninstallUrl);

        $parameters = array(
            'clientIdentifier' => $group->getPaasIdentifier(),
            'clientType' => $group->getPaasType(),
        );

        try {
            /** @var \Buzz\Message\Response $response */
            $response = $this->buzz->put($url . '?' . $this->generateQueryString($parameters));

            if ($response->isSuccessful()) {
                $this->logger->debug(sprintf('[PaasManager] successfully flag application %s as uninstalled', $application->getUniqueName()));
            } else {
                throw new \Exception(sprintf('Invalid response from paas : %s', $response->getStatusCode()));
            }

        } catch (\Exception $e) {
            $this->logger->error(sprintf('[PaasManager] Error will calling paas to flag application as uninstalled %s from groupId %s: %s', $application->getUniqueName(), $group->getId(), $e->getMessage()));
        }
    }

    /**
     * Send paas information for uninstalled activity
     * @param ActivityUninstallEvent $event
     */
    public function onActivityUninstallEvent(ActivityUninstallEvent $event)
    {
        $activity = $event->getActivity();
        $group = $event->getGroup();

        $url = str_replace('{activity}', $activity->getUniqueName(), $this->activityUninstallUrl);

        $parameters = array(
            'clientIdentifier' => $group->getPaasIdentifier(),
            'clientType' => $group->getPaasType(),
        );

        try {
            /** @var \Buzz\Message\Response $response */
            $response = $this->buzz->put($url . '?' . $this->generateQueryString($parameters));

            if ($response->isSuccessful()) {
                $this->logger->debug(sprintf('[PaasManager] successfully flag activty %s as uninstalled', $activity->getUniqueName()));
            } else {
                throw new \Exception(sprintf('Invalid response from paas : %s', $response->getStatusCode()));
            }

        } catch (\Exception $e) {
            $this->logger->error(sprintf('[PaasManager] Error will calling paas to flag activity as uninstalled %s from groupId %s: %s', $activity->getUniqueName(), $group->getId(), $e->getMessage()));
        }
    }


    /**
     * Redirect the client after a successfull order from spot
     *
     * @param $clientType
     * @param $clientIdentifier
     * @param null $codeOffer
     * @return bool|RedirectResponse
     */
    public function getRedirectClient($clientType, $clientIdentifier, $codeOffer = null)
    {
        $client = $this->getClient($clientType, $clientIdentifier);
        if (!$client || !($client instanceof Group)) {
            return false;
        }

        try {
            $this->rightManager->switchContext($client);
        } catch (\Exception $e) {
            return false;
        }

        $offer = $this->getApplicationFromOffer($client, $codeOffer);

        if (isset($offer['unique_name']) && $offer['unique_name'] === self::PREMIUM_SUBSCRIPTION) {
            // Auto open apps panel for premium subscription
            return true;
        }

        if (isset($offer['type'])) {
            switch (strtoupper($offer['type'])) {
                case 'APPLICATION':
                    $application = $this->applicationManager->getApplication($codeOffer);
                    if ($application) {
                        return new RedirectResponse($this->router->generate($application->getRouteFront()));
                    }

                    // open application panel
                    return true;
                    break;
                case 'ACTIVITY':
                    $groupActivity = $this->activityManager->getActivity($codeOffer, $client);
                    if ($groupActivity) {
                        return new RedirectResponse($this->router->generate($groupActivity->getRouteFront(), $groupActivity->getRouteParameters()));
                    }

                    // open application panel
                    return true;
                break;
                case 'FEATURE':
                    // TODO redirect to guide of the feature
                    return true;
                break;
            }
        }

        $externalFolder = $client->getExternalFolder();
        if ($externalFolder) {
            return new RedirectResponse($this->router->generate('BNSAppMediaLibraryBundle_user_folder', [
                'slug' => $externalFolder->getSlug(),
            ]));
        } else {
            return new RedirectResponse($this->router->generate('BNSAppMediaLibraryBundle_front'));
        }
    }

    public function getResourceFromActivity(Activity $activity, PaasClientInterface $client)
    {
        $key = $this->getClientCacheKey($client, 'activity_' . $activity->getUniqueName());

        if (!$this->redis->exists($key)) {
            $informations = array(
                'clientType' => $client->getPaasType(),
                'clientIdentifier' => $client->getPaasIdentifier()
            );
            $url = str_replace('{activity}', $activity->getUniqueName(), $this->resourceActivityUrl) . '?' . $this->generateQueryString($informations);

            /** @var \Buzz\Message\Response $paasResponse */
            $paasResponse = $this->buzz->get($url);
            if ($paasResponse->isSuccessful()) {
                $this->redis->set($key, $paasResponse->getContent());
                $this->redis->expire($key, 3600 * 3);

                return json_decode($paasResponse->getContent(), true);
            } else {
                // Log error handle this case
                $this->logger->error(sprintf('[Paas] get resource activity "%s" error', $activity->getUniqueName()), array(
                    'clientType' => $client->getPaasType(),
                    'clientIdentifier' => $client->getPaasIdentifier(),
                    'header' => $paasResponse->getHeaders(),
                    'response' => $paasResponse->getContent()
                ));

                return null;
            }
        }

        return json_decode($this->redis->get($key), true);
    }

    protected function getApplicationFromOffer(PaasClientInterface $client, $codeOffer, $clearCache = false)
    {
        $subscriptions = $this->getSubscriptions($client, $clearCache);
        if (is_array($subscriptions) && isset($subscriptions['currentSubscriptions'])) {
            foreach ($subscriptions['currentSubscriptions'] as $subscription) {
                if (isset($subscription['offer']['unique_name']) && $subscription['offer']['unique_name'] === $codeOffer) {
                    return $subscription['offer'];
                }
            }
        }

        // we asked for a fresh data but we didn't find it
        if ($clearCache) {
            return null;
        }

        // we didn't found the offer in the cache we try to clear it
        return $this->getApplicationFromOffer($client, $codeOffer, true);
    }


    protected function handleUndeliveredSubscription($subscription, PaasClientInterface $client)
    {
        if (false !== $subscription['delivered'] || !is_array($subscription)) {
            return ;
        }

        if ($subscription['offer']['unique_name'] == self::PREMIUM_SUBSCRIPTION) {
            // TODO Move this check in the "handlePremiumSubscription" method
            if ((isset($subscription['end']) && time() <= strtotime($subscription['end'])) ||
            (isset($subscription['life_time']) && true === $subscription['life_time'])
            ) {
                $this->handlePremiumSubscription($subscription, $client);
            }
        } elseif (isset($subscription['offer']['type']) && in_array($subscription['offer']['type'], array('APPLICATION', 'FEATURE'))) {
            $this->handleApplicationFeatureSubscription($subscription, $client);
        } else {
            //Livraison des autres abonnements ici
            $this->processSubscription(self::PAAS_SUBSCRIPTION_ACCEPT, $client, $subscription['id']);
        }
    }

    /**
     * This handle application / feature / activity subscription
     * @param array $subscription
     * @param Group|User $client
     * @return bool
     */
    protected function handleApplicationFeatureSubscription($subscription, $client)
    {
        if (!isset($subscription['offer']['type']) || !isset($subscription['delivered']) || false !== $subscription['delivered']) {
            return false;
        }

        switch (strtoupper($subscription['offer']['type'])) {
            case 'APPLICATION':
                if ($client instanceof Group) {
                    try {
                        $this->applicationManager->installApplication($subscription['offer']['unique_name'], $client);

                        $this->processSubscription(self::PAAS_SUBSCRIPTION_ACCEPT, $client, $subscription['id']);
                        $this->trackInstall($subscription, $client);

                        return true;
                    } catch (InvalidInstallApplication $e) {
                        // try to install a system or base application we accept the subscription without installing the app
                        $this->processSubscription(self::PAAS_SUBSCRIPTION_ACCEPT, $client, $subscription['id']);
                        $this->trackInstall($subscription, $client);

                        return true;
                    } catch (InvalidApplication $e) {
                        $this->logger->error('Paas handle Application / Feature Subscription InvalidApplication : ' . $e->getMessage(), array(
                            'groupId' => $client->getId(),
                            'subscription' => $subscription
                        ));
                    } catch (\Exception $e) {
                        $this->logger->error('Paas handle Application / Feature Subscription error : ' . $e->getMessage(), array(
                            'groupId' => $client->getId(),
                            'subscription' => $subscription
                        ));
                    }
                } elseif ($client instanceof User) {
                    $this->logger->error('Paas handle Application / Feature Subscription invalid subscription try to install app for a user', array(
                        'clientId' => $client->getId(),
                        'subscription' => $subscription
                    ));
                }

                $this->processSubscription(self::PAAS_SUBSCRIPTION_REFUSE, $client, $subscription['id']);

                return false;
            case 'FEATURE':
                // TODO feature manager handle installation
                return false;
            case 'ACTIVITY':
                if ($client instanceof Group) {
                    $this->activityManager->install($subscription['offer']['unique_name'], $subscription['offer'], $client);
                    $this->processSubscription(self::PAAS_SUBSCRIPTION_ACCEPT, $client, $subscription['id']);
                    $this->trackInstall($subscription, $client);

                    return true;
                }

                $this->processSubscription(self::PAAS_SUBSCRIPTION_REFUSE, $client, $subscription['id']);

                return false;
        }

        return false;
    }

    /**
     * Will disable all applications / features that do not have a valid subscription
     *
     * @param $applications
     * @param $features
     * @param $client
     */
    protected function handleAvailableApplicationsFeatures($subscriptionUniqueNames, $client)
    {
        if ($client instanceof Group) {
            // Check Applications for uninstall
            $currentApplications = $this->applicationManager->getUserInstalledApplications($client);
            $applications = isset($subscriptionUniqueNames['APPLICATION']) ? $subscriptionUniqueNames['APPLICATION'] : array();
            foreach ($currentApplications as $currentApplication) {
                if (!in_array($currentApplication->getUniqueName(), $applications)) {
                    try {
                        $this->applicationManager->uninstallApplication($currentApplication->getUniqueName(), $client);
                    } catch (\Exception $e) {
                        $this->logger->error(sprintf('Paas try to uninstall application "%s" failed : %s', $currentApplication->getUniqueName(), $e->getMessage()), array(
                            'clientId' => $client->getId(),
                            'clientType' => $client->getType()
                        ));
                    }
                }
            }

            // Check Activities for uninstall
            $currentGroupActivities = $this->activityManager->getActivities($client);
            $activities = isset($subscriptionUniqueNames['ACTIVITY']) ? $subscriptionUniqueNames['ACTIVITY'] : array();
            foreach ($currentGroupActivities as $groupActivity) {
                $activity = $groupActivity->getActivity();
                if (!in_array($activity->getUniqueName(), $activities)) {
                    try {
                        $this->activityManager->uninstall($activity, $client);
                    } catch (\Exception $e) {
                        $this->logger->error(sprintf('Paas try to uninstall activity "%s" failed : %s', $activity->getUniqueName(), $e->getMessage()), array(
                            'clientId' => $client->getId(),
                            'clientType' => $client->getType()
                        ));
                    }
                }
            }
        }

        // TODO uninstall features
    }

    protected function trackInstall($subscription, $client)
    {
        $user = null;
        $properties = [];
        if ($client instanceof Group) {
            if (isset($subscription['subscriber_username'])) {
                $user = UserQuery::create()->filterByLogin($subscription['subscriber_username'])->findOne();
                if ($user) {
                    $properties['application'] = $subscription['offer']['unique_name'];
                    $properties['type'] = $subscription['offer']['type'];
                    $properties['group_id'] = $client->getId();
                    $properties['group_type'] = $client->getType();
                }
            }
        } elseif ($client instanceof User) {
            $user = $client;
            $properties['application'] = $subscription['offer']['unique_name'];
            $properties['type'] = $subscription['offer']['type'];
            $properties['user_id'] = $user->getId();
        }

        if ($user && count($properties) > 0) {
            $properties['category'] = 'ENT - Module';               // nice category, for GGA
            $properties['label'] = $properties['application'];      // nice label, for GGA
            $this->analyticsManager->trackUser('INSTALLED_APPLICATION_USER', $user, $properties);
        }

    }


}
