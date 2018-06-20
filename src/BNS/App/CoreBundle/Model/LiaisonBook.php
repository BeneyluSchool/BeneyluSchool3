<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Model\om\BaseLiaisonBook;
use BNS\App\CoreBundle\Utils\StringUtil;
use BNS\App\NotificationBundle\Notification\LiaisonbookBundle\LiaisonBookModifiedMessageNotification;
use BNS\App\NotificationBundle\Notification\LiaisonbookBundle\LiaisonBookNewMessageNotification;
use BNS\App\NotificationBundle\Notification\LiaisonbookBundle\LiaisonBookNewMessageToSignNotification;

/**
 * @author Pierre-Luc
 */
class LiaisonBook extends BaseLiaisonBook
{
    /**
     * @return string
     */
    public function getShortContent()
    {
        return StringUtil::substrws($this->getContent());
    }

    public function buildPkeyCriteria()
    {
        // There are FK on group_id and author_id columns, that are added to the PK criteria for some reason.
        // But if one of these columns is modified, the UPDATE query issued wrongly uses the new value in its WHERE
        // clause, hence failing to find the row to update. Ie:
        //      LiaisonBookQuery::create()->findPk(1)->setGroupId(123)->save()
        // results in the wrong SQL:
        //      UPDATE liaison_book SET group_id=123 WHERE id=1 AND group_id=123

        // Quick fix: override the base method to not add group_id and author_id clauses
        $criteria = new \Criteria(LiaisonBookPeer::DATABASE_NAME);
        $criteria->add(LiaisonBookPeer::ID, $this->id);

        return $criteria;
    }

    /**
     * @param \PropelPDO $con
     *
     * @return int affected rows
     */
    public function save(\PropelPDO $con = null, $skipNotification = false)
    {
        $container = BNSAccess::getContainer();
        if ($this->isNew()) {
            if ($skipNotification || null === $container) {
                // If $container == null, this method is called by CLI
                return parent::save($con);
            }

            $affectedRows = parent::save($con);

            $liaisonBookGoup = $this->getGroup();

            $canSignUsers = $container->get('bns.group_manager')->setGroup($liaisonBookGoup)->getUsersByRankUniqueName('LIAISONBOOK_USE', true);
            $canReadUsers = $container->get('bns.group_manager')->setGroup($liaisonBookGoup)->getUsersByPermissionUniqueName('LIAISONBOOK_ACCESS', true);

            if ($canSignUsers instanceof \PropelObjectCollection) {
                $canSignUsers = $canSignUsers->getArrayCopy('Id');
            }
            if ($canReadUsers instanceof \PropelObjectCollection) {
                $canReadUsers = $canReadUsers->getArrayCopy('Id');
            }
            if ($this->getIndividualized()) {
                $canManageUsers = $container->get('bns.group_manager')->setGroup($liaisonBookGoup)->getUserWithPermission('LIAISONBOOK_ACCESS_BACK')->getArrayCopy('Id');
                $adressedUsers = $this->getaddresseds()->getArrayCopy('Id');
                // users that can read and have been adressed + users that can manage
                $canReadUsers = array_intersect_key($canReadUsers, $adressedUsers) + $canManageUsers;
                $adressedParents = UserQuery::create()
                    ->usePupilParentLinkRelatedByUserParentIdQuery()
                        ->filterByUserPupilId(array_keys($canReadUsers))
                    ->endUse()
                    ->find()
                    ->getArrayCopy('Id')
                ;
                // users that can sign and are parents of adressed users
                $canSignUsers = array_intersect_key($canSignUsers, $adressedParents);
            }

            $finalSignUsers = array();
            foreach ($canSignUsers as $user) {
                if ($user->getId() != $this->getAuthorId()) {
                    $finalSignUsers[] = $user;
                }
            }

            $finalReadUsers = array();
            foreach ($canReadUsers as $user) {
                if ($user->getId() != $this->getAuthorId() && !in_array($user, $finalSignUsers)) {
                    $finalReadUsers[] = $user;
                }
            }
            if (!$this->getPublicationDate()) {
                // Nouveau message PAR enseignant POUR personne ayant le droit de signer
                $container->get('notification_manager')->send($finalSignUsers, new LiaisonBookNewMessageToSignNotification($container, $this->getId(), $liaisonBookGoup->getId()));
                // Nouveau message PAR enseignant POUR personne ayant le droit de lecture MAIS pas celui de signer
                $container->get('notification_manager')->send($finalReadUsers, new LiaisonBookNewMessageNotification($container, $this->getId(), $liaisonBookGoup->getId()));
            }

            return $affectedRows;
        }

        $affectedRows = parent::save($con);
        $userIds = $container->get('bns.group_manager')->getUserIdsWithPermission('LIAISONBOOK_ACCESS', $this->getGroup());
        $finalUsers = UserQuery::create()->filterById($userIds)->find();
        $container->get('notification_manager')->send($finalUsers, new LiaisonBookModifiedMessageNotification($container, $this->getId(), $this->getGroupId()));
        return $affectedRows;
    }

    public function hasSigned($userId)
    {
        if (null !== $this->collLiaisonBookSignatures) {
            foreach ($this->getLiaisonBookSignatures() as $signature) {
                if ($signature->getUserId() === $userId) {
                    return true;
                }
            }

            return false;
        }

        // count if the user sign this article
        return LiaisonBookSignatureQuery::create()
            ->filterByLiaisonBook($this)
            ->filterByUserId($userId)
            ->count() > 0
        ;
    }

    public function getNumberOfAdressed()
    {
        if (!$this->getIndividualized()) {
            return null;
        }
        $children = $this->getaddresseds()->toArray('Id');
        return PupilParentLinkQuery::create()->filterByUserPupilId(array_keys($children))->count();

    }
}
