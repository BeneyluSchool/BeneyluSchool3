<?php

namespace BNS\App\CompetitionBundle\Model;

use BNS\App\CompetitionBundle\Model\om\BaseCompetition;
use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\MediaLibraryBundle\Twig\MediaExtension;
use PropelPDO;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class Competition extends BaseCompetition
{

    /**
     * Exposed on form, to handle the questionnaire Media collection and their parameters
     *
     * @var array
     */
    public $questionnaires;

    /**
     * @var float
     */
    public $percent;

    /**
     * @var int
     */
    public $score;

    public function getCompetitionType()
    {
        switch ($this->getClassKey()) {
            case CompetitionPeer::CLASSKEY_SIMPLECOMPETITION :
                return CompetitionPeer::TYPE_SIMPLE_COMPETITION;
            case CompetitionPeer::CLASSKEY_READINGCHALLENGE :
                return CompetitionPeer::TYPE_READING_CHALLENGE;
            case CompetitionPeer::CLASSKEY_PEDAGOGICCOURSE:
                return CompetitionPeer::TYPE_PEDAGOGIC_COURSE;
        }
    }

    public function setCompetitionType($CompetitionType)
    {
        if (CompetitionPeer::TYPE_SIMPLE_COMPETITION === $CompetitionType) {
            $this->setClassKey(CompetitionPeer::CLASSKEY_SIMPLECOMPETITION);
        } else {
            $this->setClassKey(CompetitionPeer::CLASSKEY_READINGCHALLENGE);
        }

        return $this;
    }

    public function getUsers()
    {
        return UserQuery::create()
            ->useCompetitionUserQuery()
                ->filterByCompetition($this)
            ->endUse()
            ->find();
    }

    /**
     * @inheritDoc
     */
    public function preSave(PropelPDO $con = null)
    {
        if ($this->booksScheduledForDeletion !== null) {
            if (!$this->booksScheduledForDeletion->isEmpty()) {
                foreach ($this->booksScheduledForDeletion as $book) {
                    // actually delete books that are no longer associated with this competition
                    $book->delete($con);
                }
                $this->booksScheduledForDeletion = null;
            }
        }

        return parent::preSave($con);
    }

    public function getMediaUrl()
    {
        if ($media = $this->getMedia()) {
            return BNSAccess::getContainer()->get('bns.media.download_manager')->getImageDownloadUrl($media, 'competition_landscape');
        }

        return null;
    }

    public function getPercent() {
        if (isset($this->percent)) {
            return $this->percent;
        }
    }

    public function getScore() {
        if (isset($this->score)) {
            return $this->score;
        }
    }

    public function hasParticipant(ExecutionContextInterface $context)
    {
        if ( !count($this->getParticipatingGroups()) && !count($this->getCompetitionUsers())){
            $context->buildViolation('ERROR_PARTICIPATION')->addViolation();
        }

    }

    public function getParticipatingGroupIds()
    {
        return GroupQuery::create()->useCompetitionGroupQuery()
        ->filterByCompetitionId($this->getId())
        ->endUse()
        ->select('id')
        ->find()
        ->toArray();
    }

    public function getDescription()
    {
        $description =  parent::getDescription();
        $twigExtent = new MediaExtension(BNSAccess::getContainer());
        $content = $twigExtent->parsePublicResources($description,false,'medium');
        return $content;

    }


}
