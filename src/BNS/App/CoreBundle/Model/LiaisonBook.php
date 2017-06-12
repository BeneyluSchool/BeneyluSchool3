<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Model\om\BaseLiaisonBook;
use BNS\App\CoreBundle\Utils\StringUtil;
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
        if ($this->isNew()) {
            $container = BNSAccess::getContainer();
            if ($skipNotification || null === $container) {
                // If $container == null, this method is called by CLI
                return parent::save($con);
            }

            $affectedRows = parent::save($con);

            $liaisonBookGoup = $this->getGroup();

            $canSignUsers = $container->get('bns.group_manager')->setGroup($liaisonBookGoup)->getUsersByRankUniqueName('LIAISONBOOK_USE', true);
            $canReadUsers = $container->get('bns.group_manager')->setGroup($liaisonBookGoup)->getUsersByPermissionUniqueName('LIAISONBOOK_ACCESS', true);

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

            // Nouveau message PAR enseignant POUR personne ayant le droit de signer
            $container->get('notification_manager')->send($finalSignUsers, new LiaisonBookNewMessageToSignNotification($container, $this->getId(), $liaisonBookGoup->getId()));
            // Nouveau message PAR enseignant POUR personne ayant le droit de lecture MAIS pas celui de signer
            $container->get('notification_manager')->send($finalReadUsers, new LiaisonBookNewMessageNotification($container, $this->getId(), $liaisonBookGoup->getId()));

            return $affectedRows;
        }

        return parent::save($con);
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
}
