<?php

namespace BNS\App\MiniSiteBundle\Tests\MiniSite;

use BNS\App\CoreBundle\Test\AppWebTestCase;
use BNS\App\MiniSiteBundle\Model\MiniSitePage;
use BNS\App\MiniSiteBundle\Model\MiniSitePageCityNews;
use BNS\App\MiniSiteBundle\Model\MiniSitePageNewsPeer;
use BNS\App\MiniSiteBundle\Model\MiniSitePageNewsQuery;
use BNS\App\MiniSiteBundle\Model\MiniSitePagePeer;
use BNS\App\MiniSiteBundle\Model\MiniSitePageQuery;
use BNS\App\UserDirectoryBundle\Model\DistributionList;
use BNS\App\UserDirectoryBundle\Model\DistributionListGroup;
use BNS\App\UserDirectoryBundle\Model\DistributionListPeer;

/**
 * Class MiniSiteTest
 *
 * @package BNS\App\MiniSiteBundle\Tests\MiniSite
 */
class MiniSiteTest extends AppWebTestCase
{

    /**
     * @dataProvider usersAndGroups
     *
     * @param string $username
     * @param int $groupId
     * @param bool $inCity
     */
    public function testManagement($username, $groupId, $inCity)
    {
        $this->logIn($username);
        $client = $this->getAppClient();
        $translator = $client->getContainer()->get('translator');
        $router = $client->getContainer()->get('router');

        if ($inCity) {
            $minisiteData = $this->getMinisiteInfo($groupId);
            foreach ($minisiteData['pages'] as $page) {
                $this->assertNotEquals('CITY', $page['type'], 'The city news page of a city website is not visible in front');
            }
        }

        $this->setCurrentGroup($groupId);
        $crawler = $client->request('GET', $router->generate('BNSAppMiniSiteBundle_back'));
        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode(), 'Minisite is manageable');
        $this->assertContains(
            $translator->trans('TITLE_CITY_INFORMATIONS', [], 'MINISITE'),
            $crawler->filter('#minisite-page-list')->text(),
            'The city information page is listed'
        );

        $cityPage = $this->getCityPage($groupId);

        $client->request('GET', $router->generate('minisite_manager_page', [
            'slug' => $cityPage->getSlug(),
        ]));
        $content = $client->getResponse()->getContent();

        $createRoute = $router->generate('minisite_manager_page_news_new', [
            'slug' => $cityPage->getSlug()
        ]);
        if ($inCity) {
            $this->assertContains('"'.$createRoute.'"', $content, 'City news can be created in city');
        } else {
            $this->assertNotContains('"'.$createRoute.'"', $content, 'City news cannot be created in school');
        }

        $previewRoute = $router->generate('minisite_page_preview', [
            'miniSiteSlug' => $cityPage->getMiniSite()->getSlug(),
            'pageSlug' => $cityPage->getSlug()
        ]);
        if ($inCity) {
            $this->assertNotContains('"'.$previewRoute.'"', $content, 'City page cannot be previewed in city');
        } else {
            $this->assertContains('"'.$previewRoute.'"', $content, 'City page can be previewed in school');
        }

        $switchRoute = $router->generate('minisite_manager_switch_activation_page');
        if ($inCity) {
            if ($cityPage->isHome()) {
                $this->assertNotContains('"'.$switchRoute.'"', $content, 'City homepage cannot be deactivated in city');
            } else {
                $this->assertContains('"'.$switchRoute.'"', $content, 'City page can be deactivated in city');
            }
        } else {
            $this->assertNotContains('"'.$switchRoute.'"', $content, 'City homepage cannot be deactivated in school');
        }

        $privatizeRoute = $router->generate('minisite_manager_page_confidentiality');
        $this->assertNotContains('"'.$privatizeRoute.'"', $content, 'City page cannot be privatized');

