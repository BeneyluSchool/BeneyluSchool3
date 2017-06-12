<?php

namespace BNS\App\UserBundle\ApiController;

use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Utils\StringUtil;
use BNS\App\ProfileBundle\Form\Type\AuthenticationType;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcherInterface;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class UserLinkApiController
 *
 * @package BNS\App\UserBundle\ApiController
 */
class UserLinkApiController extends BaseApiController
{

    /**
     * @ApiDoc(
     *  section="Users - Link",
     *  resource = true,
     *  description="Gets a previous account by credentials",
     * )
     *
     * @Rest\Post("/users/by-credentials")
     * @Rest\View()
     */
    public function getUserByCredentialsAction()
    {
        $data = [
            'login' => '',
            'password' => '',
        ];

        return $this->restForm(new AuthenticationType(), $data, [
            'csrf_protection' => false, // TODO
        ], null, function ($data) {
            $user = $this->getLinkableUserByLoginPassword($data['login'], $data['password']);

            return $user ? $user : View::create(null, Codes::HTTP_NOT_FOUND);
        });
    }

    /**
     * @ApiDoc(
     *  section="Users - Link",
     *  resource = true,
     *  description="Preview of the previous classrooms of the current user, or the one identified by login/password",
     * )
     *
     * @Rest\Get("/previous-groups/{type}", requirements={"type":"classroom|school"})
     * @Rest\QueryParam(name="user_login", description="Login of the old user to recover", nullable=true)
     * @Rest\QueryParam(name="user_password", description="Password of the old user to recover", nullable=true)
     * @Rest\View(serializerGroups={"Default", "list", "group_preview"})
     *
     * @param ParamFetcherInterface $paramFetcher
     * @param string $type
     * @return View
     */
    public function getPreviousGroupsAction(ParamFetcherInterface $paramFetcher, $type)
    {
        $userLogin = $paramFetcher->get('user_login');
        $userPassword = $paramFetcher->get('user_password');
        $currentUser = $this->getUser();
        if ($userLogin && $userPassword) {
            $user = $this->getLinkableUserByLoginPassword($userLogin, $userPassword);
            if (!$user) {
                return View::create(null, Codes::HTTP_NOT_FOUND);
            }
            $previousGroupIds = $this->getCurrentGroupIds($user);
        } else {
            $previousGroupIds = $this->getPreviousGroupIds($currentUser);
        }

        return GroupQuery::create()
            ->filterById($previousGroupIds)
            ->filterByAafLinked(null)
            ->useGroupTypeQuery()
                ->filterByType(strtoupper($type))
            ->endUse()
            ->find()
        ;
    }

