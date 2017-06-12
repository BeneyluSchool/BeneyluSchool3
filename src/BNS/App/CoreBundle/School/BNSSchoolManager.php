<?php

namespace BNS\App\CoreBundle\School;

use BNS\App\CoreBundle\Api\BNSApi;
use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Module\BNSModuleManager;
use BNS\App\CoreBundle\Module\IBundleActivation;
use BNS\App\CoreBundle\Role\BNSRoleManager;
use BNS\App\CoreBundle\User\BNSUserManager;
use BNS\App\MailerBundle\Mailer\BNSMailer;
use BNS\App\CoreBundle\Model\UserQuery;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @author Eymeric Taelman
 *
 * Classe permettant la gestion des écoles, hérite du GroupManager
 */
class BNSSchoolManager extends BNSGroupManager implements IBundleActivation
{
    protected $classroom;

    /**
     * @var BNSMailer
     */
    protected $mailer;

    /**
     * @param ContainerInterface $container
     * @param BNSRoleManager $roleManager
     * @param BNSUserManager $userManager
     * @param BNSApi $api
     * @param BNSModuleManager $moduleManager
     * @param int $domainId
     * @param BNSMailer $mailer
     */
    public function __construct($container, $roleManager, $userManager, $api, $moduleManager, $domainId, $mailer)
    {
        parent::__construct($container, $roleManager, $userManager, $api, $moduleManager, $domainId);

        $this->mailer        = $mailer;
    }

    /**
     * Archive TOUT le contenu d'une école, sans supprimer l'école elle même
     * L'école repart ainsi à zéro
     * @param String $uai
     */
    public function archiveSchoolContent($uai)
    {
        $schoolQuery = GroupQuery::create()
            ->useGroupTypeQuery()
                ->filterByType('SCHOOL')
            ->endUse();

        $schools =
            $schoolQuery->filterBySingleAttribute('UAI', $uai)
            ->find();

        if(!$schools)
        {
            $schools =
                $schoolQuery->filterById($uai)
                    ->find();
        }

        if(!$schools)
        {
            throw new \Exception("School not Found");
        }

        if(count($schools) > 1)
        {
            throw new \Exception("Several Schools found, please check Schools before");
        }
        $school = $schools->getFirst();
        $this->setGroup($school);


        // 1 - On désassigne tous les utilisateurs de l'école en question
        foreach($this->getUsersIds() as $userId)
        {
            $this->roleManager->unassignRole($userId, $school->getId());
        }

        foreach($this->getSubgroups(true, false, 2) as $classroom)
        {
            $this->deleteGroup($classroom->getId());
        }
        $this->api->resetGroup($school->getId(), true);
        return $school;
    }

    /**
     * Checks if given user is invited in the currently set school
     *
     * @param User $user
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function isInvitedInSchool(User $user)
    {
        return $this->isInvitedInGroup($user, $this->roleManager->findGroupTypeRoleByType('TEACHER'));
    }

    /**
     * Issues an invitation for the given user to be teacher in the current school, by the given author.
     *
     * @param User $user
     * @param User $author
     * @throws \InvalidArgumentException
     */
    public function inviteTeacherInSchool(User $user, User $author)
    {
        $this->inviteUserInGroup($user, $author, $this->roleManager->findGroupTypeRoleByType('TEACHER'));
    }

    /**
     * Gets all teachers who have an invitation pending in the current school
     *
     * @return User[]\PropelObjectCollection
     */
    public function getInvitedTeachers()
    {
        $response = $this->api->send('invitation_search', array(
            'values' => array(
                'group_id' => $this->getGroup()->getId(),
                'role_id' => $this->roleManager->findGroupTypeRoleByType('TEACHER')->getId(),
            ),
        ));

        $userIds = array();
        foreach ($response as $invitation) {
            $userIds[] = $invitation['user_id'];
        }

        return UserQuery::create()->findPks($userIds);
    }
}
