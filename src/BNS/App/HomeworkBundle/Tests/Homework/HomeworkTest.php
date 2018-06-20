<?php

namespace BNS\App\HomeworkBundle\Tests\Homework;

use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Test\AppWebTestCase;
use Symfony\Bundle\FrameworkBundle\Client;

/**
 * Class HomeworkTest
 *
 * @package BNS\App\HomeworkBundle\Tests\Homework
 */
class HomeworkTest extends AppWebTestCase
{

    public function testSetup()
    {
        $username = $this->users()[0][0];
        $this->ensureAppIsOpen('HOMEWORK', $username);
    }

    /**
     * @dataProvider users
     */
    public function testAccess($username, $isTeacher, $isPupil, $isParent)
    {
        $client = $this->getAppClient();
        $currentMonday = (new \DateTime('monday this week'))->format('Y-m-d');
        $currentTuesday = (new \DateTime('tuesday this week'))->format('Y-m-d');

        $client->request('GET', '/api/1.0/homeworks/week/'.$currentMonday);
        $response = $client->getResponse();
        $this->assertEquals(401, $response->getStatusCode(), 'No anonymous access');

        $this->logIn($username);

        $client->request('GET', '/api/1.0/homeworks/week/'.$currentMonday);
        $response = $client->getResponse();
        if ($isTeacher) {
            $content = json_decode($response->getContent(), true);
            $this->assertEquals(200, $response->getStatusCode());
            $this->assertEquals(2, count($content), '2 homeworks are visible this week');
            $homework = $content[0];
            $this->assertEquals($currentMonday, $homework['due_date']);
            $this->assertArrayHasKey('done', $homework);
        } else {
            $this->assertEquals(403, $response->getStatusCode(), 'No back access');
        }

        $client->request('GET', '/api/1.0/homeworks/day/'.$currentMonday);
        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
        $homeworks = json_decode($response->getContent(), true);
        $this->assertEquals(1, count($homeworks));

        $client->request('GET', '/api/1.0/homeworks/day/'.$currentTuesday);
        $response = $client->getResponse();
        $homeworks = json_decode($response->getContent(), true);
        $this->assertEquals(1, count($homeworks));
    }

    /**
     * @dataProvider users
     *
     * @param string $username
     * @param bool $isTeacher
     */
    public function testScheduledPublication($username, $isTeacher)
    {
        $client = $this->getAppClient();
        $nextWednesday = (new \DateTime('wednesday next week'))->format('Y-m-d');

        $this->logIn($username);

        if ($isTeacher) {
            $client->request('GET', '/api/1.0/groups/current.json');
            $response = $client->getResponse();
            $currentGroupData = json_decode($response->getContent(), true);
            $post = [
                'homeworks' => [
                    [
                        'date' => $nextWednesday,
                        'name' => 'Scheduled homework',
                        'description' => 'Test scheduled access',
                        'recurrence_type' => 'ONCE',
                        'recurrence_end_date' => $nextWednesday,
                        'groups' => [$currentGroupData['id']],
                    ]
                ],
            ];
            $client->request('POST', '/api/1.0/homeworks', $post);
            $response = $client->getResponse();
            $this->assertEquals(204, $response->getStatusCode());
        }

        $client->request('GET', '/api/1.0/homeworks/day/'.$nextWednesday);
        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
        $homeworks = json_decode($response->getContent(), true);
        $this->assertEquals(1, count($homeworks), 'Homework is visible');
    }

    /**
     * @dataProvider users
     *
     * @param string $username
     * @param bool $isTeacher
     */
    public function testScheduledPublicationFuture($username, $isTeacher)
    {
        $client = $this->getAppClient();
        $nextMonday = (new \DateTime('monday next week'))->format('Y-m-d');
        $nextWednesday = (new \DateTime('wednesday next week'))->format('Y-m-d');

        $this->logIn($username);

        if ($isTeacher) {
            // set publication date to next monday
            $response = $this->setPublicationDateForHomeworkDate($client, $nextWednesday, $nextMonday);
            $this->assertEquals('SCHED', json_decode($response->getContent(), true)['status']);
        }

        // check homework scheduled in the future
        $client->request('GET', '/api/1.0/homeworks/day/'.$nextWednesday);
        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
        $homeworkDues = json_decode($response->getContent(), true);
        $this->assertEquals($isTeacher ? 1 : 0, count($homeworkDues));
    }

