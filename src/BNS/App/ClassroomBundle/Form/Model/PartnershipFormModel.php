<?php

namespace BNS\App\ClassroomBundle\Form\Model;

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Partnership\BNSPartnershipManager;

/**
 * @author El Mehdi Ouarour <el-mehdi.ouarour@atos.net>
 */
class PartnershipFormModel
{
    
    public $partnership;

    public $name;
    
    public $description;
    
    public $home_message;
    
    public $currentGroupId;
    
    public $isEditionMode;

    /**
     * @var BNSPartnershipManager
     */
    public $partnershipManager;

    public $classrooms;

    /**
     * @param type $groupId
     * @param boolean $isEditionMode
     * @param Group $partnership
     */
    public function __construct($groupId, $partnershipManager, $isEditionMode,$partnership = null)
    {
        $this->partnership = $partnership;
        $this->currentGroupId = $groupId;
        $this->isEditionMode = $isEditionMode;
        $this->partnershipManager = $partnershipManager;
        
        //Si on est en mode edition on alimente le formulaire
        if($isEditionMode && $partnership != null)
        {
            $this->name = $partnership->getLabel();
            $this->description = $partnership->getAttribute('DESCRIPTION');
            $this->home_message = $partnership->getAttribute('HOME_MESSAGE');
        }
    }

    public function save()
    {
        $params = array();
        $params['label'] = $this->name;
        $params['validated'] = true;
        $params['group_creator_id'] = $this->currentGroupId;
        $params['attributes']['DESCRIPTION'] = $this->description;
        $params['attributes']['HOME_MESSAGE'] = $this->home_message;

        if(! $this->isEditionMode)
        {
            $partnership = $this->partnershipManager->createPartnership($params);
            // if classrooms have been selected, add them to partnership
            if (count($this->classrooms)) {
                $partnershipInfo = $this->partnershipManager->getGroupFromCentral($partnership->getId());
                /** @var Group $classroom */
                foreach ($this->classrooms as $classroom) {
                    $this->partnershipManager->joinPartnership($partnershipInfo['uid'], $classroom->getId());
                }
            }
            return $partnership;
        }
        else
        {
            //update des attributes du partenariat
            $this->partnership->setAttribute('DESCRIPTION', $this->description);
            $this->partnership->setAttribute('HOME_MESSAGE', $this->home_message);
                
            //update côté centrale
            $this->partnershipManager->setPartnership($this->partnership);
            $this->partnershipManager->updatePartnership($params);
        }
    }
    
}
