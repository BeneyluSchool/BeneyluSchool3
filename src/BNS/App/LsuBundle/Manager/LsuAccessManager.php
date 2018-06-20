<?php

namespace BNS\App\LsuBundle\Manager;

use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Right\BNSRightManager;
use BNS\App\CoreBundle\User\BNSUserManager;
use BNS\App\LsuBundle\Model\Lsu;
use BNS\App\LsuBundle\Model\LsuTemplate;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class LsuAccessManager
 *
 * @package BNS\App\LsuBundle\Manager
 */
class LsuAccessManager
{

    /**
     * @var BNSRightManager
     */
    protected $rightManager;

    /**
     * @var BNSUserManager
     */
    protected $userManager;

    public function __construct(BNSRightManager $rightManager, BNSUserManager $userManager)
    {
        $this->rightManager = $rightManager;
        $this->userManager = $userManager;
    }

    public function validateLsu(Lsu $lsu, User $user)
    {
        try {
            $this->validateTemplate($lsu->getLsuTemplate());
        } catch (\Exception $e) {
            // allow access to parents of LSU user
            $childrenIds = [];
            $children = $this->userManager->getUserChildren($user);
            foreach ($children as $child) {
                $childrenIds[] = $child->getId();
            }
            if (!in_array($lsu->getUserId(), $childrenIds)) {
                throw $e;
            }
        }
    }

    public function validateTemplate(LsuTemplate $template)
    {
        $group = GroupQuery::create()
            ->useLsuConfigQuery()
                ->useLsuTemplateQuery()
                    ->filterById($template->getId())
                ->endUse()
            ->endUse()
            ->findOne()
        ;
        if (!$group) {
            throw new NotFoundHttpException('Template not found');
        }

        $this->validateGroup($group);

        return $group;
    }

    public function validateGroup(Group $group, $permission = 'LSU_ACCESS_BACK')
    {
        // Check security
        if (!$this->rightManager->hasRight($permission, $group->getId())) {
            throw new AccessDeniedHttpException('Access denied');
        }

        if ('CLASSROOM' !== $group->getType()) {
            throw new NotFoundHttpException('Group not found');
        }
    }

}
