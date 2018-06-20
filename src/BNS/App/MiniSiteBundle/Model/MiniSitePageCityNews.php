<?php

namespace BNS\App\MiniSiteBundle\Model;
use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Model\GroupQuery;
use Symfony\Component\Validator\Context\ExecutionContextInterface;


/**
 * Skeleton subclass for representing a row from one of the subclasses of the 'mini_site_page_news' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.MiniSiteBundle.Model
 */
class MiniSitePageCityNews extends MiniSitePageNews {

    /**
     * Constructs a new MiniSitePageCityNews class, setting the class_key column to MiniSitePageNewsPeer::CLASSKEY_2.
     */
    public function __construct()
    {
        parent::__construct();
        $this->setClassKey(MiniSitePageNewsPeer::CLASSKEY_2);
    }

    public function getSchools()
    {
        $container = BNSAccess::getContainer();
        if ($this->getIsAllSchools()) {
            $city = $this->getMiniSitePage()->getMiniSite()->getGroup();

            return $container->get('bns.group_manager')->setGroup($city)->getSubgroupsByGroupType('SCHOOL');
        } else {
            $groupIds = [];
            foreach ($this->getDistributionLists() as $list) {
                $groupIds = array_merge($groupIds, $list->getGroupIds());
            }

            return GroupQuery::create()
                ->filterById(array_unique($groupIds))
                ->useGroupTypeQuery()
                    ->filterByType('SCHOOL')
                ->endUse()
                ->find();
        }
    }

    public function validatePublicationDates(ExecutionContextInterface $context)
    {
        if ($this->getPublishedAt() && $this->getPublishedEndAt() && !($this->getPublishedAt() <= $this->getPublishedEndAt())) {
            $context->buildViolation('INVALID_PUBLISHED_AT_AFTER_END_AT')
                ->atPath('published_at')
                ->addViolation()
            ;
        }
    }

    public function validateSchools(ExecutionContextInterface $context)
    {
        if (!$this->getIsAllSchools() && !$this->getDistributionLists()->count()) {
            $context->buildViolation('INVALID_EMPTY_DISTRIBUTION_LIST')
                ->atPath('distributionLists')
                ->addViolation()
            ;
        }
    }

} // MiniSitePageCityNews
