<?php

namespace BNS\App\WorkshopBundle\Manager;
use BNS\App\WorkshopBundle\Model\WorkshopDocument;
use BNS\App\CoreBundle\Model\User;
use BNS\App\WorkshopBundle\Model\WorkshopDocumentContributionQuery;

/**
 * Class ContributionManager
 *
 * @package BNS\App\WorkshopBundle\Manager
 */
class ContributionManager
{

    /**
     * @param int|User $user
     * @param WorkshopDocument $document
     * @param \PropelPDO $con
     * @return int
     * @throws \Exception
     * @throws \PropelException
     */
    public function addContribution($user, WorkshopDocument $document, \PropelPDO $con = null)
    {
        $contribution = WorkshopDocumentContributionQuery::create()
            ->_if($user instanceof User)
                ->filterByUser($user)
            ->_else()
                ->filterByUserId($user)
            ->_endif()
            ->filterByWorkshopDocument($document)
            ->findOneOrCreate($con)
        ;

        $contribution->setUpdatedAt(time());

        return $contribution->save($con);
    }

}
