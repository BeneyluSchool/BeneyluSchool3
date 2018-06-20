<?php

namespace BNS\App\MiniSiteBundle\Command;

use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\MiniSiteBundle\Model\MiniSite;
use BNS\App\MiniSiteBundle\Model\MiniSitePagePeer;
use BNS\App\MiniSiteBundle\Model\MiniSiteQuery;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateCityPageCommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this
            ->setName('bns:minisite:create-city-page')
            ->setDescription('Create minisite city page')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $translator = $this->getContainer()->get('translator');
        $groupManager = $this->getContainer()->get('bns.group_manager');

        // ids of minisite already having a city page
        $minisitesWithCityPage = MiniSiteQuery::create()
            ->groupById()
            ->useMiniSitePageQuery()
                ->filterByType(MiniSitePagePeer::TYPE_CITY)
            ->endUse()
            ->select(['Id'])
            ->find()
            ->getArrayCopy()
        ;
        // ids of all cities
        $cities = GroupQuery::create()
            ->useGroupTypeQuery()
                ->filterByType('CITY')
            ->endUse()
            ->select(['Id'])
            ->find()
            ->getArrayCopy()
        ;
        // ids of all schools belonging to cities
        $schools = [];
        foreach ($cities as $cityId) {
            $subschools = GroupQuery::create()
                ->filterById($groupManager->getOptimisedAllSubGroupIds($cityId))
                ->useGroupTypeQuery()
                    ->filterByType('SCHOOL')
                ->endUse()
                ->select(['Id'])
                ->find()
                ->getArrayCopy()
            ;
            $schools = array_merge($schools, $subschools);
        }
        // ids of groups eligible for a city page
        $groupIds = array_unique(array_merge($cities, $schools));
        $minisiteData = MiniSiteQuery::create()
            ->filterById($minisitesWithCityPage, \Criteria::NOT_IN)
            ->useGroupQuery()
                ->filterById($groupIds)
                ->useGroupTypeQuery()
                    ->filterByType(['CITY', 'SCHOOL'])
                ->endUse()
            ->endUse()
            ->select(['Id', 'Group.Lang'])
            ->find()
        ;
        $count = 0;
        foreach ($minisiteData as $data) {
            $lang = $data['Group.Lang'] ?: 'fr';
            $translator->setLocale($lang);
            MiniSite::createCityPage($data['Id'], $translator);
            $count++;
        }

        $output->writeln(sprintf('%s pages created. %s were already here.', $count, count($minisitesWithCityPage)));
    }

}