    /**
     * @ApiDoc(
     *  section="Users - Link",
     *  resource = true,
     *  description="Post the recovery configuration"
     * )
     *
     * @Rest\Post("/recovery")
     * @Rest\RequestParam(name="school_id", requirements="\d+", description="ID of the old school to recover", nullable=true)
     * @Rest\RequestParam(name="school_data", description="Array of apps unique names to recover", array=true)
     * @Rest\RequestParam(name="classroom_id", requirements="\d+", description="ID of the old classroom to recover", nullable=true)
     * @Rest\RequestParam(name="classroom_data", description="Array of apps unique names to recover", array=true)
     * @Rest\RequestParam(name="user_login", description="Login of the old user to recover", nullable=true)
     * @Rest\RequestParam(name="user_password", description="Password of the old user to recover", nullable=true)
     * @Rest\RequestParam(name="user_data", description="Array of apps unique names to recover", array=true)
     * @Rest\RequestParam(name="pupils_map", description="Map new ID => old ID pupil accounts to merge", array=true)
     * @Rest\View()
     *
     * @param ParamFetcherInterface $paramFetcher
     * @return array
     */
    public function postRecoveryAction(ParamFetcherInterface $paramFetcher)
    {
        /** @var User $user */
        $user = $this->getUser();
        $group = $this->get('bns.right_manager')->getCurrentGroup();
        $this->get('bns.right_manager')->forbidIfHasNotRight('CLASSROOM_ACCESS_BACK', $group->getId());

        $configuration = [];

        $userLogin = $paramFetcher->get('user_login');
        $userPassword = $paramFetcher->get('user_password');
        $userData = $paramFetcher->get('user_data');
        $schoolData = $paramFetcher->get('school_data');
        $schoolId = $paramFetcher->get('school_id');
        $classroomData = $paramFetcher->get('classroom_data');
        $classroomId = $paramFetcher->get('classroom_id');
        $pupilsMap = $paramFetcher->get('pupils_map');

        // user
        if ($userLogin && $userPassword) {
            $oldUser = $this->getLinkableUserByLoginPassword($userLogin, $userPassword);
            if (!$oldUser) {
                throw new NotFoundHttpException('User not found');
            }

            if (count($userData)) {
                $configuration['user']['new_id'] = $user->getId();
                $configuration['user']['old_id'] = $oldUser->getId();
                $configuration['user']['data'] = $userData;
            }
        }

        // if a reference user has been given, use it instead of current user
        if (isset($oldUser)) {
            $previousGroupIds = $this->getCurrentGroupIds($oldUser);
        } else {
            $previousGroupIds = $this->getPreviousGroupIds($user);
        }

        // school
        if ($schoolId) {
            if (!in_array($schoolId, $previousGroupIds)) {
                throw new NotFoundHttpException('SChool not found');
            }

            $school = GroupQuery::create()->findPk($schoolId);
            if (!$school) {
                throw new NotFoundHttpException('School not found');
            }

            if (count($schoolData)) {
                $groupManager = $this->get('bns.group_manager');
                $groupManager->setGroup($group);
                $newSchool = $groupManager->getParent();
                $configuration['school'] = [
                    'new_id' => $newSchool->getId(),
                    'old_id' => $school->getId(),
                    'data' => $schoolData,
                ];
            }
        }

        // classroom
        if ($classroomId) {
            if (!in_array($classroomId, $previousGroupIds)) {
                throw new NotFoundHttpException('Classroom not found');
            }

            $classroom = GroupQuery::create()->findPk($classroomId);
            if (!$classroom) {
                throw new NotFoundHttpException('Classroom not found');
            }

            if (count($classroomData)) {
                $configuration['classroom'] = [
                    'new_id' => $group->getId(),
                    'old_id' => $classroom->getId(),
                    'data' => $classroomData,
                ];
            }

            if (count($pupilsMap)) {
                $idsByRole = $this->get('bns.user_directory.manager')->getUserIdsByRoles($group, null);
                $newPupilIds = isset($idsByRole['PUPIL']) ? $idsByRole['PUPIL'] : [];
                $idsByRole = $this->get('bns.user_directory.manager')->getUserIdsByRoles($classroom);
                $oldPupilIds = isset($idsByRole['PUPIL']) ? $idsByRole['PUPIL'] : [];

                foreach ($pupilsMap as $newId => $oldId) {
                    if (!in_array($newId, $newPupilIds)) {
                        throw new NotFoundHttpException('New pupil not found for ID: '.$newId);
                    }
                    if (!in_array($oldId, $oldPupilIds)) {
                        throw new NotFoundHttpException('Old pupil not found for ID: '.$oldId);
                    }
                }
                $configuration['classroom']['new_id'] = $group->getId();
                $configuration['classroom']['old_id'] = $classroom->getId();
                $configuration['classroom']['pupils'] = $pupilsMap;
            }
        }

        // mark current user as linked, to not bother him anymore
        $user->setAafLinked(true)->save();

        // check if there is something to do
        if (count($configuration)) {
            /** @var Producer $producer */
            $producer = $this->get('old_sound_rabbit_mq.account_link_producer');
            $producer->publish(json_encode($configuration));

            return ['recovery' => true];
        }

        return ['recovery' => false];
    }

    /**
     * @ApiDoc(
     *  section="Users - Link",
     *  resource = true,
     *  description="Preview of the previous parent accounts of the user",
     * )
     *
     * @Rest\Get("/parents/linkable")
     * @Rest\View(serializerGroups={"Default", "user_list", "user_children_preview"})
     */
    public function getLinkableParentsAction()
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($user->getAafLinked()) {
            return View::create(['redirect' => $this->generateUrl('home', [], true)], Codes::HTTP_BAD_REQUEST);
        }

