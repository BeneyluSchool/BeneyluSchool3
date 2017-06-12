<?php

namespace BNS\App\ForumBundle\Model;

use BNS\App\CoreBundle\Model\User;

use BNS\App\CoreBundle\Model\UserQuery;

use Symfony\Component\Validator\ExecutionContext;

use BNS\App\ForumBundle\Model\om\BaseForum;

class Forum extends BaseForum
{
    protected $validatedUsers;

    protected $pendingValidationUsers;

    public function isCloseUntilValid($context)
    {
        if (null !== $this->getClosedUntil() && null !== $this->getClosedAt()) {
            if ($this->getClosedUntil()->getTimestamp() <= $this->getClosedAt()->getTimestamp()) {
                $context->addViolationAt('closed_until', 'La date de fin de fermeture doit être postérieur à la date de début de fermeture');
            }
        } else if (null !== $this->getClosedUntil()) {
            $context->addViolationAt('closed_at', 'Pour fermer le forum, une date de début de fermeture est requise.');
        }
    }

    public function isClosed()
    {
        if (null !== $this->getClosedAt()) {
            if ($this->getClosedAt()->getTimestamp() < time() && (null === $this->getClosedUntil() || $this->getClosedUntil()->getTimestamp() > time())) {
                return true;
            }
        }

        return false;
    }

    public function isReadOnly()
    {
        return $this->getIsArchived() || $this->isClosed();
    }

    public function getValidatedUsers()
    {
        if (null === $this->validatedUsers) {
            $this->validatedUsers = UserQuery::create()
                ->useForumUserQuery()
                    ->filterByForum($this)
                    ->filterByStatus(ForumUserPeer::STATUS_VALIDATED)
                ->endUse()
                ->find();
        }

        return $this->validatedUsers;
    }

    public function getPendingValidationUsers()
    {
        if (null === $this->pendingValidationUsers) {
            $this->pendingValidationUsers = UserQuery::create()
            ->useForumUserQuery()
            ->filterByForum($this)
            ->filterByStatus(ForumUserPeer::STATUS_PENDING_VALIDATION)
            ->endUse()
            ->find();
        }

        return $this->pendingValidationUsers;
    }

    public function canSubscribe()
    {
        return $this->getIsPublic();
    }

    public function getForumUser($user)
    {
        return ForumUserQuery::create()->filterByForum($this)->filterByStatus(ForumUserPeer::STATUS_VALIDATED)->filterByUser($user)->findOne();
    }

    public function isSubscribe(User $user)
    {
        return ForumUserQuery::create()->filterByForum($this)->filterByStatus(ForumUserPeer::STATUS_VALIDATED)->filterByUser($user)->count() > 0;
    }

    public function isPendingValidation(User $user)
    {
        return ForumUserQuery::create()->filterByForum($this)->filterByStatus(ForumUserPeer::STATUS_PENDING_VALIDATION)->filterByUser($user)->count() > 0;
    }


    public function anonymize($users)
    {
        $messageIds = ForumMessageQuery::create()->useForumSubjectQuery()->filterByForum($this)->endUse()->filterByUser($users)->select('Id')->find()->getArrayCopy();

        ForumMessageQuery::create()->filterById($messageIds)->update(array('AuthorId' => null));
        ForumSubjectQuery::create()->filterByForum($this)->filterByUser($users)->update(array('AuthorId' => null));
    }

    public function anonymizeAll()
    {
        $messageIds = ForumMessageQuery::create()->useForumSubjectQuery()->filterByForum($this)->endUse()->select('Id')->find()->getArrayCopy();

        ForumMessageQuery::create()->filterById($messageIds)->update(array('AuthorId' => null));
        ForumSubjectQuery::create()->filterByForum($this)->update(array('AuthorId' => null));
    }
}
