<?php

namespace BNS\App\GroupBundle\Controller;

use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupType;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use BNS\App\CoreBundle\Model\UserQuery;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use BNS\App\CoreBundle\Annotation\Rights;

class CommonController extends Controller
{

    protected $available_group_ids;

	protected $not_authorised_ranks = array(
		'HELLOWORLD_MANAGE',
		'HELLOWORLD_READ',
		'HELLOWORLD_USE',
		'ADMIN_ADMINISTRATION',
        'ADMIN_ADMINISTRATION_STRONG',
		'MESSAGING_SEND_EXTERNAL',
		'MESSAGING_SEND_INTERNAL',
		'MESSAGING_USE_ADULT',
		'MESSAGING_USE_CHILD',
		'MESSAGING_USE_NO_MODERATION',
		'MESSAGING_USE_PARENT',
		'PROFILE_NO_MODERATE_COMMENT',
		'PROFILE_NO_MODERATE_STATUS',
		'MEDIA_LIBRARY_MANAGE',
		'TEAM_MANAGE',
		'TEAM_READ',
		'TEAM_USE',
		'DIRECTORY_MANAGE',
		'MESSAGING_ACCESS_EXTERNAL_SEND',
		'MESSAGING_ACCESS_BYPASS_WHITELISTS',
        'USER_ASSIGNMENT'
	);

	protected $not_authorised_attributes = array(
		'AVATAR_ID',
		'LANGUAGE',
		'MESSAGING_TYPE',
		'MINISITE_ALLOW_PUBLIC',
		'POLICY',
		'RESOURCE_QUOTA_GROUP',
		'RESOURCE_QUOTA_USER',
        'RESOURCE_QUOTA_CHILD',
		'RESOURCE_USED_SIZE',
		'WHITE_LIST_USE_PARENT',
		'WHITE_LIST',
		'WHITE_LIST_USE_PARENT',
		'WHITE_LIST_UNIQUE_KEY',
        'YERBOOK_AVATAR'
	);

    protected $extended_not_authorised_attributes = array(
        'STRUCTURE_ID',
        'UAI',
        'CURRENT_YEAR',
    );

    protected $media_size_attributes = array(
        'RESOURCE_QUOTA_USER',
        'RESOURCE_QUOTA_GROUP',
        'RESOURCE_QUOTA_CHILD',
    );

    /**
     * Récupère l'utilisateur et effectue les vérifications (PS : nommé Asked car entre en conflit avec la méthode SF)
     * @param string $login
     * @param string right : Droit nécessaire pour faire l'action
     * @return BNS\App\CoreBundle\Model\User $user
     * @throws NotFoundHttpException
     */
    protected function getAskedUser($login, $right = 'VIEW', $forbid = true)
    {
        $user = UserQuery::create('u')
			->filterByLogin($login)
            ->findOne();

        if(!$user)
        {
            throw new NotFoundHttpException("The user with the login " . $login . " has not been found.");
        }
        $canManage = $this->canManageUser($user, $forbid, $right);
        return $canManage ? $user : false;
    }

    /*
     * L'utilisateur en cours peut il gérer l'utilisateur ciblé ?
     * TODO: Rappatrier dans le right manager
     */
    protected function canManageUser(User $user,$forbid = true, $right = 'VIEW')
	{
		$result = $this->get('bns.user_manager')
            ->canManageUserInGroup(
                $this->get('bns.right_manager')->getUserSession(),
                $user,
                $this->get('bns.right_manager')->getCurrentGroup(),
                $right
            );
        if($result)
        {
            return true;
        } elseif($forbid && !$result) {
			$this->get('bns.right_manager')->forbidIf(true);
        }
		return false;
	}

	protected function canManageGroup(Group $group, $right = 'VIEW')
	{
		$result = $this->get('bns.user_manager')
            ->canManageGroupInGroup(
                $group,
                $this->get('bns.right_manager')->getCurrentGroup(),
                $right
            );
        if($result)
        {
            return true;
        } else {
            $this->get('bns.right_manager')->forbidIf(true);
        }
	}

    protected function canManageGroupType(GroupType $groupType, $right = 'VIEW')
    {
        $rm = $this->get('bns.right_manager');
        $vgts = $rm->getManageableGroupTypes(null,$right);
        foreach($vgts as $vgt)
        {
            if($vgt->getId() == $groupType->getId())
            {
                return true;
            }
        }
        $this->get('bns.right_manager')->forbidIf(true);
    }

	protected function getNotAuthorisedRanks()
	{

		$notAuthorised = $this->not_authorised_ranks;
		$authorisedGroupTypes = $this->get('bns.right_manager')->getManageableGroupTypes();
		$authorisedGroupTypesTypes = array();
		foreach($authorisedGroupTypes as $authorisedGroupType){
			$authorisedGroupTypesTypes[] = $authorisedGroupType->getType();
		}

		$groupTypes = GroupTypeQuery::create()->find();
		foreach( $groupTypes as $groupType ){
			if(!in_array($groupType->getType(), $authorisedGroupTypesTypes)){
				$notAuthorised[] = $groupType->getType() . '_TYPE_MANAGE';
			}
			$notAuthorised[] = $groupType->getType() . '_TYPE_CREATION';
		}

		return $notAuthorised;
	}

    protected function getNotAuthorisedAttributes()
    {


        $attributes = ($this->container->hasParameter('extend_not_authorised_attributes') && $this->container->getParameter('extend_not_authorised_attributes') != true) ?
            array_merge($this->not_authorised_attributes,$this->extended_not_authorised_attributes) :
            $this->not_authorised_attributes;

        if($this->get('bns.right_manager')->hasRight('GROUP_EDIT_MEDIA_LIBRARY_SIZE'))
        {
            foreach($attributes as $key => $value)
            {
                if(in_array($value,$this->media_size_attributes))
                {
                    unset($attributes[$key]);
                }
            }
        }
        return $attributes;
    }
}

