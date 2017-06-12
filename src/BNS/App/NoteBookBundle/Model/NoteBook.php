<?php

namespace BNS\App\NoteBookBundle\Model;

use BNS\App\CoreBundle\Utils\StringUtil;
use BNS\App\NoteBookBundle\Model\om\BaseNoteBook;

class NoteBook extends BaseNoteBook
{
    /**
     * @return string
     */
    public function getShortContent()
    {
        return StringUtil::substrws($this->getContent());
    }

    public function getExportContent()
    {
        return strip_tags(html_entity_decode($this->getContent()));
    }
}
