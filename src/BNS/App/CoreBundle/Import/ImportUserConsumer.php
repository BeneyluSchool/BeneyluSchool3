<?php

namespace BNS\App\CoreBundle\Import;

use BNS\App\CoreBundle\Model\GroupDataQuery;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\UserGuideTourQuery;
use BNS\App\CoreBundle\Role\BNSRoleManager;
use BNS\App\CoreBundle\User\BNSUserManager;
use BNS\App\CoreBundle\Classroom\BNSClassroomManager;
use BNS\App\CoreBundle\Model\Import;
use \BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\UserQuery;

/**
 * Description of ImportUserConsumer
 *
 * @author alexandre.melard@worldline.com
 * @author Florian Rotagnon <florian.rotagnon@atos.net>
 */
class ImportUserConsumer extends AbstractImportConsumer {

    /** @var BNSUserManager $userManager */
    private $userManager;

    /** @var BNSRoleManager $roleManager */
    private $roleManager;

    /** @var BNSClassroomManager $classroomManager */
    private $classroomManager;
    private $groupManager;

    public function setClassroomManager(BNSClassroomManager $cm) {
        $this->classroomManager = $cm;
    }

    public function setUserManager(BNSUserManager $um) {
        $this->userManager = $um;
    }

    public function setRoleManager(BNSRoleManager $rm) {
        $this->roleManager = $rm;
    }

    public function setGroupManager($gm) {
        $this->groupManager = $gm;
    }

    /**
     * initialisation de l'importation
     *
     * @param \BNS\App\CoreBundle\Model\Import $import
     */
    protected function onImport(Import $import) {

    }




    public function buildClassroomStructure()
    {
        $struc = array();
        $values = GroupDataQuery::create()
            ->useGroupTypeDataQuery()
            ->filterByGroupTypeId(2)
            ->filterByGroupTypeDataTemplateUniqueName('STRUCTURE_ID')
            ->endUse()
            ->find();
        foreach($values as $value)
        {
            $struc[$value->getValue()]['Id'] = $value->getGroupId();
        }
        return $struc;
    }

    public function buildSchoolStructure()
    {
        $struc = array();
        $values = GroupDataQuery::create()
            ->useGroupTypeDataQuery()
            ->filterByGroupTypeId(3)
            ->filterByGroupTypeDataTemplateUniqueName('UAI')
            ->endUse()
            ->find();
        foreach($values as $value)
        {
            $struc[$value->getValue()] = $value->getGroupId();
        }
        return $struc;
    }


    /**
     * Retrouve sexe parmis les différents cas possibles en entrée (M/F, 1/2)
     * @param $value
     * @return string M ou F, M par défaut
     */
    protected function findGender($value)
    {
        if(in_array($value,array('F','M')))
        {
            return $value;
        }elseif(in_array($value,array('1','2'))){
            if($value == '1')
                return "M";
            else
                return "F";
        }
        return "M";
    }

    protected static $classroomStructure = false;
    protected static $schoolStructure = false;

