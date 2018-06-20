<?php

namespace BNS\App\MediaLibraryBundle\Model;

use BNS\App\MediaLibraryBundle\Model\om\BaseMediaQuery;

class MediaQuery extends BaseMediaQuery
{
    /**
     * @param MediaFolderUser|MediaFolderGroup $mediaFolder
     * @return $this
     */
    public function filterByMediaFolder($mediaFolder)
    {
        $this
            ->filterByMediaFolderType($mediaFolder->getType())
            ->filterByMediaFolderId($mediaFolder->getId());

        return $this;
    }
}
