<?php

namespace BNS\App\CoreBundle\Partnership;

use BNS\App\CoreBundle\Api\BNSApi;
use BNS\App\CoreBundle\Module\BNSModuleManager;
use BNS\App\CoreBundle\Role\BNSRoleManager;
use BNS\App\CoreBundle\User\BNSUserManager;
use BNS\App\MailerBundle\Mailer\BNSMailer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\Module\IBundleActivation;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @author El Mehdi Ouarour <el-mehdi.ouarour@atos.net>
 *
 * Service permettant la gestion des partenariats
 */
class BNSPartnershipManager extends BNSGroupManager implements IBundleActivation
{
    protected $partnership;
    protected $mailer;

    /**
     * @param ContainerInterface $container
     * @param BNSRoleManager $roleManager
     * @param BNSUserManager $userManager
     * @param BNSApi $api
     * @param BNSModuleManager $moduleManager
     * @param int $domainId
     * @param BNSMailer $mailer
     */
    public function __construct($container, $roleManager, $userManager, $api, $moduleManager, $domainId, $mailer)
    {
        parent::__construct($container, $roleManager, $userManager, $api, $moduleManager, $domainId);

        $this->mailer = $mailer;
    }

    /*
     * Création d'un partenariat
     *
     * @param array $params
     *
     * @return Group
     */
    public function createPartnership($params)
    {
        if (!isset($params['label'])) {
            throw new HttpException(500, 'Please provide a partnership name!');
        }

        $partnershipGroupTypeRole = GroupTypeQuery::create()->findOneByType('PARTNERSHIP');
        $newPartnershipParams = array(
            'group_type_id' => $partnershipGroupTypeRole->getId(),
            'type'          => $partnershipGroupTypeRole->getType(),
            'domain_id'	=> $this->domainId,
            'label'		=> $params['label'],
            'validated'	=> isset($params['validated']) && $params['validated'] ? true : false,
            'attributes' => $params['attributes']
        );

        $this->partnership = $this->createGroup($newPartnershipParams);
        $this->setPartnership($this->partnership);

        //création lien entre partenariat er le groupe courant
        $centralGroup = $this->getGroupFromCentral($this->partnership->getId());
        $this->joinPartnership($centralGroup['uid'], $params['group_creator_id']);

        return $this->partnership;
    }

    /*
     * Retourne le partenariat en question à partir de son uid
     *
     * @param type $uid
     *
     * @return Group
     */
    public function getPartnershipByUid($uid)
    {
        $response = $this->api->send('get_partnership',array('route' => array('uid' => $uid)),true);

        if(null != $response)
        {
            $partnership = GroupQuery::create()->findOneById($response['id']);
            return $partnership;
        }

        //suppression de la clé du cache redis car ne sert à rien puisque aucun partenarat ne correspond à l'uid
        $this->api->resetPartnershipRead($uid);

        return null;
    }

    /*
     * Vérifie si un goupe identifié par $groupId fait parti d'un partenariat
     * identifié par $uid
     *
     * @param int $partnershipId
     * @param int $groupId
     *
     * @return boolean
     */
    public function isAlreadyMemberofPartnership($partnershipId, $groupId)
    {
        $partnershipsGroupBelongs = $this->getPartnershipsGroupBelongs($groupId);

        foreach($partnershipsGroupBelongs as $partnership)
        {
            if($partnershipId == $partnership->getId())
            {
                return true;
            }
        }
        return false;
    }

    /*
     * ajouter le goupe identifié par $groupId au partenariat identifié par $uid
     *
     * @param type $uid
     * @param int $groupId
     *
     * @return boolean
     */
    public function joinPartnership($uid, $groupId)
    {
        $partnership = $this->getPartnershipByUid($uid);

        if (!$partnership) {
            throw new NotFoundHttpException('Partnership not found');
        }

        if(! $this->isAlreadyMemberofPartnership($partnership->getId(), $groupId))
        {
            $response = $this->api->send('join_partnership',array('route' => array('uid' => $uid, 'group_id' => $groupId)),false);

            //reset du cahe redis
            $this->resetPartnershipCache($partnership->getId(), $groupId);

            return true;
        }
        return false;
    }

    /*
     * Retourne la liste des partenariats auquels un groupe identifié par $groupId
     * est membre
     *
     * @param int $groupId
     *
     * @return array|Group[]|\PropelObjectCollection $partnerships
     */
    public function getPartnershipsGroupBelongs($groupId)
    {
        $response = $this->api->send('partnerships_group_belongs',array('route' => array('group_id' => $groupId)),true);

        $groupsIds = array();

        foreach($response as $group)
        {
            $groupsIds[] = $group['group_id'];
        }

        $partnerships = GroupQuery::create()->orderByLabel()->findById($groupsIds);

        return $partnerships;
    }

    /*
     * Retourne la liste des membre d'un partenariat identifié par $uid
     *
     * @param int $partnershipId
     *
     * @return array $members
     */
    public function getPartnershipMembers($partnershipId)
    {
        $response = $this->api->send('partnership_members',array('route' => array('partnership_id' => $partnershipId)),true);

        $groupsIds = array();
        foreach($response as $group)
        {
            $groupsIds[] = $group['friend_id'];
        }

        $members = GroupQuery::create()->orderByLabel()->findById($groupsIds);

        return $members;
    }

