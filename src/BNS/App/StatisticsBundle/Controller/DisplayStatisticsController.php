<?php

namespace BNS\App\StatisticsBundle\Controller;

use BNS\App\StatisticsBundle\Form\Model\StatsFilterFormModel;
use BNS\App\StatisticsBundle\Form\Type\StatsFilterType;
use BNS\App\CoreBundle\Date\ExtendedDateTime;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use BNS\App\CoreBundle\Annotation\Rights;
use \BNS\App\CoreBundle\Model\GroupTypeQuery;

/**
 * Description of DisplayStatistics
 *
 * @author florian rotagnon <florian.rotagnon@atos.net>
 */
class DisplayStatisticsController extends Controller {

    /**
	 *
	 * @Route("", name="BNSAppStatisticsBundle_display_stats")
	 */
    public function displayStatsAction($isExport, $request, $originPath, $type = 'current')
	{
        //valeur des dates par défauts pour affichage sur get
		$monthEnd = new ExtendedDateTime("NOW");
        $monthStart = clone $monthEnd;
		$monthStart->modify('-1 month');
        $monthStart->setTime(0, 0, 0);
        $monthEnd->setTime(23, 59, 59);

        //valeurs par défaut
        $stats ='';
        $listGroups = array();
        $allGroupTypesAllowed = false;
        $groupTypes = array();
        $groupsAllowed = array();

        //récupération des services
        $currentGroup = $this->get('bns.right_manager')->getCurrentGroup();
        $gt = $this->get('bns.right_manager')->getManageableGroupTypes();

        //récupération du nom du groupe courant pour le titre de la page
        $currentGroupName = $currentGroup->getLabel();

        //filtre non étendu initialisation de valeur par défaut
        $expandFilters = false;
		$endFilter = clone $monthEnd;
		$statsModel = new StatsFilterFormModel($monthStart, $endFilter, $listGroups);

        /**** gestion des groupes types *****/

        // récupération du groupType courant
        $currentGroupType = GroupTypeQuery::create()
                ->filterById($currentGroup->getGroupTypeId())
                ->findOne()
                ->getType();

        //groupe types qui nous intéressent pour les stats
        $allGroupTypes = array(
            'TEACHER' => $this->get('translator')->trans('GROUP_TEACHER', array(), 'STATISTICS'),
            'PUPIL'	  => $this->get('translator')->trans('GROUP_PUPIL', array(), 'STATISTICS'),
            'PARENT'  => $this->get('translator')->trans('GROUP_PARENT', array(), 'STATISTICS')
        );

        //si on est dans la classe on a le droit d'accès a tous les sous-groupes roles
        if($currentGroupType == "CLASSROOM") {
            $allGroupTypesAllowed = true;
            $groupTypes = $allGroupTypes;
            $groupsAllowed = array();
        }
        else {//sinon on crée les filtres
            foreach ($gt as $value) {
                if(isset($allGroupTypes[$value->getType()])) {
                   $groupTypes[$value->getType()] = $allGroupTypes[$value->getType()];
                   $groupsAllowed[] = $value->getType();
                }
            }
        }

        //création du formulaire
        $form = $this->createForm(new StatsFilterType($groupTypes, $currentGroupName, $this->get("bns.right_manager"), $type), $statsModel);

        if ($request->isMethod('POST')) {
            $form->bind($request);
            if ($form->isValid()) {
                $groupName = $currentGroupName;

                //récupération des groupe types sélectionnés
                $groupType = $form->getData()->getGroupType();

                $groupTypeArray = array();
                //si l'utilisateur a choisi ALL
                if( $groupType == '') {
                    //si on est dans une classe on retourne les infos de tous les groupes
                    if($currentGroupType != "CLASSROOM") {//dans le cas groupe on regarde les droits de l'utilisateur courant
                        foreach ($gt as $groupT) {
                            $groupTypeArray[] = $groupT->getType();
                        }
                    }
                }
                else {
                    $groupTypeArray[] = $groupType;
                }



                $stats = $this->get("main_service_bns_app_statistics_bundle")->statFilter(
                            $form->getData()->getMarker(),
                            $groupTypeArray,
                            true,
                            $allGroupTypesAllowed,
                            $groupName,
                            $form->getData()->getPeriod(),
                            $form->getData()->getDateStart(),
                            $form->getData()->getDateEnd(),
                            $form->getData()->getTitle()
                );
                $expandFilters = true;
            }

		}
        //if GET ou POST invalide
        if(!$expandFilters){
            $stats = $this->get("main_service_bns_app_statistics_bundle")->statFilter('', $groupsAllowed, true, $allGroupTypesAllowed, $this->get('translator')->trans('ALL_SUBGROUPS', array(), 'STATISTICS'));
        }else if ($isExport) {
			return $this->exportCSV($stats["data"], $form->getData()->getPeriod(), $form->getData()->getMarker(), $stats["name"]);
        }

		return $this->render('BNSAppStatisticsBundle::statsContent.html.twig', array(
			'stats'			=> $stats,
			'form'			=> $form->createView(),
			'expandFilters'	=> $expandFilters,
            'graph_pro'     => $form->getData()->getGraphPro(),
            'controller_path' => $originPath,
            'group_name'    =>  $currentGroupName
		));
    }

