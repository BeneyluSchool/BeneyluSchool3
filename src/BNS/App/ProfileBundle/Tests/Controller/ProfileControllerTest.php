<?php

namespace BNS\App\ProfileBundle\Tests\Controller;

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Test\AppWebTestCase;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\UserQuery;

class ProfileControllerTest extends AppWebTestCase
{

    /**
     * List of usernames to test with
     *
     * @return array
     */
    public function users()
    {
        return [
            ['enseignant'],
            ['eleve'],
        ];
    }

    public function testSetup()
    {
        $this->ensureProfileIsOpen();
    }

    /**
     * Tests display of previous connection date (or welcome message for first
     * connection) in the classroom.
     *
     * @dataProvider users
     * @depends testSetup
     *
     * @param string $username
     */
    // public function testPreviousConnectionClassroomInfo($username)
    // {
    //     $this->doTestPreviousConnectionDisplay($username, 'classroom');
    // }

    /**
     * @dataProvider users
     * @depends testSetup
     *
     * @param string $username
     */
    public function testPreviousConnectionProfileInfo($username)
    {
        $this->doTestPreviousConnectionDisplay($username, 'profile');
    }

    protected function doTestPreviousConnectionDisplay($username, $where)
    {
        $client = $this->getAppClient();
        $container = $client->getContainer();
        $translator = $container->get('translator');
        $dateI18n = $container->get('date_i18n');
        $user = UserQuery::create()->filterByLogin($username)->findOne();
        $date = new \DateTime();

        if ('classroom' === $where) {
            $url = $this->generateUrl('BNSAppClassroomBundle_front');
            $tokenFirstConnection = ($user->isAdult() ? 'ADULT' : 'CHILD') . '_WELCOME_FIRST_CONNECTION';
            $textFirstConnection = $translator->trans($tokenFirstConnection, [
                '%firstname%' => $user->getFirstName(),
            ], 'CLASSROOM');
            $tokenPreviousConnection = ($user->isAdult() ? 'ADULT' : 'CHILD') . '_WELCOME_PREVIOUS_CONNECTION';
            $textPreviousConnection = $translator->trans($tokenPreviousConnection, [
                '%firstname%' => $user->getFirstName(),
                '%date%' => $dateI18n->process($date, 'none', 'none', 'd LLLL'),
                '%time%' => $dateI18n->process($date, 'none', 'short')
            ], 'CLASSROOM');
        } else if ('profile' === $where) {
            $url = $this->generateUrl('profile_back_edit', [
                'userSlug' => $user->getSlug(),
            ]);
            $tokenFirstConnection = ($user->isAdult() ? 'ADULT' : 'CHILD') . '_FIRST_CONNECTION';
            $textFirstConnection = $translator->trans($tokenFirstConnection, [], 'PROFILE');
            $tokenPreviousConnection = 'PREVIOUS_CONNECTION';
            $textPreviousConnection = $translator->trans($tokenPreviousConnection, [
                '%date%' => $dateI18n->process($date, 'none', 'none', 'd LLLL'),
                '%time%' => $dateI18n->process($date, 'none', 'short')
            ], 'PROFILE');
        }

        $this->logIn($user->getLogin());

        $client->request('GET', $url);
        $this->assertContains($textFirstConnection, $client->getResponse()->getContent());

        $user->setPreviousConnection($date)->save();

        $client->request('GET', $url);
        $this->assertContains($textPreviousConnection, $client->getResponse()->getContent());

        $user->setPreviousConnection(null)->save();
    }

    protected function ensureProfileIsOpen()
    {
        $client = $this->getAppClient();
        $this->logIn('enseignant');

        $client->request('GET', '/api/1.0/groups/current.json');
        $response = $client->getResponse();
        $content = json_decode($response->getContent(), true);
        $groupId = $content['id'];

        $client->request('PATCH', sprintf(
            '/api/1.0/groups/%s/applications/%s/%s',
            $groupId,
            'PROFILE',
            'open'
        ));
        $response = $client->getResponse();

        $this->assertEquals($response->getStatusCode(), 204);
    }

}
