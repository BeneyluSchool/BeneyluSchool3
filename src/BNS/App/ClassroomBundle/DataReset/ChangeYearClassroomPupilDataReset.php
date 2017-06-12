<?php

namespace BNS\App\ClassroomBundle\DataReset;

use BNS\App\ClassroomBundle\Form\Type\ChangeYearClassroomPupilDataResetType;
use BNS\App\CoreBundle\Model\BlogQuery;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\UserQuery;
use Symfony\Component\Validator\ExecutionContext;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class ChangeYearClassroomPupilDataReset extends AbstractDataReset
{
    /**
     * @var string
     */
    public $choice;

    /**
     * @var string
     */
    public $uid;

    /**
     * @var \BNS\App\CoreBundle\Classroom\BNSClassroomManager
     */
    private $classroomManager;

    /**
     * @var \BNS\App\CoreBundle\Right\BNSRightManager
     */
    private $rightManager;

    /**
     * @var \BNS\App\CoreBundle\User\BNSUserManager
     */
    private $userManager;

    /**
     * @var DataResetManager
     */
    private $dataResetManager;

    /**
     * @var string
     */
    private $secret;

    /**
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $classroomManager
     * @param string $secret
     */
    public function __construct($container, $secret)
    {
        $this->classroomManager = $container->get('bns.classroom_manager');
        $this->dataResetManager = $container->get('bns.data_reset.manager');
        $this->rightManager     = $container->get('bns.right_manager');
        $this->userManager      = $container->get('bns.user_manager');
        $this->secret           = $secret;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'change_year_classroom_pupil';
    }

    /**
     * @param Group $group
     */
    public function reset($group)
    {
        // KEEP OR DELETE OR TRANSFER
        $this->classroomManager->setGroup($group);

        $usersId  = array();
        $allUsersId  = array();
        $pupils   = $this->classroomManager->getPupils(false);
        $parents  = $this->classroomManager->getPupilsParents(false);
        $teachers = $this->classroomManager->getTeachers(false);

        foreach ($pupils as $pupil) {
            $usersId[] = $pupil['id'];
        }

        foreach ($parents as $parent) {
            $usersId[] = $parent['id'];
        }

        foreach($teachers as $teacher)
        {
            $allUsersIds[] = $teacher['id'];
        }

        $allUsersIds = array_merge($allUsersIds, $usersId);

        // Nettoyage des comptes parents en trop
        $parentsNoPupil = $this->classroomManager->getUsersByRoleUniqueName('PARENT',true);
        $school = $this->classroomManager->getParent();
        foreach($parentsNoPupil as $parentNoPupil)
        {
            $this->classroomManager->setGroup($school);
            $children = $parentNoPupil->getChildren();
            if(count($children) > 0)
            {
                /** @var User $parentNoPupil*/
                foreach($parentNoPupil->getChildren() as $child)
                {
                    if(!in_array($child->getId(), $usersId))
                    {
                        $this->classroomManager->removeUser($parentNoPupil);
                    }
                }
            }else{
                $this->userManager->deleteUser($parentNoPupil);
            }

        }

        $this->classroomManager->setGroup($group);

        // TRANSFER process
        if ('TRANSFER' == $this->choice) {
            $groupId = substr($this->uid, 10, strlen($this->uid));
            $transferGroup = GroupQuery::create('g')
                ->join('g.GroupType gt')
                ->where('gt.Type = ?', 'CLASSROOM')
            ->findPk($groupId);
            $this->classroomManager->setGroup($transferGroup);

            // Pupils
            $pupilsId = array();
            foreach ($pupils as $pupil) {
                $pupilsId[] = $pupil['id'];
            }

            $pupils = UserQuery::create('u')
                ->where('u.Id IN ?', $pupilsId)
            ->find();

            $this->classroomManager->migratePupils($pupils, $transferGroup);

            // Parents
            $parentsId = array();
            foreach ($parents as $parent) {
                $parentsId[] = $parent['id'];
            }

            $parents = UserQuery::create('u')
                ->where('u.Id IN ?', $parentsId)
            ->find();

            $this->classroomManager->migrateParents($parents, $transferGroup);

            // Clear cache
            $this->classroomManager->clearGroupCache();

            // Set initial group for remove
            $this->classroomManager->setGroup($group);

            $this->classroomManager->removeUsers($usersId);

            $this->classroomManager->clearGroupCache();
        }

        // Data reset users process
        $dataResetUsers = $this->dataResetManager->getDataResetUsers('change_year');
        foreach ($dataResetUsers as $dataReset) {
            $dataReset->reset($allUsersIds);
        }

        if ('DELETE' == $this->choice) {
            //On attribue les articles du blog à l'utilisateur en cours
            $blog = BlogQuery::create()->findOneByGroupId($group->getId());
            $teacherId = $this->rightManager->getUserSessionId();
            foreach($blog->getBlogArticles() as $article)
            {
                $article->setAuthorId($teacherId);
                $article->save();
            }

            foreach($pupils as $pupil)
            {
                $this->userManager->deleteUser(UserQuery::create()->findOneById($pupil['id']));
            }

            $this->classroomManager->clearGroupCache();

            // On ne supprime pas les comptes parents au cas où ils aient fusionné
            $usersId = array();
            foreach ($parents as $parent) {
                $usersId[] = $parent['id'];
            }
            $this->classroomManager->removeUsers($usersId);
            $this->classroomManager->clearGroupCache();
        }
    }

    /**
     * @return string
     */
    public function getRender()
    {
        return 'BNSAppClassroomBundle:DataReset:change_year_classroom_pupil.html.twig';
    }

    /**
     * @return \BNS\App\ClassroomBundle\Form\Type\ChangeYearClassroomPupilDataResetType
     */
    public function getFormType()
    {
        return new ChangeYearClassroomPupilDataResetType();
    }

    /**
     * @return array<String, String>
     */
    public static function getChoices()
    {
        return array(
            'KEEP'     => 'CHOICE_KEEP_PUPIL',
            'DELETE'   => 'CHOICE_DELETE_PUPIL',
            'TRANSFER' => 'CHOICE_TRANSFER_PUPIL_TO_OTHER_CLASS'
        );
    }

    /**
	 * Constraint validation on TRANSFER #uid
	 */
	public function isValidUid($context)
	{
        if ('TRANSFER' == $this->choice) {
            $length = strlen($this->uid);
            // Invalid UID
            if ($length < 10 || $length > 17) {
                return $context->buildviolation('INVALID_TRANSFERT_CODE')
                    ->atPath('uid')
                    ->setTranslationDomain('CLASSROOM')
                    ->addviolation();
            }

            $groupId = substr($this->uid, 10, $length);
            $group = GroupQuery::create('g')
                ->join('g.GroupType gt')
                ->where('gt.Type = ?', 'Classroom')
            ->findPk($groupId);

            if(!$group)
            {
                return $context->buildviolation('INVALID_TRANSFERT_CODE')
                    ->atPath('uid')
                    ->setTranslationDomain('CLASSROOM')
                    ->addviolation();
            }

            /*
            $targetParentGroup  = $this->classroomManager->setGroup($group)->getParent();
            $currentParentGroup = $this->classroomManager->setGroup($this->rightManager->getCurrentGroup())->getParent();

            // NOT same school
            if ($targetParentGroup->getId() != $currentParentGroup->getId()) {
                return $context->buildviolation('CANT_TRANFERT_PUPILIN_SAME_SCHOOL')
                    ->atPath('uid')
                    ->setTranslationDomain('CLASSROOM')
                    ->addviolation();
            }
            */

            // Wrong UID OR group is NOT found OR trying to transfer on his own group
            if (substr($this->uid, 0, 10) != strtoupper(substr(sha1($groupId . $this->secret), 5, 10)) ||
                null == $group ||
                $groupId == $this->rightManager->getCurrentGroupId()) {
                return $context->buildviolation('INVALID_TRANSFERT_CODE')
                    ->atPath('uid')
                    ->setTranslationDomain('CLASSROOM')
                    ->addviolation();
            }
        }
	}
}
