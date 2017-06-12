<?php
namespace BNS\App\CoreBundle\Import;

use BNS\App\CoreBundle\Model\GroupDataQuery;
use BNS\App\CoreBundle\Role\BNSRoleManager;
use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\Model\Import;
use \BNS\App\CoreBundle\Model\GroupQuery;
use \BNS\App\CoreBundle\Model\GroupTypeQuery;

class ImportClassConsumer extends AbstractImportConsumer
{
    /** @var BNSGroupManager $groupManager */
    private $groupManager;

    /** @var BNSRoleManager $roleManager */
    private $roleManager;

    public function setGroupManager(BNSGroupManager $gm)
    {
        $this->groupManager = $gm;
    }
    
    public function setRoleManager(BNSRoleManager $rm)
    {
        $this->roleManager = $rm;
    }
    
    /**
     * initialisation de l'importation
     * 
     * @param \BNS\App\CoreBundle\Model\Import $import
     */
    protected function onImport(Import $import)
    {
        
    }

    protected static $i = 0;

    public static $classroomStructure = false;

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
    
    /**
     * pour chaque ligne apres verification des donnees
     * 
     * @param \BNS\App\CoreBundle\Model\Import $import
     * @param type $line
     */
    protected function onLineRead(Import $import, $line, $structure = false, $structure = false)
    {

        if(self::$classroomStructure === false)
        {
            self::$classroomStructure = $this->buildClassroomStructure();
        }


        $cse     = $line[0];    // Codestructure Enseignement
        $uai     = $line[1];    // UAI
        $name    = $line[2];   // Label

        if(isset(self::$classroomStructure[$cse]))
        {
            $exists = GroupQuery::create()->findOneById(self::$classroomStructure[$cse]);
        }


        //N'existe pas, on crée
        if($exists != null)
        {
            $schoolParent = GroupQuery::create()
                ->filterBySingleAttribute('UAI', $uai)
                ->find();

            if(count($schoolParent) > 1)
            {
                foreach($schoolParent as $potentialSchool)
                {
                    $this->groupManager->setGroup($potentialSchool);
                    if($this->groupManager->getProjectInfo('name') == $import->getProjectName())
                    {
                        $schoolValidated = $potentialSchool;
                    }
                }
            }else{
                $schoolValidated = $schoolParent->getFirst();
            }

            if(isset($schoolValidated) && $schoolValidated != null)
            {
                $params['label'] = $name;
                $params['group_type_id'] = GroupTypeQuery::create()->findOneByType('CLASSROOM')->getId();
                $params['type_unique_name'] = 'CLASSROOM';
                $params['validated'] = true;
                $params['parent_id'] = $schoolValidated->getId();
                $params['country'] = $schoolValidated->getCountry();
                $params['attributes']['STRUCTURE_ID'] = $cse;
                $params['import_id'] = $import->getId();
                $this->addGroupToImport($params);
            }else{
                var_dump('No school for UAI : ' . $uai);
                self::$i = self::$i + 1;
            }
        }else{
            //Sinon on met à jour l'import Id
            if($exists->isArchived())
            {
                $this->groupManager->restoreGroup($exists->getId());
            }
            $exists->setImportId($import->getId());
            $exists->save();
        }
    }
}