    /**
     * @dataProvider users
     *
     * @param string $username
     * @param bool $isTeacher
     */
    public function testScheduledPublicationPast($username, $isTeacher)
    {
        $client = $this->getAppClient();
        $lastMonday = (new \DateTime('monday last week'))->format('Y-m-d');
        $nextWednesday = (new \DateTime('wednesday next week'))->format('Y-m-d');

        $this->logIn($username);

        if ($isTeacher) {
            // set publication date to last monday
            $response = $this->setPublicationDateForHomeworkDate($client, $nextWednesday, $lastMonday);
            $this->assertEquals('PUB', json_decode($response->getContent(), true)['status']);
        }

        // check homework scheduled in the past
        $client->request('GET', '/api/1.0/homeworks/day/'.$nextWednesday);
        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
        $homeworkDues = json_decode($response->getContent(), true);
        $this->assertEquals(1, count($homeworkDues));
    }

    /**
     * @dataProvider assignmentUsers
     *
     * @param string $username
     * @param bool $isTeacher
     */
    public function testAssignment($username, $isTeacher, $isInGroup, $isIndividual)
    {
        $client = $this->getAppClient();
        $nextFriday = (new \DateTime('friday next week'))->format('Y-m-d');

        $this->logIn($username);

        if ($isTeacher) {
            $client->request('GET', '/api/1.0/groups.json');
            $response = $client->getResponse();
            $groupsData = json_decode($response->getContent(), true);
            $teamId = null;
            foreach ($groupsData as $groupData) {
                if ('TEAM' === $groupData['type']) {
                    $teamId = $groupData['id'];
                    break;
                }
            }
            $this->assertNotNull($teamId);

            // get user that must be assigned individually
            foreach ($this->assignmentUsers() as $user) {
                if ($user[3]) {
                    break;
                }
            }
            $user = UserQuery::create()->findOneByLogin($user[0]);
            $this->assertNotNull($user);

            $post = [
                'homeworks' => [
                    [
                        'date' => $nextFriday,
                        'name' => 'Assigned homework',
                        'description' => 'Test assigned homework',
                        'recurrence_type' => 'ONCE',
                        'recurrence_end_date' => $nextFriday,
                        'groups' => [$teamId],
                        'users' => [$user->getId()],
                    ]
                ],
            ];
            $client->request('POST', '/api/1.0/homeworks', $post);
            $response = $client->getResponse();
            $this->assertEquals(204, $response->getStatusCode());
        }

        // check homework scheduled in the future
        $client->request('GET', '/api/1.0/homeworks/day/'.$nextFriday);
        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
        $homeworkDues = json_decode($response->getContent(), true);

        if ($isTeacher) {
            $this->assertEquals(1, count($homeworkDues), 'Teacher can see homework');
        } else if ($isInGroup) {
            $this->assertEquals(1, count($homeworkDues), 'User in group can see homework');
        } else if ($isIndividual) {
            $this->assertEquals(1, count($homeworkDues), 'User assigned individually can see homework');
        } else {
            $this->assertEquals(0, count($homeworkDues), 'Other user cannot see homework');
        }
    }

    public function users()
    {
        return [
            // login, isTeacher, isPupil, isParent
            ['enseignant2', true, false, false],
            ['eleve2', false, true, false],
            ['eleve2par', false, false, true],
        ];
    }

    public function assignmentUsers()
    {
        return [
            // login, isTeacher, isInGroup, isIndividual
            ['enseignant2', true, false, false],
            ['eleve1', false, true, false],
            ['eleve1par', false, true, false],
            ['eleve4', false, false, true],
            ['eleve4par', false, false, true],
            ['eleve5', false, false, false],
            ['eleve5par', false, false, false],
        ];
    }

    protected function setPublicationDateForHomeworkDate(Client $client, $date, $pubDate)
    {
        // get the first homework for the given date
        $client->request('GET', '/api/1.0/homeworks/day/'.$date);
        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
        $homeworkDues = json_decode($response->getContent(), true);
        $homeworkDue = $homeworkDues[0];

        // update publication date
        $homeworkId = $homeworkDue['_embedded']['homework']['id'];
        $client->request('PATCH', '/api/1.0/homeworks/'.$homeworkId, [
            'scheduled_publication' => true,
            'publication_date' => $pubDate,
        ]);
        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

        return $response;
    }

}
