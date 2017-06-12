<?php

class BNSResourceAttachmentableBehavior extends Behavior
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

        $builder->declareClassNamespace("ResourceJoinObjectQuery", "BNS\App\ResourceBundle\Model");
        $builder->declareClassNamespace("ResourceJoinObject", "BNS\App\ResourceBundle\Model");
        $builder->declareClassNamespace("ResourceQuery", "BNS\App\ResourceBundle\Model");
        $builder->declareClassNamespace("Resource", "BNS\App\ResourceBundle\Model");
        $builder->declareClassNamespace("ResourceJoinObjectLinks", "BNS\App\ResourceBundle\Model");

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
public function getResourceClassName()
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
public function addResourceAttachment(\$resourceId)
{
    \$query = ResourceJoinObjectQuery::create();
    \$query->filterByResourceId(\$resourceId);
    \$query->filterByObjectId(\$this->getSerializedPrimaryKey());
    \$query->filterByObjectClass(\$this->getResourceClassName());

    if (!\$query->findOne()) {
        \$attachment = new ResourceJoinObject();
        \$attachment->setObjectId(\$this->getSerializedPrimaryKey());
        \$attachment->setObjectClass(\$this->getResourceClassName());
        \$attachment->setResourceId(\$resourceId);
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
public function deleteResourceAttachment(\$resource)
{
    \$query = ResourceJoinObjectQuery::create();
    \$query->filterByResourceId(\$resource->getId());
    \$query->filterByObjectId(\$this->getSerializedPrimaryKey());
    \$query->filterByObjectClass(\$this->getResourceClassName());
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
    \$query = ResourceJoinObjectQuery::create();
    \$query->filterByObjectId(\$this->getSerializedPrimaryKey());
    \$query->filterByObjectClass(\$this->getResourceClassName());
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
        \$query = ResourceQuery::create();
        \$query->useResourceJoinObjectQuery()
            ->filterByObjectId(\$this->getSerializedPrimaryKey())
            ->filterByObjectClass(\$this->getResourceClassName())
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
    \$link = new ResourceJoinObjectLinks();
    \$link->setGroupId(\$groupId);
    \$link->setResourceJoinObjectId(\$attachmentId);
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
    \$link = new ResourceJoinObjectLinks();
    \$link->setUserId(\$userId);
    \$link->setResourceJoinObjectId(\$attachmentId);
    \$link->save();
}
";
    }
}
