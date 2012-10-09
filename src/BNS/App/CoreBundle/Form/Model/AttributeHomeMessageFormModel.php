<?php

namespace BNS\App\CoreBundle\Form\Model;

use Symfony\Component\HttpKernel\Exception\HttpException;

use Criteria;

use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\GroupTypePeer;
use BNS\App\CoreBundle\Form\Model\IFormModel;

class AttributeHomeMessageFormModel implements IFormModel
{
    /**
        * @var Group correspond à l'objet Group dont on souhaite modifier l'attribut de message d'accueil
        */
    public $group;

    /**
        * @var String
        */
    public $homeMessage;

    public function __construct(Group $group)
    {
        if (null == $group)
        {
            $groupTypeId = $group->getGroupTypeId();
            $teamAndClassroomGroupType = GroupTypeQuery::create()
                ->add(GroupTypePeer::TYPE, array('CLASSROOM', 'TEAM'), Criteria::IN)
            ->find();
            $ids = array();
            foreach ($teamAndClassroomGroupType as $groupType)
            {
                $ids[] = $groupType->getId();
            }
            
            if (!in_array($groupTypeId, $ids))
            {
                throw new HttpException(500, 'You must provide a group with group type equals to CLASSROOM or WORKGROUP!');
            }
        }
        else
        {
            $this->group = $group;
            $this->homeMessage = $this->group->getAttribute('HOME_MESSAGE');
        }
    }

    public function save()
    {		
        $this->group->setAttribute('HOME_MESSAGE', $this->homeMessage);
        
        $this->group->save();
    }
}