        return array_values($this->getLinkableParents($this->getUser()));
    }

    /**
     * @ApiDoc(
     *  section="Users - Link",
     *  resource = true,
     *  description="Links the given parent account to the current user",
     * )
     *
     * @Rest\Post("/parents/link")
     * @Rest\RequestParam(name="user_id", requirements="\d+", description="ID of the parent account to link")
     * @Rest\View()
     *
     * @param ParamFetcherInterface $paramFetcher
     * @return array
     */
    public function postLinkParentAction(ParamFetcherInterface $paramFetcher)
    {
        $newId = $paramFetcher->get('user_id');
        $user = $this->getUser();
        $possibleParents = $this->getLinkableParents($user);
        if (!isset($possibleParents[$newId])) {
            throw new NotFoundHttpException('Parent not found');
        }

        if ($this->get('bns.user.account_link_manager')->linkParents($newId, $user->getId())) {
            $this->get('session')->remove('need_aaf_parent_confirmation');

            $redirect = $this->get('bns.user_manager')->onLogon();
            if (!$redirect) {
                $redirect = $this->generateUrl('home', [], true);
            }

            return [
                'linked' => true,
                'redirect' => $redirect
            ];
        }

        return ['linked' => false];
    }

    /**
     * @ApiDoc(
     *  section="Users - Link",
     *  resource = true,
     *  description="Get the pupil map between new and old groups"
     * )
     *
     * @Rest\Get("/pupils-map")
     * @Rest\QueryParam(name="new_group_id", requirements="\d+", description="ID of the new group")
     * @Rest\QueryParam(name="old_group_id", requirements="\d+", description="ID of the old group")
     * @Rest\View(serializerGroups={"Default", "list", "group_preview"})
     *
     * @param ParamFetcherInterface $paramFetcher
     * @return array|mixed|\PropelObjectCollection
     */
    public function getPupilsMapAction(ParamFetcherInterface $paramFetcher)
    {
        $newGroupId = $paramFetcher->get('new_group_id');
        $oldGroupId = $paramFetcher->get('old_group_id');
        if (!($newGroupId && $oldGroupId)) {
            return $this->view(null, Codes::HTTP_BAD_REQUEST);
        }

        $newGroup = $this->getClassroom($newGroupId);
        $oldGroup = $this->getClassroom($oldGroupId);

        $newPupils = $this->get('bns.user_directory.manager')->getPupilsPreview($newGroup, null);
        $oldPupils = $this->get('bns.user_directory.manager')->getPupilsPreview($oldGroup, null);

        $remaining = [];
        $matches = [];
        $mapped = [];
        foreach ($newPupils as $newPupil) {
            // first pass: exact match
            foreach ($oldPupils as $oldPupil) {
                if (isset($mapped[$oldPupil->getId()])) {
                    continue; // old pupil is already mapped
                }

                if ($this->isSamePupil($newPupil, $oldPupil)) {
                    $matches[$newPupil->getId()] = $oldPupil;
                    $mapped[$oldPupil->getId()] = $newPupil;
                    unset($remaining[$oldPupil->getId()]);
                    continue 2;
                }
            }

            // second pass: fuzzy match
            foreach ($oldPupils as $oldPupil) {
                if (isset($mapped[$oldPupil->getId()])) {
                    continue; // old pupil is already mapped
                }

                if ($this->isSamePupil($newPupil, $oldPupil, 2)) {
                    $matches[$newPupil->getId()] = $oldPupil;
                    $mapped[$oldPupil->getId()] = $newPupil;
                    unset($remaining[$oldPupil->getId()]);
                    continue 2;
                } else {
                    $remaining[$oldPupil->getId()] = $oldPupil;
                }
            }
        }

        return [
            'new' => array_values($newPupils->getArrayCopy('Id')),
            'matches' => $matches,
            'old' => array_values($remaining),
        ];
    }

    protected function getLinkableUserByLoginPassword($login, $password)
    {
        $currentUser = $this->getUser();
        $roles = $this->get('bns.user_manager')->canMergeUser($currentUser, $login, $password);

        if (count($roles)) {
            return UserQuery::create()
                ->filterByAafLinked(null)
                ->findOneByLogin($login)
            ;
        }

        return null;
    }

    protected function getClassroom($groupId)
    {
        $group = GroupQuery::create()
            ->filterById($groupId)
            ->filterByAafLinked(null)
            ->useGroupTypeQuery()
                ->filterByType('CLASSROOM')
            ->endUse()
            ->findOne();

        if (!$group) {
            throw new NotFoundHttpException();
        }

        return $group;
    }

    protected function isSamePupil(User $newPupil, User $oldPupil, $nameComparisonLength = null)
    {
        $newFirstName = strtolower(StringUtil::filterString($newPupil->getFirstName()));
        $newLastName = strtolower(StringUtil::filterString($newPupil->getLastName()));
        $oldFirstName = strtolower(StringUtil::filterString($oldPupil->getFirstName()));
        $oldLastName = strtolower(StringUtil::filterString($oldPupil->getLastName()));

        if ($newFirstName === $oldFirstName) {
            if ($nameComparisonLength) {
                return substr($newLastName, 0, $nameComparisonLength) === substr($oldLastName, 0, $nameComparisonLength);
            }

            return $newLastName === $oldLastName;
        }

        return false;
    }

    /**
     * Gets ids of the groups the given user previously was in.
     *
     * @param User $user
     * @return array
     */
    protected function getPreviousGroupIds(User $user)
    {
        $newYear = (int)$this->getParameter('registration.current_year');

        return $this->get('bns.user_manager')->getPreviousGroupIds($user, $newYear - 1);
    }

    /**
     * Gets ids of the groups the given user currently is in.
     *
     * @param User $user
     * @return array
     */
    protected function getCurrentGroupIds(User $user)
    {
        $userManager = $this->get('bns.user_manager');
        $previousUser = $userManager->getUser();
        $rights  = $userManager->setUser($user)->getFullRightsAndGroups();
        $userManager->setUser($previousUser);

        return array_keys($rights);
    }

    /**
     * @param User $user
     * @return array|User[]
     */
    protected function getLinkableParents(User $user)
    {
        $parents = [];
        if (9 == $user->getHighRoleId()) {
            foreach ($user->getChildren() as $child) {
                foreach ($child->getParents() as $parent) {
                    if (!$parent->getAafLinked() && $parent->getAafId() && $parent->getId() !== $user->getId()) {
                        $parents[$parent->getId()] = $parent;
                    }
                }
            }
        }

        return $parents;
    }

}
