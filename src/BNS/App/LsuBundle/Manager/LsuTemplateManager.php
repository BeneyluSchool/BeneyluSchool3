<?php
namespace BNS\App\LsuBundle\Manager;

use BNS\App\LsuBundle\Model\LsuConfig;
use BNS\App\LsuBundle\Model\LsuDomain;
use BNS\App\LsuBundle\Model\LsuDomainQuery;
use BNS\App\LsuBundle\Model\LsuPositionQuery;
use BNS\App\LsuBundle\Model\LsuQuery;
use BNS\App\LsuBundle\Model\LsuTemplate;
use BNS\App\LsuBundle\Model\LsuTemplateDomainDetailQuery;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class LsuTemplateManager
{

    /**
     * @param LsuTemplate $template
     * @param LsuConfig $config
     */
    public function setCompletion(LsuTemplate $template, LsuConfig $config = null)
    {
        if (!$config || $config->getId() != $template->getConfigId()) {
            $config = $template->getLsuConfig();
        }

        $userIds = $config->getUserIds();

        $total = count($userIds);
        $templateDomains = LsuDomainQuery::create()
            ->filterByCode(null, \Criteria::ISNOTNULL)
            ->useLsuTemplateDomainDetailQuery(null, \Criteria::LEFT_JOIN)
                ->filterByLsuTemplate($template)
            ->endUse()
            ->_or()
            ->filterByCycle('socle')
            ->groupById()
            ->find()
        ;

        $lsuIds = LsuQuery::create()
            ->filterByLsuTemplate($template)
            ->filterByUserId($userIds)
            ->select(['Id'])
            ->find()
            ->getArrayCopy()
        ;

        $nbPositionDomain = count($templateDomains);
        $lsuPositions = LsuPositionQuery::create()
            ->filterByAchievement(null, \Criteria::ISNOTNULL)
            ->filterByLsuId($lsuIds)
            ->filterByLsuDomain($templateDomains)
            ->joinWith('Lsu')
            ->withColumn('count(lsu_position.lsu_id)', 'nbPosition')
            ->select(['LsuId', 'Lsu.UserId', 'nbPosition'])
            ->groupByLsuId()
            ->find()
        ;

        $validatedLsus = LsuQuery::create()
            ->filterById($lsuIds)
            ->filterByValidated(true)
            ->select(['Id'])
            ->find()
            ->getArrayCopy()
        ;

        $userCompletions = [];
        foreach ($lsuPositions as $position) {
            $userCompletions[$position['Lsu.UserId']] = [
                'positions' => (int)$position['nbPosition'],
                'validated' => in_array($position['LsuId'], $validatedLsus),
            ];
        }

        $template->setTotalcompletion($total);
        $template->setCompletion(count($validatedLsus));
        $template->setUserCompletions($userCompletions);
        $template->setCompletionDomains($nbPositionDomain);

    }

    public function getRootDomain(LsuTemplate $template, LsuConfig $config = null, $version = 'v2016')
    {
        if (!$config || $config->getId() != $template->getConfigId()) {
            $config = $template->getLsuConfig();
        }

        $level = $config->getLsuLevel();
        $cycle = $level->getCycle();

        $rootDomain = LsuDomainQuery::create()
            ->findRoot($version);
        if ($rootDomain) {
            $rootCycleDomain = LsuDomainQuery::create()
                ->childrenOf($rootDomain)
                ->filterByTreeLevel(1)
                ->filterByLabel($cycle)
                ->findOne()
            ;

            return $rootCycleDomain;
        }

        return null;
    }

    public function copy(LsuTemplate $template)
    {
        $newTemplate = $template->copy();
        $newTemplate->setPeriod('Copie de '.$template->getPeriod());
        $newTemplate->setIsOpen(false);
        $newTemplate->setValidated(false);
        foreach ($template->getLsuTemplateDomainDetails() as $detail) {
            $newTemplate->addLsuTemplateDomainDetail($detail->copy());
        }

        return $newTemplate;
    }
}
