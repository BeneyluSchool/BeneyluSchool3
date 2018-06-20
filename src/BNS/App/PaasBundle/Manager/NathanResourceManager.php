<?php
namespace BNS\App\PaasBundle\Manager;

use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\MobileDetect\DeviceView;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupPeer;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\GroupTypePeer;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Right\BNSRightManager;
use BNS\App\CoreBundle\User\BNSUserManager;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaPeer;
use Predis\Client;
use Psr\Log\LoggerInterface;
use SunCat\MobileDetectBundle\DeviceDetector\MobileDetector;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class NathanResourceManager
{

    /** @var  LoggerInterface */
    protected $logger;

    protected $debug = false;

    /**
     * 'client_id' => [
     *    'url' => '',
     *    'pf' => '',
     *    'key' => '',
     *  ]
     *
     * @var array
     */
    protected $clientOptions = [];

    /** @var  string */
    protected $casClientId;

    /** @var  string */
    protected $casClientKey;

    /** @var  Client */
    protected $redisCache;

    /** @var BNSGroupManager */
    protected $groupManager;

    /**
     * @var MobileDetector
     */
    protected $mobileDetector;

    /**
     * @var BNSUserManager
     */
    protected $userManager;

    public function __construct($casClientId, $casClientKey, array $clientOptions, BNSGroupManager $groupManager, LoggerInterface $logger, $debug, $redisCache, MobileDetector $mobileDetector, BNSUserManager $userManager)
    {
        list($this->casClientId) = explode('_', $casClientId);
        $this->casClientKey = $casClientKey;
        $this->logger = $logger;
        $this->debug = (boolean) $debug;
        $this->setClientOptions($clientOptions);
        $this->redisCache = $redisCache;
        $this->groupManager = $groupManager;
        $this->mobileDetector = $mobileDetector;
        $this->userManager = $userManager;
    }

    public function setClientOptions(array $options)
    {
        // todo validate options
        $this->clientOptions = $options;
    }

    /**
     * @param User $user
     * @param Group $group
     * @param string $clientId
     * @param null $resourceId a resource Id to filter the catalog
     * @return array
     */
    public function getResources(User $user, Group $group, $clientId = 'nathan', $resourceId = null)
    {
        if (!$this->isClientValid($clientId)) {
            $this->logger->debug(sprintf('NathanResourceManager:getResources invalid client id: %s', $clientId));

            return [];
        }

        if (!($uai = $this->getUAI($group))) {
            $this->logger->debug(sprintf('NathanResourceManager:getResources no UAI for group "%s" and client id: %s', $group->getId(), $clientId));

            return [];
        }

        $nathanProfile = $this->getNathanProfile($user);
        $hydratedResources = [];
        if ($resources = $this->getCatalog($clientId, $user, $uai)) {
            foreach ($resources as $resource) {
                if (null === $resourceId || (isset($resource->IdRessource) && $resource->IdRessource == $resourceId)) {
                    if ($hydrated = $this->hydrateResource($resource, $group->getId(), $nathanProfile)) {
                        if (isset($resource->IdRessource) && $resource->IdRessource == $resourceId) {
                            // return one resource
                            return [$hydrated];
                        } else {
                            $hydratedResources[] = $hydrated;
                        }
                    }
                }
            }
        }

        return $hydratedResources;
    }

    public function hasResources(User $user, Group $group, $clientId = 'nathan')
    {
        if (!$this->isClientValid($clientId)) {
            $this->logger->debug(sprintf('NathanResourceManager:getResources invalid client id: %s', $clientId));

            return false;
        }

        if (!($uai = $this->getUAI($group))) {
            $this->logger->debug(sprintf('NathanResourceManager:getResources no UAI for group "%s" and client id: %s', $group->getId(), $clientId));

            return false;
        }

        $resources = $this->getCatalog($clientId, $user, $uai);

        return $resources && count($resources) > 0;
    }

    /**
     * get resources catalog from the soap api
     *
     * @param string $clientId the soap client id
     * @param User $user
     * @param string $uai the school uai to get resources
     * @param bool $refresh set true to rebuild the cache
     * @return array|bool|mixed|string
     */
    public function getCatalog($clientId, User $user, $uai, $refresh = false)
    {
        if (!$this->isClientValid($clientId)) {
            $this->logger->debug(sprintf('NathanResourceManager:getCatalog invalid client id: %s', $clientId));

            return false;
        }

        $deviceData = $this->getDeviceTypeData();
        $cacheKey = $this->getCacheKey($clientId, $user, $uai, $deviceData);

        if (!$refresh && ($data = $this->redisCache->get($cacheKey))) {
            $this->logger->debug('NathanResourceManager::getCatalog get from cache', [
                'cache_key' => $cacheKey,
                'data' => $data,
                'deviceData' => $deviceData
            ]);

            return unserialize($data);
        }

        try {
            $data = $this->buildCatalog($clientId, $user, $uai, $deviceData);
            if (false !== $data) {
                $this->redisCache->set($cacheKey, serialize($data), 'EX', 86400);
            }

            return $data;
        } catch (\SoapFault $soe) {
            $this->logger->error(sprintf('NathanResourceManager:getCatalog soap error: %s', $soe->getMessage()));
            if ($this->debug) {
                throw $soe;
            }
        } catch (\Exception $e) {
            $this->logger->error(sprintf('NathanResourceManager:getCatalog error: %s', $e->getMessage()));
            if ($this->debug) {
                throw $e;
            }
        } catch (\Error $e) {
            $this->logger->error(sprintf('NathanResourceManager:getCatalog error: %s', $e->getMessage()));
            if ($this->debug) {
                throw new \Exception($e);
            }
        }

        return false;
    }

    /**
     * Return a Nathan profile type that correspond to user profile
     *
     * @param User $user
     * @return string Nathan profile
     * @throws \PropelException
     */
    public function getNathanProfile(User $user)
    {
        $role = GroupTypeQuery::create()
            ->filterById($user->getHighRoleId())
            ->filterBySimulateRole(true)
            ->select(['Type'])
            ->findOne()
        ;

        switch (strtoupper($role)) {
            case 'ENT_REFERENT':
            case 'DIRECTOR':
            case 'TEACHER':
                return 'National_ENS';
            case 'PUPIL':
                return 'National_ELV';
            case 'PARENT':
                return 'National_TUT';
        }

        return 'National_ETA';
    }

    /**
     * build the catalog by calling soap api
     *
     * @param $clientId
     * @param User $user
     * @param $uai
     * @return array|bool
     */
    protected function buildCatalog($clientId, User $user, $uai, array $deviceData = null)
    {
        $client = $this->initSoapClient($clientId, $deviceData);

        $securityParams = $this->getClientSecurityParameters($clientId, $uai);

        // get SSO Type
        $ssoType = $this->getSsoType($client, $securityParams);

        $params = $this->getDataParameters($user, $ssoType, $uai);

        // get catalog data
        $response = $client->UserRessourcesCatalog(array_merge($securityParams, $params));
        if ($this->debug) {
            $this->logger->debug('NathanResourceManager::buildCatalog SoapCall', [
                'header' => $client->__getLastRequestHeaders(),
                'request' => $client->__getLastRequest()
            ]);
        }

        if ($response && is_object($response) && 'OK' === $response->messageRetour) {
            if ($this->debug) {
                $this->logger->debug('NathanResourceManager::buildCatalog OK response', [
                    'response' => $response,
                    'clientId' => $clientId,
                    'user' => $user->getId(),
                    'uai' => $uai,
                    'params' => $params,
                ]);
            }
            if (isset($response->ressources)) {
                return $response->ressources;
            }

            return [];
        }

        $this->logger->error('NathanResourceManager::buildCatalog invalid response', [
            'response' => $response,
            'clientId' => $clientId,
            'user' => $user->getId(),
            'uai' => $uai,
            'params' => $params,
        ]);

        return false;
    }

    protected function getClientOptions($clientId)
    {
        if (!$this->isClientValid($clientId)) {
            $this->logger->error('NathanResourceManager : error invalid client id', [
                'clientId' => $clientId,
                'options' => $this->clientOptions,
            ]);
            throw new \InvalidArgumentException('NathanResourceManager : error invalid client id');
        }

        return $this->clientOptions[$clientId];
    }

    /**
     * @param $clientId
     * @return bool
     */
    protected function isClientValid($clientId)
    {
        return isset($this->clientOptions[$clientId]);
    }

    /**
     * init soap client
     *
     * @param string $clientId
     * @return \SoapClient
     */
    protected function initSoapClient($clientId, array $deviceData = null)
    {
        $options = $this->getClientOptions($clientId);

        $client = new \SoapClient($options['url'], ['trace' => $this->debug ? 1 : 0 ]);

        if ($deviceData) {
            $headers = [];
            if ($type = $deviceData['type']) {
                $headers[] = new \SoapHeader('http://cns.connecteur-universel.com/webservices','deviceType', $type);
            }
            if ($os = $deviceData['os']) {
                $headers[] = new \SoapHeader('http://cns.connecteur-universel.com/webservices','deviceOS', $os);
            }
            if (count($headers)) {
                $client->__setSoapHeaders($headers);
            }
        }

        return $client;
    }

    /**
     * @param $clientId
     * @param $uai
     * @return array
     */
    protected function getClientSecurityParameters($clientId, $uai)
    {
        $options = $this->getClientOptions($clientId);
        $key = md5($options['key'] . date('dmY'));

        $params = [
            'Cle' => $key,
            'Pf' => $options['pf'],
            'ENTPersonStructRattachUAI' => $uai
        ];

        return $params;
    }

    /**
     * build call parameters based on SsoType
     *
     * @param User $user
     * @param $ssoType
     * @param $uai
     * @return array
     */
    protected function getDataParameters(User $user, $ssoType, $uai)
    {
        $this->userManager->setUser($user);
        $classrooms = [];
        $teams = [];

        $groups = GroupQuery::create()
            ->filterById($this->userManager->getGroupsIdsUserBelong())
            ->filterByEnabled(true)
            ->useGroupTypeQuery()
                ->filterByType(['CLASSROOM', 'TEAM'])
                ->withColumn('type', 'type')
            ->endUse()
            ->select(['label', 'type'])
            ->find();
        foreach ($groups as $group) {
            if ($group['type'] === 'CLASSROOM') {
                $classrooms[] = $group['label'];
            } elseif ($group['type'] === 'TEAM') {
                $teams[] = $group['label'];
            }
        }

        $profile = $this->getNathanProfile($user);
        $data = [];
        switch ($ssoType) {
            case 5:
            case 4:
            case 3:
                $data['user'] = $this->anonymizeId($user->getLogin());
                $data['ENTEleveMEF'] = $this->getPupilMefCode($user);
                $data['ENTEleveCodeEnseignements'] = null;
                $data['ENTEleveClasses'] = $profile === 'National_ELV' ? implode('|', $classrooms) : null;
                $data['ENTEleveGroupes'] = $profile === 'National_ELV' ? implode('|', $teams) : null;
                $data['ENTAuxEnsClassesMatieres'] = null;
                $data['ENTAuxEnsClasses'] = $profile !== 'National_ELV' ? implode('|', $classrooms) : null;
                $data['ENTAuxEnsGroupes'] = $profile !== 'National_ELV' ? implode('|', $teams) : null;
                $data['ENTAuxEnsMEF'] = null;

            case 2:
                $data['ENTPersonStructRattachUAI'] = $uai;
                $data['ENTPersonProfils'] = $this->getNathanProfile($user);
                $data['EnfantId'] = null;
                $data['ENTStructureTypeStruct'] = '1ORD';

            default:
            case 1:
                break;
        }

        return $data;
    }

    /**
     * made a soap Api call to get the sso type allowed
     * @param \SoapClient $client
     * @param $params
     * @return bool
     */
    protected function getSsoType(\SoapClient $client, $params)
    {
        $response = $client->InitUserRessourcesCatalog($params);
        if ($response && is_object($response) && 'OK' === $response->messageRetour) {
            if ($this->debug) {
                $this->logger->debug('NathanResourceManager::getSsoType OK response', [
                    'response' => $response,
                    'ssoType' => $response->TypeSSO,
                ]);
            }

            return $response->TypeSSO;
        }

        $this->logger->error('NathanResourceManager::getSsoType invalid response', [
            'response' => json_encode($response)
        ]);

        throw new \Exception('invalid sso type');
    }

    protected function anonymizeId($id)
    {
        return sha1($id . md5($this->casClientId) . md5($this->casClientKey));
    }

    protected function getCacheKey($clientId, User $user, $uai, array $deviceData = null)
    {
        return 'nr:' . $clientId . ':' . $user->getId() . ':' . $uai . ':' . date('dmY') . ':' . implode(':', $deviceData);
    }

    protected function hydrateResource($resource, $groupId, $profile = null)
    {
        // TODO filter by profile
        if ($this->debug) {
            $this->logger->debug('NathanResourceManager:hydrateResource', [
                'ressource' => var_export($resource, true)
            ]);
        }
        $media = new Media();
        $media->setLabel(isset($resource->TitreRessource) ? $resource->TitreRessource : '');
        $media->setExternalSource(MediaPeer::EXTERNAL_SOURCE_NATHAN);
        if (isset($resource->Description)) {
            $media->setDescription($resource->Description);
        }

        // TODO : Temporary set a link until nathan fix https issue
        // $resourceType = 'HTML';
        $resourceType = 'LINK';
        $media->setTypeUniqueName($resourceType);
        $media->setSize(0);
        $media->setId($groupId . '-' . $resource->IdRessource . '-nr');

        $content = '<iframe class="full-page" scrolling="auto" frameborder="0" bns-3pc-src="' . $resource->UrlAccesressource . '" ></iframe>';

        switch($resourceType)
        {
            case 'HTML':
                $media->setValue(serialize(['content' => $content]));
                break;
            case 'FILE':
                $media->setDownloadUrl($resource->Urltelechargement);
                break;
            case 'DOCUMENT':
                $media->setDownloadUrl($resource->Urltelechargement);
                break;
            case 'IMAGE':
                $media->setDownloadUrl($resource->Urltelechargement);
                break;
//            case 'HTML_BASE':
//                $media->htmlBase = $resource['html_base'];
//                break;
            case 'LINK':
                $media->setValue($resource->UrlAccesressource);
                break;
        }

        $media->setExternalId(md5($resource->UrlAccesressource));

        if (isset($resource->UrlVisuelRessource)) {
            $media->imageMediumUrl = $resource->UrlVisuelRessource;
            $media->imageThumbnailUrl = $resource->UrlVisuelRessource;
        }
        $media->provider = [
            'id' => md5(isset($resource->EditeurRessource) ? $resource->EditeurRessource : 'EDUMAX'),
            'name' => isset($resource->EditeurRessource) ? $resource->EditeurRessource : 'EDUMAX',
        ];

        return $media;
    }

    protected function getPupilMefCode(User $user)
    {
        if (!$user->isChild()) {
            return null;
        }
        $level = $user->getAafLevel();

        switch ($level) {
            case 'TPS':
                return '11100010001';
            case 'PS':
                return '11110010001';
            case 'MS':
                return '11120010001';
            case 'GS':
                return '11130010001';
            case 'CP':
                return '11210010002';
            case 'CE1':
                return '11220010002';
            case 'CE2':
                return '11230010002';
            case 'CM1':
                return '11240010002';
            case 'CM2':
                return '11250010002';
            case '1UPE2A':
                return '12110010003';
            case 'CLIS1D':
                return '14110010005';
        }

        return null;
    }

    protected function getUAI(Group $group)
    {
        $uai = $group->getUAI();
        if (!$uai) {
            $parent = $this->groupManager->setGroup($group)->getParent();
            if ($parent) {
                $uai = $parent->getUAI();
            }
        }

        return $uai ? : false;
    }

    protected function getDeviceTypeData()
    {
        $type = null;
        $os = null;
        if ($this->mobileDetector->isTablet()) {
            $type ='TABLET';
        } elseif ($this->mobileDetector->isMobile()) {
            $type = 'MOBILE';
        }

        if ($type) {
            if ($this->mobileDetector->isAndroidOS()) {
                $os = 'ANDROID';
            } elseif ($this->mobileDetector->isIOS()) {
                $os = 'IOS';
            }
        }

        return [
            'type' => $type,
            'os' => $os,
        ];
    }
}
