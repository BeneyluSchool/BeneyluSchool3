<?php
namespace BNS\App\CampaignBundle\ApiController;

use BNS\App\CampaignBundle\Form\Type\UserFastEditType;
use BNS\App\CampaignBundle\Form\Type\UsersType;
use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\UserQuery;
use Doctrine\Common\Collections\Criteria;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Julie Boisnard <julie.boisnard@pixel-cookers.com>
 */
class UserFastEditApiController extends BaseApiController
{
    /**
     * <pre>
     * {"first_name" : "Julie",
     * "last_name" : "Boisnard",
     * "email" : "email@test.com",
     * "phone" : "3630"}
     * </pre>
     *
     * @ApiDoc(
     *  section="Users Fast Edit",
     *  resource = true,
     *  description="Edit some user information for campaign, limited to (teacher, parent, assistant)",
     * )
     *
     * @Rest\Patch("/users/{userId}/campaign-type/{type}/fast-edit")
     * @Rest\View(serializerGroups={"Default","detail"})
     *
     *
     * @param Integer $userId
     * @param Request $request
     *
     * @return array
     */
    public function editUserAction($userId, $type, Request $request)
    {
        $user = UserQuery::create()
            ->filterById($userId)
            ->filterByArchived(false)
            ->findOne();

        if (!$user) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        $groupIds = $this->get('bns.right_manager')->getGroupIdsWherePermission('CAMPAIGN_VIEW_INDIVIDUAL_USER');

        if (0 === count($groupIds)) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        // Check if the current user can edit the user
        $groupManager = $this->get('bns.group_manager');
        $allow = false;
        foreach (GroupQuery::create()->filterById($groupIds)->find() as $group) {
            if (
                in_array($user->getId(), $groupManager->setGroup($group)->getUsersByRoleUniqueNameIds('PARENT')) ||
                in_array($user->getId(), $groupManager->setGroup($group)->getUsersByRoleUniqueNameIds('TEACHER')) ||
                in_array($user->getId(), $groupManager->setGroup($group)->getUsersByRoleUniqueNameIds('ASSISTANT'))
            ) {
                $allow = true;
                break;
            }
        }

        if (!$allow) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        $userManager = $this->get('bns.user_manager');

        $type = strtolower($type);
        if ($type != "sms" && $type != "email") {
            $type = 'Default';
        }

        $groupCountry = $this->get('bns.right_manager')->getCurrentGroup()->getCountry();
        if (!$groupCountry) {
            $groupCountry = 'FR';
        }

        return $this->restForm(new UserFastEditType($userManager, $groupCountry), $user, array(
            'csrf_protection' => false,
            'validation_groups' => [$type],
        ), null, function($user) use ($userManager) {
            $userManager->updateUser($user);
        });
    }

    /**
     * <pre>
     * { "users" : {
     *     "1": {"first_name" : "Julie","last_name" : "Boisnard", "email" : "email@test.com", "phone" : "3630"}},
     *     "2": {"last_name" : "Toto", "email" : "toto@test.com"}
     *   }
     * }
     * </pre>
     *
     * @ApiDoc(
     *  section="Users Fast Edit",
     *  resource = true,
     *  description="Edit some information of many users for campaign, limited to (teacher, parent, assistant)",
     * )
     *
     * @Rest\Patch("/users/campaign-type/{type}/fast-edit")
     * @Rest\View(serializerGroups={"Default","detail"})
     *
     * @param Request $request
     *
     * @return array
     */
    public function editUsersAction(Request $request, $type)
    {
        $users = $request->get('users');

        $usersObject = UserQuery::create()
            ->filterById(array_keys($users))
            ->filterByArchived(false)
            ->find()->getArrayCopy('Id');

        if (count($users) != count($usersObject)) {
            return View::create('Julie', Codes::HTTP_BAD_REQUEST);
        }

        $groupIds = $this->get('bns.right_manager')->getGroupIdsWherePermission('CAMPAIGN_VIEW_INDIVIDUAL_USER');

        if (0 === count($groupIds)) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        // Check if the current user can edit those users
        $groupManager = $this->get('bns.group_manager');

        $userIds = array_keys($usersObject);
        $allowedUsers = [];
        $allowed = false;
        foreach (GroupQuery::create()->filterById($groupIds)->find() as $group) {
            $parentIds = $groupManager->setGroup($group)->getUsersByRoleUniqueNameIds('PARENT');
            $teacherIds = $groupManager->setGroup($group)->getUsersByRoleUniqueNameIds('TEACHER');
            $assistantIds = $groupManager->setGroup($group)->getUsersByRoleUniqueNameIds('ASSISTANT');

            $allowedUsers = array_merge($allowedUsers, array_intersect($userIds, $parentIds), array_intersect($userIds, $teacherIds), array_intersect($userIds, $assistantIds));
            if (0 === count(array_diff($userIds, $allowedUsers))) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        // TODO
        $groupCountry = $this->get('bns.right_manager')->getCurrentGroup()->getCountry();
        if (!$groupCountry) {
            $groupCountry = 'FR';
        }

        $type = strtolower($type);
        if ($type != "sms" && $type != "email") {
            $type = 'Default';
        }

        $userManager = $this->get('bns.user_manager');

        return $this->restForm(new UsersType($userManager, $groupCountry), ["users" => $usersObject], array(
                'csrf_protection' => false,
                'validation_groups' => [$type]
            ), null, function ($usersFinal , $form) use ($userManager) {
                /** @var User $user */
                foreach ($usersFinal['users'] as $user) {
                    $userManager->updateUser($user);
                }
            }
        );
    }
}
