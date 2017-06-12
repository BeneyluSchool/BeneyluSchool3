<?php

class BNSMediaAttachmentableBehavior extends Behavior
{
    private $builder;

    public function objectAttributes($builder)
    {
        return "private \$resource_attachments = null;
";
    }

    public function objectMethods($builder)
    {
        $this->builder = $builder;

        $builder->declareClassNamespace("MediaJoinObjectQuery", "BNS\App\MediaLibraryBundle\Model");
        $builder->declareClassNamespace("MediaJoinObject", "BNS\App\MediaLibraryBundle\Model");
        $builder->declareClassNamespace("MediaQuery", "BNS\App\MediaLibraryBundle\Model");
        $builder->declareClassNamespace("Media", "BNS\App\MediaLibraryBundle\Model");
        $builder->declareClassNamespace("MediaJoinObjectLinks", "BNS\App\MediaLibraryBundle\Model");

        $script = "";

        $script .= $this->getPrimarykeys();
        $script .= $this->getResourceClassNameFunction();
        $script .= $this->addResourceAttachmentFunction();
        $script .= $this->deleteResourceAttachmentFunction();
        $script .= $this->deleteAllResourceAttachmentsFunction();
        $script .= $this->getResourceAttachmentsFunction();
        $script .= $this->addResourceAttachmentsLinkGroups();
        $script .= $this->addResourceAttachmentsLinkUsers();

        return $script;
    }

    protected function getPrimarykeys()
    {
        return "
/**
 * get a pk or serialized pks if key is composite
 */
protected function getSerializedPrimaryKey()
{
    \$pks = \$this->getPrimaryKey();
    if (is_array(\$pks)) {
        // TODO handle composite
        // implode('_', \$pks);
        return array_shift(\$pks);
    }

    return \$pks;
}
";
    }

    protected function getResourceClassNameFunction()
    {
        return "
/**
 * get ClassName for resources
 */
public function getMediaClassName()
{
    return substr(strrchr(get_class(\$this),'\\\'),1);
}
";
    }

    protected function addResourceAttachmentFunction()
    {

        return "
/**
 * Add a resource attachment
 */
public function addResourceAttachment(\$mediaId)
{
    \$query = MediaJoinObjectQuery::create();
    \$query->filterByMediaId(\$mediaId);
    \$query->filterByObjectId(\$this->getSerializedPrimaryKey());
    \$query->filterByIsEmbedded(false);
    \$query->filterByObjectClass(\$this->getMediaClassName());

    if (!\$query->findOne()) {
        \$attachment = new MediaJoinObject();
        \$attachment->setObjectId(\$this->getSerializedPrimaryKey());
        \$attachment->setObjectClass(\$this->getMediaClassName());
        \$attachment->setMediaId(\$mediaId);
        \$attachment->save();

        return \$attachment;
    }
}
";

    }

    protected function deleteResourceAttachmentFunction()
    {
        return "
/**
 * Delete a resource attachment
 */
public function deleteResourceAttachment(\$media)
{
    \$query = MediaJoinObjectQuery::create();
    \$query->filterByMediaId(\$media->getId());
    \$query->filterByObjectId(\$this->getSerializedPrimaryKey());
    \$query->filterByObjectClass(\$this->getMediaClassName());
    \$query->delete();
}
";

    }

    protected function deleteAllResourceAttachmentsFunction()
    {
        return "
/**
 * Delete all resource attachments
 */
public function deleteAllResourceAttachments()
{
    \$query = MediaJoinObjectQuery::create();
    \$query->filterByObjectId(\$this->getSerializedPrimaryKey());
    \$query->filterByObjectClass(\$this->getMediaClassName());
    \$query->filterByIsEmbedded(false);
    \$query->delete();
}
";
    }

    protected function getResourceAttachmentsFunction()
    {
        return "
/**
 * Get attachments
 */
public function getResourceAttachments()
{
    if (null === \$this->resource_attachments) {
        \$query = MediaQuery::create();
        \$query->useMediaJoinObjectQuery()
            ->filterByObjectId(\$this->getSerializedPrimaryKey())
            ->filterByObjectClass(\$this->getMediaClassName())
            ->filterByIsEmbedded(false)
        ->endUse();
        \$this->resource_attachments = \$query->find();
    }

    return \$this->resource_attachments;
}
";
    }

    public function postDelete()
    {
        return "\$this->deleteAllResourceAttachments();";
    }

    public function addResourceAttachmentsLinkGroups()
    {
        return "
/**
 * Add link from Group
 */
public function addResourceAttachmentsLinkGroups(\$attachmentId, \$groupId)
{
    \$link = new MediaJoinObjectLinks();
    \$link->setGroupId(\$groupId);
    \$link->setMediaJoinObjectId(\$attachmentId);
    \$link->save();
}
";

    }

    public function addResourceAttachmentsLinkUsers()
    {
        return "
/**
 * Add link from User
 */
public function addResourceAttachmentsLinkUsers(\$attachmentId, \$userId)
{
    \$link = new MediaJoinObjectLinks();
    \$link->setUserId(\$userId);
    \$link->setMediaJoinObjectId(\$attachmentId);
    \$link->save();
}
";
    }
}
