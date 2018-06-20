<?php

namespace BNS\App\PortalBundle\Model;

use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use BNS\App\MiniSiteBundle\Model\MiniSitePageQuery;
use BNS\App\MiniSiteBundle\Model\MiniSiteQuery;
use BNS\App\PortalBundle\Model\om\BasePortalWidget;
use BNS\App\UserDirectoryBundle\Model\DistributionListGroupQuery;

class PortalWidget extends BasePortalWidget
{

    public function isEnabled()
    {
        return $this->getEnabled();
    }

    public function getData($data)
    {
        $datas = $this->getDatas();
        if ($datas && is_array($datas) && isset($datas[$data])) {
            return $datas[$data];
        }

        return null;
    }

    public function getMedia($data)
    {
        if ($mediaId = $this->getData($data)) {
            return MediaQuery::create()->findPk($mediaId);
        }

        return null;
    }

    /**
     * @return mixed
     * @throws \PropelException
     */
    public function getMiniSites()
    {
        $groupIds = DistributionListGroupQuery::create()
            ->filterByDistributionListId($this->getData('lists'), \Criteria::IN)
            ->select(['groupId'])
            ->find()
            ->toArray();

        return MiniSiteQuery::create()
            ->filterByGroupId($groupIds)
            ->orderByTitle()
            ->find();
    }

    /**
     * @deprecated
     * @return string|mixed|array
     */
    public function getUnserializedDatas()
    {
        return $this->getDatas();
    }
}