        if ($inCity) {
            $formName = 'mini_site_page_city_news_form';
            $crawler = $client->request('GET', $router->generate('minisite_manager_page_news_new', [
                'slug' => $cityPage->getSlug(),
            ]));
            $form = $crawler->filter('form[name="'.$formName.'"]')->form();
            $values = $form->getPhpValues();
            $values[$formName]['title'] =  'Test city news 1';
            $crawler = $client->request($form->getMethod(), $form->getUri(), $values, $form->getPhpFiles(), $values);
            $response = $client->getResponse();
            // actual twig error messages are enclosed in ul li
            $this->assertContains('<li>'.$translator->trans('INVALID_PUBLISHED_AT_EMPTY', [], 'validators').'</li>', $response->getContent());
            $this->assertContains('<li>'.$translator->trans('INVALID_EMPTY_DISTRIBUTION_LIST', [], 'validators').'</li>', $response->getContent());

            $form = $crawler->filter('form[name="'.$formName.'"]')->form();
            $values = $form->getPhpValues();
            $values[$formName]['title'] =  'Test city news 1';
            $values[$formName]['content'] =  'Test city news 1';
            $values[$formName]['status'] =  MiniSitePageNewsPeer::STATUS_PUBLISHED;
            $values[$formName]['published_at'] = (new \DateTime('today'))->format('Y-m-d');
            $values[$formName]['published_end_at'] = (new \DateTime('next month'))->format('Y-m-d');
            $values[$formName]['is_all_schools'] = true;
            $client->request($form->getMethod(), $form->getUri(), $values, $form->getPhpFiles(), $values);
            $this->assertEquals(302, $client->getResponse()->getStatusCode());
        }
    }

    /**
     * @dataProvider usersAndGroups
     *
     * @param string $username
     * @param int $groupId
     * @param bool $inCity
     */
    public function testOrder($username, $groupId, $inCity)
    {
        $data = $this->usersAndGroups();
        $schoolId = $data[1][1];
        $user = $this->logIn($username);
        $this->setCurrentGroup($groupId);
        $client = $this->getAppClient();
        $router = $client->getContainer()->get('router');

        if ($inCity) {
            $cityPage = $this->getCityPage($groupId);
            $cityPage->getMiniSitePageNewss()->delete();

            $dl = new DistributionList();
            $dl->setName('Test distribution list');
            $dl->setGroupId($groupId);
            $dl->setType(DistributionListPeer::TYPE_STRUCT);
            $dl->save();
            $dlg = new DistributionListGroup();
            $dlg->setGroupId($schoolId);
            $dlg->setDistributionList($dl);
            $dlg->save();

            $news1 = new MiniSitePageCityNews();
            $news1->setTitle('City news 1');
            $news1->setPublishedAt('monday last month');
            $news1->setPublishedEndAt('next month');
            $news1->setIsAllSchools(true);
            $news1->setMiniSitePage($cityPage);
            $news1->setUser($user);
            $news1->save();

            $news2 = new MiniSitePageCityNews();
            $news2->setTitle('City news 2');
            $news2->setPublishedAt('last monday');
            $news2->setPublishedEndAt('next month');
            $news2->addDistributionList($dl);
            $news2->setMiniSitePage($cityPage);
            $news2->setUser($user);
            $news2->save();

            $this->assertEquals(2, $cityPage->getMiniSitePageNewss(new \Criteria())->count());
        }
    }

    public function users()
    {
        return [
            // login, isCityReferent, isSchoolReferent
            ['referentville2', true, false],
            ['directeur2', false, true],
        ];
    }

    public function groups()
    {
        return [
            // id, isCity, isSchool
            [138002, true, false ],
            [138003, false, true ],
        ];
    }

    public function usersAndGroups()
    {
        return [
            // username, groupId, inCity
            ['referentville2', 138002, true],
            ['directeur2', 138003, false],
        ];
    }

    protected function getMinisiteInfo($groupId)
    {
        $client = $this->getAppClient();
        $client->request('GET', '/api/1.0/groups/'.$groupId.'/minisite');
        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode(), 'Minisite exists');
        $minisiteData = json_decode($response->getContent(), true);

        $client->request('GET', '/api/1.0/minisite/'.$minisiteData['minisite']['slug']);
        $response = $client->getResponse();
        $minisiteData = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('pages', $minisiteData, 'Minisite full info is available');

        return $minisiteData;
    }

    /**
     * @param $groupId
     * @return MiniSitePage
     */
    protected function getCityPage($groupId)
    {
        $cityPage = MiniSitePageQuery::create()
            ->filterByType(MiniSitePagePeer::TYPE_CITY)
            ->useMiniSiteQuery()
                ->filterByGroupId($groupId)
            ->endUse()
            ->findOne()
        ;
        $this->assertNotNull($cityPage);

        return $cityPage;
    }

}