    	/**
	 * @param array  $data
	 * @param string $titleLeft
	 * @param string $titleRight
	 *
	 * @return Response
	 */
	public function exportCSV($data, $titleLeft, $titleRight, $name)
	{
		$finalData = array();
		foreach ($data as $id => $tab) {
            $tab = json_decode($tab);
            foreach ($tab as $item){
                $date = new ExtendedDateTime();
                $date->setTimestamp($item[0] / 1000);

                switch($titleLeft) {
                    case "HOURS" :
                        $finalData[] = array(
                            gmdate("Y-m-d H", $item[0]),
                            $item[1],
                            $name[$id]
                        );
                        break;
                    case "MONTH" :
                        $finalData[] = array(
                            gmdate("Y-m", $item[0] / 1000),
                            $item[1],
                            $name[$id]
                        );
                        break;
                    default:
                        $finalData[] = array(
                            gmdate("Y-m-d", $item[0] / 1000),
                            $item[1],
                            $name[$id]
                        );
                        break;
                }
            }
		}

		$response = $this->render('BNSAppStatisticsBundle::export_csv.html.twig', array(
			'title' => array(
				$titleLeft,
				$titleRight,
                "GROUP"
			),
			'data'  => $finalData,
		));

		$response->headers->set('Content-Type', 'text/csv');
		$response->headers->set('Content-Disposition', 'attachment; filename="export_graphique.csv"');

		return $response;
	}

    /**
     *
     * @param group $currentGroup
     * @param string $subGroupType
     * @param service $classroomManager
     * @param service $pm
     * @param service $groupManager
     * @return groupArray
     */
    public function getSubGroups($currentGroup, $subGroupType, $classroomManager, $pm, $groupManager)
    {
        //déclare la liste des groupes a retourner
        $listGroups = array();
        //set le groupe courrant dans les services
        $groupManager->setGroup($currentGroup);
        $classroomManager->setClassroom($currentGroup);

        if($subGroupType == 'CURRENT' || $subGroupType == 'ALL')
        {
            //On ajoute le groupe courant a la liste des groupes
            $listGroups[] = $currentGroup;
        }

        if($subGroupType == 'CHILDREN' || $subGroupType == 'ALL') {
            //cas classe
            if($currentGroup->getGroupType()->getType() == "CLASSROOM") {
                //récupération des groupes d'élèves
                $teams = $classroomManager->getTeams();
                //ajout des teams a la liste des groupes
                foreach ($teams as $team) {
                    $listGroups[] = $team;
                }
            }
        }

        if($subGroupType == 'PARTNERSHIPS' || $subGroupType == 'ALL') {
            //cas classe
            if($currentGroup->getGroupType()->getType() == "CLASSROOM") {
                //Liste des partenariats auquels le groupe apartient
                $partnerships = $pm->getPartnershipsGroupBelongs($currentGroup->getId());
                //ajout des partenariat a la liste des groupes
                foreach ($partnerships as $partnership) {
                    $listGroups[] = $partnership;
                }
            }
        }

        //si non test d'arret => si on est pas au niveau classroom ET la recherche n'est pas de type groupe courrant
        if($currentGroup->getGroupType()->getType() != "CLASSROOM" && $subGroupType != 'CURRENT') {
            //pour chacun des sous-groupes qui ne sont pas des sous-groupes rôles
            foreach ( $groupManager->getSubgroups(true, false) as $subgroup) {
                //si on est dans le cas children on ajoute les groupes trouvés
                if($subGroupType == 'CHILDREN') {
                    $listGroups = \array_merge($listGroups, array($subgroup));
                }
                //on appel de manière récursive pour les enfants
                $ret = $this->getSubGroups($subgroup, $subGroupType, $classroomManager, $pm, $groupManager);
                //merge des enfants et du groupes courant
                $listGroups = \array_merge($listGroups, $ret);
            }
        }

        return $listGroups;
    }
}
