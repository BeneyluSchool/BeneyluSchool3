<?php

namespace BNS\App\MessagingBundle\ApiController;

use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\CoreBundle\Model\Group;
use FOS\RestBundle\Controller\Annotations as Rest;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\User\BNSUserManager;

class BaseMessagingApiController extends BaseApiController
{

    /**
     * Gets an array of ids of users that can be managed by the current user.
     * Optionally users can be restricted to the given groups.
     *
     * @param array|Group[] $groups
     * @return array
     */
    protected function getAuthorisedUsersIds($groups = array())
    {
        return $this->get('bns.message_manager')->getAuthorisedUsersIds($groups);
    }

    /**
     * Reole que l'on affiche dans le User Picker
     * @return Array
     */
    protected function getShownRoles()
    {
        $rightManager = $this->get('bns.right_manager');
        $rightsAll = $rightManager->hasRightSomeWhere('MESSAGING_SEND_ALL');
        $shownRoles = array();
        if($rightManager->hasRightSomeWhere('MESSAGING_SEND_PUPILS') || $rightsAll){
            $shownRoles[] = "PUPIL";
        }
        if($rightManager->hasRightSomeWhere('MESSAGING_SEND_PARENTS') || $rightsAll){
            $shownRoles[] = "PARENT";
        }
        if($rightManager->hasRightSomeWhere('MESSAGING_SEND_TEACHERS') || $rightsAll){
            $shownRoles[] = "TEACHER";
        }
        if($rightManager->hasRightSomeWhere('MESSAGING_SEND_DIRECTORS') || $rightsAll){
            $shownRoles[] = "DIRECTOR";
        }

        return $shownRoles;
    }

    protected function handlePartnershipForUserList($groupManager,$role = null)
    {
        $whereMessagingIds = $this->get('bns.right_manager')->getGroupIdsWherePermission('MESSAGING_ACCESS');
        if($groupManager->getGroup()->getGroupType()->getType() != 'PARTNERSHIP')
        {
            return $role ==null ? $groupManager->getUsersIds() : $groupManager->getUsersByRoleUniqueNameIds($role);
        }else{
            $final = array();
            $myGroup = $groupManager->getGroup();
            foreach($groupManager->getPartners() as $partner)
            {
                if($partner->getId() != $myGroup->getId() && !in_array($partner->getId(),$whereMessagingIds))
                {
                    $groupManager->setGroup($partner);
                    $final = array_merge($final,$role == null ? $groupManager->getUsersIds() : $groupManager->getUsersByRoleUniqueNameIds($role));
                }
            }
            $groupManager->setGroup($myGroup);
            return $final;
        }
    }

    protected function handleUserList($toList)
    {
        $rightManager = $this->get('bns.right_manager');
        /** @var BNSGroupManager $groupManager */
        $groupManager = $this->get('bns.group_manager');

        $users = UserQuery::create()->findById($toList);
        //Vérification des droits
        /** @var BNSUserManager $userManager */
        $userManager = $this->get('bns.user_manager');

        $directUsersIds = array($rightManager->getUserSessionId());
        $undirectUsersIds = array();

        //Permission d'envoyer directement à l'extérieur
        $noExternalModerationGroupIds = $rightManager->getGroupIdsWherePermission('MESSAGING_NO_EXTERNAL_MODERATION');
        //Permission d'envoyer directement au groupe
        $noGroupModerationGroupIds = $rightManager->getGroupIdsWherePermission('MESSAGING_NO_GROUP_MODERATION');

        $whereMessaging = $this->get('bns.right_manager')->getGroupsWherePermission('MESSAGING_ACCESS');

        /*
         * Prise en compte des partenariats : si je fais partie d'un partenariat et que j'ai au moins une fois le droit d'envoyer à l'exterieur
         * Cela ajoute aux groupes autorisés les classes
        */

        foreach($whereMessaging as $groupWhereMessaging)
        {
            if($groupWhereMessaging->getGroupType()->getType() == 'PARTNERSHIP' && count($noExternalModerationGroupIds) > 0)
            {
                $groupManager->setGroup($groupWhereMessaging);
                $partners = $groupManager->getPartnersIds();
                foreach($partners as $partnerId)
                {
                    if(!in_array($partnerId, $noExternalModerationGroupIds))
                    {
                        $noGroupModerationGroupIds[] = $partnerId;
                    }
                }
            }
        }

        //Construction des tableaux de destinataires
        //Si SEND_ALL : je peux envoyer à tout le monde
        foreach($rightManager->getGroupsWherePermission('MESSAGING_SEND_ALL') as $sendAllGroup)
        {
            $groupManager->setGroup($sendAllGroup);
            $directUsersIds = array_merge($this->handlePartnershipForUserList($groupManager),$directUsersIds);
        }

        //Si qq part j'ai MESSAGING_SEND_CHILD je peux envoyer à mes enfants
        if(count($rightManager->getGroupIdsWherePermission('MESSAGING_SEND_CHILD')) > 0)
        {
            $children = $userManager->getUserChildren();
            foreach($children as $child)
            {
                $directUsersIds[] = $child->getId();
            }
        }

        //Si qq part j'ai MESSAGING_SEND_PARENT je peux envoyer à mes enfants
        if(count($rightManager->getGroupIdsWherePermission('MESSAGING_SEND_PARENT')) > 0)
        {
            $parents = $userManager->getUserParent();
            foreach($parents as $parent)
            {
                $directUsersIds[] = $parent->getId();
            }
        }

        $rolesTodo = array('PUPIL','PARENT','TEACHER');
        foreach($rolesTodo as $roleTodo)
        {
            foreach($rightManager->getGroupsWherePermission('MESSAGING_SEND_' . $roleTodo . 'S') as $sendGroup)
            {
                $groupManager->setGroup($sendGroup);
                if(
                    (in_array($sendGroup->getGroupType()->getType(),array("CLASSROOM","SCHOOL","TEAM",'PARTNERSHIP')) && in_array($sendGroup->getId(),$noGroupModerationGroupIds))
                    ||
                    (in_array($sendGroup->getGroupType()->getType(),array("SCHOOL","TEAM",'PARTNERSHIP')) && count($noExternalModerationGroupIds) > 0)
                )
                {
                    $directUsersIds = array_merge($this->handlePartnershipForUserList($groupManager,$roleTodo),$directUsersIds);
                }else{
                    $undirectUsersIds = array_merge($this->handlePartnershipForUserList($groupManager,$roleTodo),$undirectUsersIds);
                }
            }
        }

        //Cas spécial : ajout des enseignants des classes dans la liste des utilisateur forcément autorisés
        $accessibleGroups = $rightManager->getGroupsWherePermission('MESSAGING_ACCESS');
        foreach($accessibleGroups as $accessibleGroup)
        {
            if(in_array($accessibleGroup->getGroupType()->getType(),array("CLASSROOM","SCHOOL","TEAM")))
            {
                $groupManager->setGroup($accessibleGroup);
                $directUsersIds = array_merge($groupManager->getUsersByRoleUniqueNameIds('TEACHER'),$directUsersIds);
            }
        }

        $validatedUsers = array();
        $needModeration = false;

        foreach($users as $user)
        {
            if(in_array($user->getId(),$directUsersIds))
            {
                $validatedUsers[$user->getId()] = $user;
            }elseif(in_array($user->getId(),$undirectUsersIds)){
                $validatedUsers[$user->getId()] = $user;
                $needModeration = true;
            }
        }

        return array(
            'needModeration' => $needModeration,
            'validatedUsers' => $validatedUsers
        );
    }

}
