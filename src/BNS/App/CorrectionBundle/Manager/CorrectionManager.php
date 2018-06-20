<?php

namespace BNS\App\CorrectionBundle\Manager;

use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\User\BNSUserManager;
use Psr\Log\LoggerInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class CorrectionManager
{
    /**
     * @var BNSUserManager
     */
    protected $userManager;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(BNSUserManager $userManager, LoggerInterface $logger)
    {
        $this->userManager = $userManager;
        $this->logger = $logger;
    }

    public function hasCorrectionRight($className, User $user, $groupId = null)
    {
        if (!$this->isClassValid($className)) {
            $this->logger->error(sprintf('CorrectionManager invalid className "%s" check if it implements "BNS\App\CorrectionBundle\Model\CorrectionInterface"', $className), [
                'className' => $className,
                'groupId' => $groupId,
                'userId' => $user->getId()
            ]);

            return false;
        }
        $rightName = $className::getCorrectionRightName();
        // Check if
        if ($groupId) {
            return $this->userManager->setUser($user)->hasRight($rightName, $groupId);
        }

        return $this->userManager->setUser($user)->hasRightSomeWhere($rightName);
    }

    public function hasCorrectionEditRight($className, User $user, $groupId = null)
    {
        if (!$this->isClassValid($className)) {
            $this->logger->error(sprintf('CorrectionManager invalid className "%s" check if it implements "BNS\App\CorrectionBundle\Model\CorrectionInterface"', $className), [
                'className' => $className,
                'groupId' => $groupId,
                'userId' => $user->getId()
            ]);

            return false;
        }
        $rightName = $className::getCorrectionRightName() . '_EDIT';
        // Check if
        if ($groupId) {
            return $this->userManager->setUser($user)->hasRight($rightName, $groupId);
        }

        return $this->userManager->setUser($user)->hasRightSomeWhere($rightName);
    }

    protected function isClassValid($className)
    {
        return $className && is_subclass_of($className, 'BNS\App\CorrectionBundle\Model\CorrectionInterface');
    }
}
