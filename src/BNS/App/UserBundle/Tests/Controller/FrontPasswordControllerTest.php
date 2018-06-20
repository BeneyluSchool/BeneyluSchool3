<?php
namespace BNS\App\UserBundle\Tests\Controller;

use BNS\App\CoreBundle\Model\GroupPeer;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\UserPeer;
use BNS\App\CoreBundle\Test\AppWebTestCase;
use BNS\Central\CoreBundle\Model\UserQuery;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class FrontPasswordControllerTest extends AppWebTestCase
{

    /**
     * @dataProvider userData
     */
    public function testResetAction($username, $success)
    {
        $client = $this->getAppClient();
        $container = $client->getContainer();
        $router = $container->get('router');

        $crawler = $client->request('GET', $router->generate('user_password_reset'));
        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
        $user = UserQuery::create()->filterByUsernameCanonical($username)->findOne();
        $this->assertNull($user->getPasswordRequestedAt());
        $this->assertNull($user->getConfirmationToken());

        $form = $crawler->selectButton($container->get('translator')->trans('BUTTON_SEND', [], 'USER'))->form([
            'password_reset_form[email]' => $user->getEmailCanonical()
        ]);

        $client->submit($form);

        if ($success) {
            $response = $client->getResponse();
            $this->assertEquals(302, $response->getStatusCode());
            $this->assertEquals($router->generate('user_password_reset_confirmation'), $response->headers->get('location'));
            $user->reload();
            $this->assertNotNull($user->getPasswordRequestedAt());
            $this->assertNotNull($user->getConfirmationToken());

            $client->restart();

            // second call should generate an error
            $crawler = $client->request('GET', $router->generate('user_password_reset'));
            $form = $crawler->selectButton($container->get('translator')->trans('BUTTON_SEND', [], 'USER'))->form([
                'password_reset_form[email]' => $user->getEmail()
            ]);
            $client->submit($form);
            $response = $client->getResponse();
            $this->assertEquals(302, $response->getStatusCode());
            $this->assertEquals($router->generate('user_password_reset_warn'), $response->headers->get('location'));

        } else {
            $errorText = $container->get('translator')->trans('EMAIL_NOT_FOUND', array(), 'USER');
            $this->assertEquals(200, $client->getResponse()->getStatusCode());
            $this->assertRegExp(
                '/' . preg_quote($errorText, '/') . '/',
                $client->getResponse()->getContent()
            );
        }
    }

    /**
     * @dataProvider userData
     */
    public function testResetSchoolNotEnabledAction($username)
    {
        $client = $this->getAppClient();
        $client->restart();
        $container = $client->getContainer();
        $router = $container->get('router');

        $crawler = $client->request('GET', $router->generate('user_password_reset'));

        $user = UserQuery::create()->filterByUsernameCanonical($username)->findOne();
        $this->assertNull($user->getPasswordRequestedAt());
        $this->assertNull($user->getConfirmationToken());

        $schoolTypeId = (int)GroupTypeQuery::create()
            ->filterBySimulateRole(false)
            ->filterByType('SCHOOL')
            ->select(['Id'])
            ->findOne();

        GroupQuery::create()
            ->filterByGroupTypeId($schoolTypeId)
            ->update(['Enabled' => 0]);

        GroupPeer::clearInstancePool();
        GroupPeer::clearRelatedInstancePool();

        $form = $crawler->selectButton($container->get('translator')->trans('BUTTON_SEND', [], 'USER'))->form([
            'password_reset_form[email]' => $user->getEmailCanonical()
        ]);

        $client->submit($form);

        $errorText = $container->get('translator')->trans('EMAIL_NOT_FOUND', array(), 'USER');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertRegExp(
            '/' . preg_quote($errorText, '/') . '/',
            $client->getResponse()->getContent()
        );
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\AccessDeniedException
     */
    public function testCantResetAdminPassword()
    {
        $client = $this->getAppClient();
        $container = $client->getContainer();

        $user = \BNS\App\CoreBundle\Model\UserQuery::create()->filterByLogin('administrateur')->findOne();

        $this->assertNull($user->getPassword());

        $container->get('bns.user_manager')->resetUserPassword($user);
    }


    public function testCanResetTeacherPassword()
    {
        $client = $this->getAppClient();
        $container = $client->getContainer();

        $user = \BNS\App\CoreBundle\Model\UserQuery::create()->filterByLogin('enseignant')->findOne();

        $this->assertNull($user->getPassword());

        $container->get('bns.user_manager')->resetUserPassword($user, false);

        $this->assertNotNull($user->getPassword());
    }


    public function userData()
    {
        return [
            ['administrateur', false],
            ['enseignant', true],
            ['enseignant2', true],
            ['administrateur', false],
            ['enseignant', true],
            ['enseignant2', true],
        ];
    }

    /**
     * @inheritDoc
     */
    protected function tearDown()
    {
        parent::tearDown();

        UserQuery::create()
            ->filterByUsernameCanonical(array_map(function($item) { return reset($item); }, $this->userData()))
            ->update([
                'PasswordRequestedAt' => null,
                'ConfirmationToken' => null,
            ]);
        UserPeer::clearInstancePool();

        $schoolTypeId = (int)GroupTypeQuery::create()
            ->filterBySimulateRole(false)
            ->filterByType('SCHOOL')
            ->select(['Id'])
            ->findOne();

        // reset groups
        GroupQuery::create()
            ->filterByGroupTypeId($schoolTypeId)
            ->update(['Enabled' => 1]);

        GroupPeer::clearInstancePool();
    }


}
