<?php

namespace BNS\App\CorrectionBundle\Model;

/**
 * You should use this trait to allow your object to use correction
 *
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
trait CorrectionTrait
{
    /** @var  Correction */
    protected $correction;

    /** @var  Correction */
    protected $correctionScheduleForDeletion;

    public function getCorrection(\Criteria $criteria = null, \PropelPDO $con = null)
    {
        if (null === $this->correction || $criteria !== null) {
            $correction = CorrectionQuery::create(null, $criteria)
                ->filterByObject($this)
                ->findOne($con)
            ;
            if ($criteria) {
                return $correction;
            }

            $this->correction = $correction;
        }

        return $this->correction;
    }

    public function hasCorrection()
    {
        if ($correction = $this->getCorrection()) {
            return $correction->hasData();
        }

        return false;
    }

    public function setCorrection(Correction $correction = null)
    {
        $this->modifiedColumns[] = 'correction';
        $currentCorrection = $this->getCorrection();
        if (!$currentCorrection ||  !$correction || $currentCorrection->getId() !== $correction->getId()) {
            if ($currentCorrection) {
                $this->correctionScheduleForDeletion = $currentCorrection;
            }
            $this->modifiedColumns[] = 'correction';
            $this->correction = $correction;
            $correction->setObject($this);
        }

        return $this;
    }

    public function clearCorrectionRelation()
    {
        $this->correction = null;
        $this->correctionScheduleForDeletion = null;
    }

    public function postSave(\PropelPDO $con = null)
    {
        if ($this->correctionScheduleForDeletion) {
            $this->correctionScheduleForDeletion->delete($con);
        }
        if ($this->correction) {
            $this->correction->save($con);
        }
    }

    public function postDelete(\PropelPDO $con = null)
    {
        if ($correction = $this->getCorrection()) {
            $correction->delete($con);
        }
    }
}