    /*
     * Retourne la liste des noms des parents des membres d'un partenariat
     *
     * @param array $members
     *
     * @return array $parentsNames
     */
    public function getParentsNamesOfMembers($members)
    {
        $parentsNames = array();
        foreach ($members as $member) {
            $parents = $this->getParents($member);
            if (isset($parents[0]) && in_array($parents[0]->getType(), ['SCHOOL', 'HIGH_SCHOOL'])) {
                $member->parent = $parents[0];
                $parentsNames[$member->getId()] = $member->getFullParentLabel() ?: $parents[0]->getLabel();
            }
        }

        return $parentsNames;
    }

    /*
     * Retourne le nombre de membres d'un partenariat
     *
     * @param int $partnershipId
     *
     * @return int
     */
    public function getNumberOfPartnershipMembers($partnershipId)
    {
        return sizeof($this->getPartnershipMembers($partnershipId));
    }

    /*
     * supprime le lien entre un partenariat identifié par $uid et un groupe
     * identifié par $groupId
     *
     * @param int $partnershipId
     * @param int $groupId
     *
     * @return type
     */
    public function leavePartnership($partnershipId, $groupId)
    {
        $response = $this->api->send('leave_partnership',array('route' => array('partnership_id' => $partnershipId, 'group_id' => $groupId)),false);

        //reset du cahe redis
        $this->resetPartnershipCache($partnershipId, $groupId);

        //Si le groupe a été supprimé de la centrale on le supprime du côté app aussi
        if($response['deleted'])
        {
            $this->deleteGroup($partnershipId, false);

            //suppression de la clé redis qui correspond au partenariat supprimé
            $centralGroup = $this->getGroupFromCentral($partnershipId);
            $this->api->resetPartnershipRead($centralGroup['uid']);
        }

        return $response['deleted'];
    }

    /**
     * @return Group
     */
    public function getPartnership()
    {
        return $this->partnership;
    }

    /**
     * @param type $partnership
     *
     * @return BNSPartnershipManager
     */
    public function setPartnership($partnership)
    {
        $this->partnership = $partnership;
        $this->setGroup($partnership);

        return $this;
    }

    /**
     * @param type $groupTypeRoleType
     *
     * @return type
     *
     * @throws HttpException
     */
    public function getRuleWhoFromGroupTypeRole($groupTypeRoleType)
    {
        $groupTypeRole = GroupTypeQuery::create()->findOneByType($groupTypeRoleType);

        if (null == $groupTypeRole) {
            throw new HttpException(500, 'Group type role type given is invalid; must be equals to : TEACHER || PARENTS || PUPIL!');
        }

        return array(
                'domain_id'         => $this->domainId,
                'group_parent_id'   => $this->getGroup()->getId(),
                'group_type_id'     => $groupTypeRole->getId()
        );
    }

    /*
     * Mise à jour d'un partenariat
     *
     * @param array $params
     */
    public function updatePartnership($params)
    {
        $partnership = $this->getPartnership();
        //On ballaie tous les params pour mettre à jour l'objet en local, puis on envoie les modifications à la centrale
        if(isset($params['label'])){
            $partnership->setLabel($params['label']);
        }
        $partnership->save();

        $datas['id'] = $partnership->getId();
        $datas['label'] = $partnership->getLabel();

        $this->api->send('partnership_update',array('route' => array('group_id' => $partnership->getId()),'values' => $datas),false);

        if($partnership->getAgenda()) {
            $partnership->getAgenda()->setTitle($partnership->getLabel())->save();
        }

        if($partnership->getMediaFolderRoot()) {
            $partnership->getMediaFolderRoot()->setLabel($partnership->getLabel())->save();
        }
    }

    /**
     * Vérifie que le paramètre $partnership est de type partenariat
     *
     * @param Group $partnership
     *
     * @throws NotFoundHttpException
     */
    public function checkPartnershipExists($partnership)
    {
        if($partnership->getGroupType()->getType() != 'PARTNERSHIP' || $partnership == null )
        {
            throw new NotFoundHttpException('The partnership with slug : ' . $partnership->getSlug() . ' is NOT found !');
        }
    }

    /**
     * Vérifie que le groupe identifié par $groupId est membre de $partnership
     *
     * @param Group $partnership
     * @param int $groupId
     *
     * @throws AccessDeniedHttpException
     */
    public function checkIfGroupMemberOfPartnership($partnership, $groupId)
    {
        if(! $this->isAlreadyMemberofPartnership($partnership->getId(), $groupId))
        {
            throw new AccessDeniedHttpException('Forbidden Action');
        }
    }

    /**
     * Supprimme le cache redis
     *
     * @param Group $partnership
     * @param int $groupId
     */
    public function resetPartnershipCache($partnershipId, $groupId)
    {
        //reset des droits des utilisateures du groupe courrant
        // TODO check me this only reset group cache not user
        $this->api->resetGroup($groupId, false);

        //reset du cache des partenariats auquels le groupe courant appartient
        $this->api->resetPartnershipsGroupBelongs($groupId);

        //reset du cache des membres d'un partenariat
        $this->api->resetPartnershipMembers($partnershipId);
    }

}