    /**
     * pour chaque ligne apres verification des donnees
     *
     * @param \BNS\App\CoreBundle\Model\Import $import
     * @param type $line
     */
    protected function onLineRead(Import $import, $line, $classroomStructureOLD, $schoolStructureOLD) {

        if(self::$classroomStructure === false)
        {
            self::$classroomStructure = $this->buildClassroomStructure();
        }

        if(self::$schoolStructure === false)
        {
            self::$schoolStructure = $this->buildSchoolStructure();
        }


        //initialisation
        $type = $import->getType();

        //pour tous les utilisateurs
        //recuperation des infos globales
        $values = array();
        $cs = $line[0];
        $uai = $line[1];
        $values['last_name'] = trim($line[2]);
        $values['first_name'] = trim($line[3]);
        $values['email'] = $line[4];
        $values['lang'] = 'fr';
        $values['import_id'] = $import->getId();
        if (isset($line[6])) {
            //Adulte
            $values['email'] = trim($line[4]);
            $values['gender'] = $this->findGender($line[5]);
        } else {
            //Eleve
            $values['gender'] = $this->findGender($line[4]);
            $values['email'] = null;
            if(isset($line[5]) && $line[5] != "")
            {
                $birthdayArray = explode('/',$line[5]);
                $values['birthday'] = $birthdayArray[2] . '-' . $birthdayArray[1] . '-' . $birthdayArray[0];
            }
        }

        //recuperation de l'ID correspondant a structure_id
        if($cs != "")
        {
            if(isset(self::$classroomStructure[$cs]))
            {
                $classroom = self::$classroomStructure[$cs];
            }else{
                $classroom = null;
            }
        }else{
            $classroom = null;
        }

        $parentIds = array();

        //si l'identifiant de la classe n'est pas fournit on rattache a l'école
        if ($classroom == null || $cs == null || trim($cs) == "") {
            //Il peut y avoir plusieurs affectations
            if(strpos($uai,','))
            {
                $schools = explode(',',$uai);
                foreach($schools as $schoolUai)
                {

                    if(isset(self::$schoolStructure[$uai]))
                    {
                        $parentIds[] = self::$schoolStructure[$uai];
                    }

                }
            }else{

                if(isset(self::$schoolStructure[$uai]))
                {
                    $parentIds[] = self::$schoolStructure[$uai];
                }
            }



        }//sinon on rattache a la classe
        elseif(isset($classroom['Id'])){

            $parentIds[] = $classroom['Id'];
        }

        //Uniquement sur Instance, pas sur annuaire
        if($values['email'] != null && $values['email'] != "")
        {
            $user = UserQuery::create()->filterByEmail($values['email'])->findOne();
            if(!$user){
                $user = UserQuery::create()
                    ->filterByLastName($values['last_name'])
                    ->filterByFirstName($values['first_name'])
                    ->findOne();
            }

        }else{
            $user = UserQuery::create()
                ->filterByLastName($values['last_name'])
                ->filterByFirstName($values['first_name']);

            if(isset($values['birthday']) && $values['birthday'] != null && trim($values['birthday'] != ""))
            {
                $user->filterByBirthday($values['birthday']);
            }

            $user = $user->find();

            if(count($user) > 1)
            {
                $found = false;
                foreach($user as $potentialUser)
                {
                    if($potentialUser->isArchived() && $potentialUser->getArchiveDate('U') > strtotime("-2 days")){
                        $user = $potentialUser;
                        $found = true;
                    }
                }
                if($found == false){
                    $user = null;
                }
            }elseif(count($user) == 1){
                $user = $user->getFirst();
            }elseif(count($user) == 0)
            {
                unset($user);
            }
        }

        if(isset($parentIds))
        {
            if(!isset($user))
            {

                if($type == 'PUPIL')
                {
                    //On affecte le rôle PUPIL au parentId
                    $pupilRole = $this->groupTypeCache['PUPIL'];
                    foreach($parentIds as $parentId)
                    {
                        $values['roles'][] = array(
                            'id' => $parentId,
                            'group_type_id' => $pupilRole->getId()
                        );
                    }
                    $values['high_role_id'] = 8;
                    $this->addUserToImport($values,false);
                    $values['roles'] = null;
                    if(isset($values['birthday']))
                    {
                        unset($values['birthday']);
                    }
                    //Dermande de création du parent
                    $values['first_name'] = "Parents de " . $values['first_name'];
                    $values['gender'] = 'M';
                    $parentRole = $this->groupTypeCache['PARENT'];
                    foreach($parentIds as $parentId)
                    {
                        $values['roles'][] = array(
                            'id' => $parentId,
                            'group_type_id' => $parentRole->getId()
                        );
                    }
                    $values['is_parent'] = true;
                    $values['high_role_id'] = 9;
                    $this->addUserToImport($values);
                }elseif($type == "ADULT"){

                    $type = ($line[6] == '1') ? 'DIRECTOR' : 'TEACHER';
                    $adultRole = $this->groupTypeCache[$type];
                    foreach($parentIds as $parentId)
                    {
                        $values['roles'][] = array(
                            'id' => $parentId,
                            'group_type_id' => $adultRole->getId()
                        );
                        if($line[6] == '1')
                        {
                            $values['roles'][] = array(
                                'id' => $parentId,
                                'group_type_id' => $this->groupTypeCache['TEACHER']->getId()
                            );
                        }
                    }
                    switch($type)
                    {
                        case 'DIRECTOR':
                            $values['high_role_id'] = $this->groupTypeCache['DIRECTOR']->getId();
                            break;
                        case 'TEACHER':
                            $values['high_role_id'] = 7;
                            break;
                        default:
                            $values['high_role_id'] = $this->groupTypeCache['DIRECTOR']->getId();
                            break;
                    }
                    $this->addUserToImport($values);
                }
            }else{

                if($type == "ADULT")
                {
                    $type = ($line[6] == '1') ? 'DIRECTOR' : 'TEACHER';
                    $adultRole = $this->groupTypeCache[$type];
                    $affectations = array('userId' => $user->getId(), 'roleId' => $adultRole->getId());
                    foreach($parentIds as $parentId)
                    {
                        $affectations['groupIds'][] = $parentId;
                    }
                    $this->addAffectationsToImport($affectations);
                    if($type == 1)
                    {
                        $adultRole = $this->groupTypeCache['TEACHER'];
                        $affectations = array('userId' => $user->getId(), 'roleId' => $adultRole->getId());
                        foreach($parentIds as $parentId)
                        {
                            $affectations['groupIds'][] = $parentId;
                        }
                        $this->addAffectationsToImport($affectations);
                    }

                    $affectations = array('userId' => $user->getId(), 'roleId' => $this->groupTypeCache['TEACHER']->getId());

                    foreach($this->userManager->setUser($user)->getSimpleGroupsAndRolesUserBelongs(true, 2) as $classroomToKeep)
                    {
                        if(!$classroomToKeep->isArchived())
                        {
                            $affectations['groupIds'][] = $classroomToKeep->getId();
                        }
                    }

                    $this->addAffectationsToImport($affectations);

                    $user->setImportId($import->getId());

                    if($user->getGender() != $values['gender'])
                    {
                        $user->setGender($values['gender']);
                    }
                    $user->save();
                    if($user->isArchived())
                    {
                        $user->restore();
                    }
                }elseif($type == 'PUPIL'){

                    $user->setImportId($import->getId());
                    $user->setGender($values['gender']);
                    $user->save();

                    if($user->isArchived())
                    {
                        $user->restore();
                    }

                    $pupilRole = $this->groupTypeCache['PUPIL'];

                    $affectations = array('userId' => $user->getId(), 'roleId' => $pupilRole->getId());

                    foreach($parentIds as $parentId)
                    {
                        $affectations['groupIds'][] = $parentId;
                    }
                    $this->addAffectationsToImport($affectations);
                    //Dermande de création du parent

                    $parentRole = $this->groupTypeCache['PARENT'];

                    foreach($user->getPupilParentLinksRelatedByUserPupilId() as $link)
                    {
                        $affectations = array('userId' => $link->getUserParentId(), 'roleId' => $parentRole->getId());
                        foreach($parentIds as $parentId)
                        {
                            $affectations['groupIds'][] = $parentId;
                        }
                        //On redonne le bon import Id
                        $parent = UserQuery::create()->findOneById($link->getUserParentId());
                        if($parent)
                        {
                            $parent->setImportId($import->getId());
                            if($parent->isArchived())
                            {
                                $parent->restore();
                            }
                            $parent->save();
                        }
                    }
                    $this->addAffectationsToImport($affectations);
                }
            }
        }else{
            $this->logger->debug("User not created  [" . $line[4] . "]");
        }
        $values = null;
        $parentIds = null;
        $exists = null;
        $user = null;
        $school = null;
        $classroom  = null;
        $affectations = null;
        $parentRole = null;
        $parents = null;
        $adultRole = null;
        $ancestor = null;
        $users = null;
        $parent = null;
        unset($values, $parentIds, $exists, $user, $school, $classroom,$affectations,$parentRole, $parents, $adultRole, $ancestor, $users, $parent);
    }
}