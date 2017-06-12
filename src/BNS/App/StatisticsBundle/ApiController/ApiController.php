<?php
namespace BNS\App\StatisticsBundle\ApiController;

use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\CoreBundle\Model\User;
use BNS\App\StatisticsBundle\Form\Type\StatisticFilterType;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\View\View;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class ApiController extends BaseApiController
{

    /**
     * @ApiDoc(
     *  section = "Statistiques",
     *  resource = true,
     *  description = "Liste des statistiques",
     *  statusCodes = {
     *      200 = "OK",
     *  }
     * )
     *
     * @Rest\Get("/statistics")
     * @Rest\View()
     *
     *
     * @RightsSomeWhere("STATISTICS_ACCESS")
     */
    public function getStatisticsAction()
    {
        $statisticManager = $this->get('bns.statistic_manager');

        return $statisticManager->getStatistics();
    }


    /**
     * @ApiDoc(
     *  section = "Statistiques",
     *  resource = true,
     *  description = "Liste des groups pour les filtres",
     *  statusCodes = {
     *      200 = "List des groups",
     *  }
     * )
     *
     * @Rest\Get("/filters/groups")
     * @Rest\View(serializerGroups={"list","groupUAI"})
     *
     * @RightsSomeWhere("STATISTICS_ACCESS")
     */
    public function getFiltersGroupsAction()
    {
        $groups =  $this->get('bns.statistic_manager')->getGroups($this->getUser());

        return $groups;
    }


    /**
     * <pre>
     *
     * </pre>
     *
     *
     * @ApiDoc(
     *  section = "Statistiques",
     *  resource = true,
     *  description = "Récupère données du graph",
     *  statusCodes = {
     *      200 = "Données du graph",
     *  }
     * )
     *
     * @Rest\Post("/{statistic}/graphs/{graph}")
     * @Rest\View(serializerGroups={"list"})
     *
     * @RightsSomeWhere("STATISTICS_ACCESS")
     */
    public function postGraphsAction(Request $request, $statistic, $graph)
    {
        $user = $this->getUser();
        $statManager = $this->get('bns.statistic_manager');
        $groups = $statManager->getGroups($user);

        $filterData = array(
            'start'  => new \DateTime('@' . strtotime('-1 month')),
            'end'    => new \DateTime('now'),
            'groups' => $groups->getArrayCopy(),
        );

        $form = $this->container->get('form.factory')
            ->createNamedBuilder('', new StatisticFilterType(), $filterData, array(
                'groups' => $groups->getArrayCopy(),
                'csrf_protection' => false
            ))
            ->getForm()
        ;
        $form->handleRequest($request);
        if ($form->isValid()) {
            $filterData = $form->getData();
        }

        return $statManager->getGraphData($statistic, $graph, $filterData);
    }


    /**
     * @ApiDoc(
     *  section = "Statistiques",
     *  resource = true,
     *  description = "Récupère données du tableau",
     *  statusCodes = {
     *      200 = "Données du tableau",
     *  }
     * )
     *
     * @Rest\Post("/{statistic}/tables")
     * @Rest\View(serializerGroups={"list"})
     *
     * @RightsSomeWhere("STATISTICS_ACCESS")
     */
    public function postTablesAction(Request $request, $statistic)
    {
        $user = $this->getUser();
        $statManager = $this->get('bns.statistic_manager');
        $groups = $statManager->getGroups($user);

        $filterData = array(
            'start'  => new \DateTime('@' . strtotime('-1 month')),
            'end'    => new \DateTime('now'),
            'groups' => $groups->getArrayCopy(),
        );

        $form = $this->container->get('form.factory')
            ->createNamedBuilder('', new StatisticFilterType(), $filterData, array(
                'groups' => $groups->getArrayCopy(),
                'csrf_protection' => false
            ))
            ->getForm()
        ;
        $form->handleRequest($request);
        if ($form->isValid()) {
            $filterData = $form->getData();
        }

        return $statManager->getTableData($statistic, $filterData);
    }

    /**
     * @ApiDoc(
     *  section = "Statistiques",
     *  resource = true,
     *  description = "Récupère données global pour CLASSROOM_SCHOOL_ACTIVATION",
     *  statusCodes = {
     *      200 = "Données du tableau",
     *  }
     * )
     *
     * @Rest\Post("/CLASSROOM_SCHOOL_ACTIVATION/globals")
     * @Rest\View(serializerGroups={"list"})
     *
     * @RightsSomeWhere("STATISTICS_ACCESS")
     */
    public function postClassroomSchoolActivationGlobalAction(Request $request)
    {
        $user = $this->getUser();
        $statManager = $this->get('bns.statistic_manager');
        $groups = $statManager->getGroups($user);

        $filterData = array(
            'start'  => new \DateTime('@' . strtotime('-1 month')),
            'end'    => new \DateTime('now'),
            'groups' => $groups->getArrayCopy(),
        );

        $form = $this->container->get('form.factory')
            ->createNamedBuilder('', new StatisticFilterType(), $filterData, array(
                'groups' => $groups->getArrayCopy(),
                'csrf_protection' => false
            ))
            ->getForm()
        ;
        $form->handleRequest($request);
        if ($form->isValid()) {
            $filterData = $form->getData();
        }

        $unDuplicateGroups = $statManager->unDublipcateGroups($filterData['groups']);
        $filterData['groups'] = $unDuplicateGroups['groups'];

        $datas = $this->get('bns_group.activation_statistics')->getTableData($filterData);

        $totals = array(
            'activatedSchools'    => 0,
            'activatedClassrooms' => 0,
            'classrooms'          => 0,
            'pupils'              => 0,
        );

        $totals = array_reduce($datas, function($totals, $items){
            $item = $items['totals'];
            $totals['activatedSchools']    += isset($item['activatedSchools']) ? $item['activatedSchools'] : 0;
            $totals['activatedClassrooms'] += isset($item['activatedClassrooms']) ? $item['activatedClassrooms'] : 0;
            $totals['classrooms']          += isset($item['classrooms']) ? $item['classrooms'] : 0;
            $totals['pupils']              += isset($item['pupils']) ? $item['pupils'] : 0;

            return $totals;
        }, $totals);

        return array(
            'totals' => $totals,
            'groups' => $unDuplicateGroups['groups'],
            'childGroups' => $unDuplicateGroups['childGroups'],
        );
    }


    /**
     * @ApiDoc(
     *  section = "Statistiques",
     *  resource = true,
     *  description = "Ajoute une visite sur un module (Pour les modules Angular)",
     *  statusCodes = {
     *      204 = "Visite prise en compte",
     *      400 = "Erreur : module incorrect",
     *      403 = "Pas accès au module",
     *  }
     * )
     *
     * @Rest\Post("/visits/{module}")
     */
    public function postVisitAction($module)
    {
        $user = $this->getUser();
        if (!$user || !$user instanceof  User) {
            // user not authenticated ignored the stats
            return View::create('', Response::HTTP_NO_CONTENT);
        }
        $module = strtoupper($module);
        $modules = $this->getStatModules();

        if (isset($modules[$module])) {
            // rights
            $rightManager = $this->get('bns.right_manager');
            $permition = $module . '_ACCESS';
            if ($rightManager->hasRight($permition) || $rightManager->hasRight($permition . '_BACK')) {
                $statService = $this->get($modules[$module]);
                if ($statService) {
                    $statService->visit();

                    return View::create('', Response::HTTP_NO_CONTENT);
                }
            } else {
                return View::create('', Response::HTTP_FORBIDDEN);
            }
        }

        return View::create('', Response::HTTP_BAD_REQUEST);
    }


    /**
     * Liste des Modules angular qui peuvent compter les visites via l'api
     *
     * @return array
     */
    protected function getStatModules()
    {
        return array(
            'HOMEWORK' => 'stat.homework',
            'MEDIA_LIBRARY' => 'stat.media_library',
            'MESSAGING' => 'stat.messaging',
            'SEARCH' => 'stat.search',
            'USER_DIRECTORY' => 'stat.user_directory',
            'WORKSHOP' => 'stat.workshop',
        );
    }
}
