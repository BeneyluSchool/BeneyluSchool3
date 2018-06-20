<?php

namespace BNS\App\MediaLibraryBundle\Model;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
trait AttachmentTrait
{
    /**
     * @var \PropelObjectCollection|Media[]|null
     */
    protected $attachments;

    /**
     * @var \PropelObjectCollection|Media[]|null
     */
    protected $attachmentsPartial;

    /**
     * @var \PropelObjectCollection|Media[]|null
     */
    protected $attachmentsScheduledForDeletion;

    /**
     * @return mixed
     */
    public function getAttachments(\Criteria $criteria = null, \PropelPDO $con = null)
    {
        if (null === $this->attachments || null !== $criteria) {
            if ($this->isNew() && null === $this->attachments) {
                // return empty collection
                $this->attachments = new \PropelObjectCollection();
                $this->attachments->setModel('Media');
            } else {
                $attachments = MediaQuery::create(null, $criteria)
                    ->useMediaJoinObjectQuery()
                        ->filterByObjectId($this->getPrimaryKey())
                        ->filterByObjectClass($this->getMediaClassName())
                        ->filterByIsEmbedded(false)
                    ->endUse()
                    ->find($con)
                ;
                if ($criteria) {
                    return $attachments;
                }

                $this->attachments = $attachments;
            }
        }

        return $this->attachments;
    }

    public function setAttachments(\PropelCollection $attachments, \PropelPDO $con = null)
    {
        $this->modifiedColumns[] = 'attachments';
        $this->clearAttachments();
        $currentAttachments = $this->getAttachments(new \Criteria(), $con);

        $this->attachmentsScheduledForDeletion = $currentAttachments->diff($attachments);
        $this->attachments = $attachments;

        return $this;
    }

    public function clearAttachments()
    {
        $this->attachments = null;
        $this->attachmentsPartial = null;

        return $this;
    }

    public function postSave(\PropelPDO $con = null)
    {
        parent::postSave($con);

        if ($this->attachmentsScheduledForDeletion !== null) {
            // delete scheduleForDeletion
            if (!$this->attachmentsScheduledForDeletion->isEmpty()) {
                $mediaIds = $this->attachmentsScheduledForDeletion->getPrimaryKeys(false);
                MediaJoinObjectQuery::create()
                    ->filterByMediaId($mediaIds)
                    ->filterByObjectId($this->getPrimaryKey())
                    ->filterByObjectClass($this->getMediaClassName())
                    ->filterByIsEmbedded(false)
                    ->delete($con)
                ;
                $this->attachmentsScheduledForDeletion = null;
            }
        }

        $currentMediaIds = MediaJoinObjectQuery::create()
            ->filterByObjectId($this->getPrimaryKey())
            ->filterByObjectClass($this->getMediaClassName())
            ->filterByIsEmbedded(false)
            ->select(['MediaId'])
            ->find($con)
            ->getArrayCopy()
        ;
        if ($this->attachments) {
            foreach ($this->attachments as $attachment) {
                if (!$attachment) {
                    continue;
                }
                if (!in_array($attachment->getId(), $currentMediaIds)) {
                    $mediaJoinObject = new MediaJoinObject();
                    $mediaJoinObject->setObjectId($this->getPrimaryKey());
                    $mediaJoinObject->setObjectClass($this->getMediaClassName());
                    $mediaJoinObject->setMediaId($attachment->getId());
                    $mediaJoinObject->setIsEmbedded(false);
                    $mediaJoinObject->save($con);
                }
            }
        }
    }

    public function postDelete(\PropelPDO $con = null)
    {
        parent::postDelete($con);

        MediaJoinObjectQuery::create()
            ->filterByObjectId($this->getPrimaryKey())
            ->filterByObjectClass($this->getMediaClassName())
            ->delete($con)
        ;
    }
}
