<?php
namespace BNS\App\CoreBundle\Import;

use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\Import;
use BNS\App\CoreBundle\Classroom\BNSClassroomManager;
use \BNS\App\CoreBundle\Model\GroupTypeDataQuery;
use \BNS\App\CoreBundle\Model\GroupDataQuery;
use \BNS\App\CoreBundle\Model\GroupQuery;

/**
 * Description of ImportGroupConsumer
 *
 * @author alexandre.melard@worldline.com
 * @author Florian Rotagnon <florian.rotagnon@atos.net>
 */
class ImportSchoolConsumer extends AbstractImportConsumer
{
    /** @var BNSClassroomManager $classroomManager */ 
    private $classroomManager;
    /** @var  BNSGroupManager $groupManager */
    private $groupManager;
    private $importGroupId;

    
    public function setClassroomManager(BNSClassroomManager $cm)
    {
        $this->classroomManager = $cm;
    }
    
    public function setGroupManager($gm)
    {
        $this->groupManager = $gm;
    }

    /**
     * initialisation de l'importation
     * 
     * @param \BNS\App\CoreBundle\Model\Import $import
     */
    protected function onImport(Import $import)
    {
        
    }
    
    /**
     * pour chaque ligne apres verification des donnees
     * 
     * @param \BNS\App\CoreBundle\Model\Import $import
     * @param type $line
     */
    protected function onLineRead(Import $import, $line, $classroomStructure)
    {
        $this->logger->debug("parsing line ", $line);
        $this->importGroupId = $import->getGroupId();
        //recuperation des donnees
        $rne        = $line[0];   // UAI - N° Etablissement
        $insee      = $line[1];   // Commune - Code (INSEE)        
        $label      = $line[2];   // Appellation - Dénom. Comp. qqn sait à quoi correspond ce champ label ? pas dans les specs
        $adresse    = $line[3];   // Adresse Postale
        $codePostal = $line[4];   // Adresse - Code Postal
        $ville      = $line[5];   // Adresse - Localité d'Acheminement

        /*
         * Gestion de la circonscription : règle
         *
         *  Si Circo RNE non renseigné => on prend la ville qui doit être créée
         *  Si Circo RNE renseignée => on prend la Circo qui doit être créée
         *  Si Circo RNE et Circo Label renseignés => On prend la ville dans la circo, on crée les données si cela n'a pas été fait
         */

        if (sizeof($line) >= 7) {
            $circoRNE = $line[6];   // Circonscription IEN - RNE - optionnel
        } else {
            $circoRNE =null;
        }
        if (sizeof($line) >= 8) {
            $circoLabel = $line[7];   // Nom de la circonscription
        } else {
            $circoLabel =null;
        }

        /**
         * Circo RNE et et CircoLabel sont facultatifs, Insee est obligatoire
         */

        $parent = null;

        if ($insee != null)
        {
            $this->logger->debug("---------------------------- insee different de null, on recherche une ville");
            $parentType = 'CITY';
            $parentC = GroupQuery::create()
                ->filterBySingleAttribute('INSEE_ID', $insee)
                    ->useGroupTypeQuery()
                        ->filterByType('CITY')
                    ->endUse()
                ->findOne();
            if($parentC == null)
            {
                $paramsCity = array(
                    'label' => $ville,
                    'INSEE_ID' => $insee,
                    'CIRCO_ID' => $circoRNE,
                    'CircoLabel' => $circoLabel
                );
                $parentC = $this->createCity($paramsCity);
            }
        }
        if ($circoRNE != null && $circoRNE != "") {

            $parentType = 'CIRCONSCRIPTION';
            $parentCirco = GroupQuery::create()
                ->filterBySingleAttribute('CIRCO_ID', $circoRNE)
                    ->useGroupTypeQuery()
                        ->filterByType('CIRCONSCRIPTION')
                    ->endUse()
                ->findOne();

            if($parentCirco == null)
            {
                $paramsCirco = array(
                    'label' => $circoLabel,
                    'CIRCO_ID' => $circoRNE
                );
                $parentCirco = $this->createCirco($paramsCirco);
            }
        }

        //creation de l'ecole a partir des informations

        $exists = GroupQuery::create()
            ->filterBySingleAttribute('UAI', $rne)
            ->useGroupTypeQuery()
            ->filterByType('SCHOOL')
            ->endUse()
            ->findOne();

        if(!$exists)
        {
            $params = array();
            // Setting params
            $params['label'] = $label;
            $params['group_type_id'] = GroupTypeQuery::create()->findOneByType('SCHOOL')->getId();
            $params['type_unique_name'] = 'SCHOOL';
            $params['validated'] = true;

            if($circoRNE != "")
            {
                $params['parent_filter']['CIRCO_ID'] = $circoRNE;
            }
            if($insee  != "")
            {
                $params['parent_filter']['INSEE_ID'] = $insee;
            }
            $params['attributes']['NAME'] = $label;
            $params['attributes']['UAI'] = $rne;
            $params['attributes']['ADDRESS'] = $adresse;
            $params['attributes']['CITY'] = $ville;
            $params['attributes']['ZIPCODE'] = $codePostal;

            // Create group

            $this->addGroupToImport($params);


        }else{

            //Vérification des affectations
            //Ville de bouge pas, peut être Circo
            $this->groupManager->setGroup($exists);
            $change = false;

            foreach($this->groupManager->getAncestors() as $ancestor)
            {
                if($ancestor->getGroupType()->getType() == 'CIRCONSCRIPTION')
                {
                    $ancestorCirco = $ancestor;
                    if($ancestor->getAttribute('CIRCO_ID') != $parentCirco->getAttribute('CIRCO_ID'))
                    {
                        $change = true;
                        $this->groupManager->deleteParent($this->groupManager->getGroup()->getId(), $ancestor->getId());
                        $this->groupManager->addParent($this->groupManager->getGroup()->getId(), $parentCirco->getId());
                    }
                }
                if($ancestor->getGroupType()->getType() == 'CITY')
                {
                    $ancestorCity = $ancestor;
                    if($ancestor->getAttribute('INSEE_ID') != $parentC->getAttribute('INSEE_ID'))
                    {
                        $change = true;
                        $this->groupManager->deleteParent($this->groupManager->getGroup()->getId(), $ancestor->getId());
                        $this->groupManager->addParent($this->groupManager->getGroup()->getId(), $parentC->getId());
                    }
                }
            }
            $this->groupManager->setGroup($ancestorCity);
            if($change)
            {
                foreach($this->groupManager->getParents() as $parent)
                {
                    if($parent->getGroupType()->getType() == 'CIRCONSCRIPTION')
                    {
                        $this->groupManager->deleteParent($this->groupManager->getGroup()->getId(), $parent->getId());
                        $this->groupManager->addParent($this->groupManager->getGroup()->getId(), $this->importGroupId);
                    }
                }
            }
        }
    }

    /**
     * Méthode de création de ville
     * @param $params
     */
    protected function createCity($params)
    {
        $params['label'] = $params['label'];
        $params['attributes']['INSEE_ID'] = $params['INSEE_ID'];
        $params['group_type_id'] = GroupTypeQuery::create()->findOneByType('CITY')->getId();
        $params['type'] = 'CITY';
        $params['validated'] = true;
        $params['parent_id'] = $this->importGroupId;
        //On créé directement pour éviter conflits
        return $this->groupManager->createGroup($params);
    }

    /**
     * Méthode de création d'une circo
     * @param $params
     */
    protected function createCirco($params)
    {
        // Setting params
        $params['label'] = $params['label'];
        $params['group_type_id'] = GroupTypeQuery::create()->findOneByType('CIRCONSCRIPTION')->getId();
        $params['type'] = 'CIRCONSCRIPTION';
        $params['validated'] = true;

        $params['parent_id'] = $this->importGroupId;
        $params['attributes']['CIRCO_ID'] = $params['CIRCO_ID'];
        //On créé directement pour éviter conflits
        return $this->groupManager->createGroup($params);

    }

}
