<?php
namespace BNS\App\GPSBundle\Controller;
use BNS\App\CoreBundle\Controller\BaseController;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Response;
use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\GPSBundle\Model\GpsPlace;
use BNS\App\GPSBundle\Model\GpsPlaceQuery;
use BNS\App\GPSBundle\Model\GpsCategory;
use BNS\App\GPSBundle\Model\GpsCategoryQuery;

use BNS\App\GPSBundle\Form\Type\GpsPlaceType;
use Symfony\Component\Form\FormError;

/**
 * @Route("/gestion")
 */

class BackController extends BaseController
{

    protected function checkRights($gpsPlace = null,$gpsCategory = null)
    {
        $rm = $this->get('bns.right_manager');
        $currentGroupId = $rm->getCurrentGroupId();
        if($gpsPlace != null){
            $rm->forbidIf($currentGroupId != $gpsPlace->getGroupId());
        }
        if($gpsCategory != null){
            $rm->forbidIf($currentGroupId != $gpsCategory->getGroupId());
        }
    }

    /**
     * Index du back GPS : liste des lieux du groupe en cours
     * @Route("", name="BNSAppGPSBundle_back")
     * @Template()
     * @Rights("GPS_ACCESS_BACK")
     */
    public function indexAction()
    {
        $this->get('stat.gps')->visit();

        $rm = $this->get('bns.right_manager');
        $currentGroupId = $rm->getCurrentGroupId();
        $this->get('session')->remove('gps_back_selected_category');

        return array(
            'categories' => GpsCategoryQuery::create()->orderByOrder()->findByGroupId($currentGroupId)
        );
    }

    /**
     * Liste des lieux selon ou non la selection faite
     * @Route("/liste-des-lieux/{categoryId}", name="BNSAppGPSBundle_back_places_list", options={"expose"=true} , defaults={"categoryId" = "toutes"}))
     * @Rights("GPS_ACCESS_BACK")
     * @Template()
     */
    public function placesListAction($forIndex = true)
    {
        $placesQuery = GpsPlaceQuery::create()->orderByGpsCategoryId()->orderByLabel()->join('GpsCategory');

        $rm = $this->get('bns.right_manager');
        $groupId = $rm->getCurrentGroupId();
        $placesQuery->useGpsCategoryQuery()->filterByGroupId($groupId)->endUse();

        if(!$forIndex){
            $current = $this->get('session')->get('gps_back_selected_category');
            if(count($current) > 0){
                $placesQuery->filterByGpsCategoryId($current);
            }
        }

        return array('places' => $placesQuery->find());
    }

    /**
     * Index du back GPS : liste des lieux du groupe en cours
     * @Route("/barre", name="BNSAppGPSBundle_back_sidebar")
     * @Rights("GPS_ACCESS_BACK")
     * @Template()
     */
    public function sidebarAction($canEdit = false)
    {
        $rm = $this->get('bns.right_manager');
        $groupId = $rm->getCurrentGroupId();
        $categories = GpsCategoryQuery::create()->orderByOrder()->findByGroupId($groupId);
        return array('categories' => $categories,'canEdit' => $canEdit);
    }

    //////////////  Actions liées aux LIEUX  \\\\\\\\\\\\\\


    /**
     * Fiche d'un lieu
     * @Route("/lieu/{slug}", name="BNSAppGPSBundle_back_place_sheet")
     * @Rights("GPS_ACCESS_BACK")
     * @Template()
     */
    public function sheetPlaceAction($slug)
    {
        $gpsPlace = GpsPlaceQuery::create()->findOneBySlug($slug);
        $this->checkRights($gpsPlace);
        return array('place' => $gpsPlace);
    }

    /**
     * Index du back GPS : liste des lieux du groupe en cours
     * @Route("/nouveau-lieu", name="BNSAppGPSBundle_back_new_place")
     * @Rights("GPS_ACCESS_BACK")
     * @Template()
     */
    public function newPlaceAction()
    {
        return $this->processPlaceForm(new GpsPlace(),$this->getRequest());
    }

    /**
     * Edition d'un lieu
     * @Route("/lieu-editer/{slug}", name="BNSAppGPSBundle_back_edit_place", options={"expose"=true})
     * @Rights("GPS_ACCESS_BACK")
     * @Template("BNSAppGPSBundle:Back:newPlace.html.twig")
     */
    public function editPlaceAction($slug)
    {
        if (!$this->hasFeature('gps_place')) {
            throw $this->createAccessDeniedException();
        }
        $gpsPlace = GpsPlaceQuery::create()->findOneBySlug($slug);
        $this->checkRights($gpsPlace);
        return $this->processPlaceForm($gpsPlace, $this->getRequest());
    }

    /**
     * Submit d'un lieu
     * @Route("/lieu-submit", name="BNSAppGPSBundle_back_edit_place_submit", options={"expose"=true})
     * @Rights("GPS_ACCESS_BACK")
     * @Template("BNSAppGPSBundle:Back:newPlace.html.twig")
     */
    public function submitEditPlaceAction()
    {

        if (!$this->hasFeature('gps_place')) {
            throw $this->createAccessDeniedException();
        }
        $request =  $this->getRequest();
        $datas = $request->get('gps_place_form');

        if($datas['id'] != ""){
            $place = GpsPlaceQuery::create()->findOneById($datas['id']);
            $this->checkRights($place);
        }else{
            $place = new GpsPlace();
            return $this->processPlaceForm($place->setIsActive(true),$request,'CREATE');
        }
        $place->setIsActive(true);
        $message = $this->get('translator')->trans('PLACE_WAS_MODIFY', array(), 'GPS');

        $this->get('session')->getFlashBag()->add('success', $message);
        return $this->processPlaceForm($place,$request);
    }

    protected function processPlaceForm($object, $request, $action = null, $options = [])
    {
        $rightManager = $this->get('bns.right_manager');
        $currentGroupId = $rightManager->getCurrentGroupId();

        $options['group_id'] = $currentGroupId;

        $form = $this->createForm(new GpsPlaceType($this->get('router')->generate('BNSAppGPSBundle_back_category_create')), $object, $options );

        if ($request->getMethod() == 'POST') {
            $form->bind($request);
            if ($form->isValid()) {
                //Création du lieu et settage des geecords
                $gs = $this->get('bns.geocoords_manager');
                $gs->setGeoCoords($object);
                $object->save();

                //statistic action
                if(null != $action && 'CREATE' == $action) {
                    $this->get("stat.gps")->newPlace();
                    $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('FLASH_PLACE_CREATE_SUCCESS', [], 'GPS'));
                }

                return $this->redirect($this->generateUrl('BNSAppGPSBundle_back'));
            }
        }

        return array(
            'form' => $form->createView(),
            'show_place_button' => $object->getAddress() != null,
            'is_edit' => !$object->isNew(),
            'place' => $object,
            /*'categories' => GpsCategoryQuery::create()->orderByOrder()->findByGroupId($currentGroupId)*/
        );
    }

    /**
     * Suppression d'un lieu
     * @Route("/lieu-supprimer/{slug}", name="BNSAppGPSBundle_back_delete_place", options={"expose"=true})
     * @Rights("GPS_ACCESS_BACK")
     * @Template()
     */
    public function deletePlaceAction($slug)
    {
        $place = GpsPlaceQuery::create()->findOneBySlug($slug);
        $this->checkRights($place);
        $place->delete();
        $message = $this->get('translator')->trans('PLACE_WAS_DELETE', array(), 'GPS');

        $this->get('session')->getFlashBag()->add('success', $message);
        return $this->redirect($this->generateUrl('BNSAppGPSBundle_back'));
    }



    /**
     * Toggle d'activation des lieux
     * @Route("/bascule-lieu", name="BNSAppGPSBundle_back_place_toggle_activation", options={"expose"=true})
     * @Rights("GPS_ACCESS_BACK")
     * @Template("BNSAppGPSBundle:Back:blockPlace.html.twig")
     */
    public function placeToggleActivationAction()
    {
        $request = $this->getRequest();
        $placeId = $request->get('place_id');
        $place = GpsPlaceQuery::create()->findOneById($placeId);
        $this->checkRights($place);
        $place->toggleActivation();
        return array('place' => $place);
    }

    /**
     * Visualisation de la carte coté back
     * @Route("/voir-carte", name="BNSAppGPSBundle_back_place_show_map", options={"expose"=true})
     * @Rights("GPS_ACCESS_BACK")
     * @Template()
     */
    public function placeShowMapAction()
    {
        if($this->getRequest()->get('place_id')){
            $place = GpsPlaceQuery::create()->findOneById($this->getRequest()->get('place_id'));
            $this->checkRights($place);
        }elseif($this->getRequest()->get('address')){
            $place = new GpsPlace();
            $place->setAddress($this->getRequest()->get('address'));
            $gs = $this->get('bns.geocoords_manager');
            $gs->setGeoCoords($place);
        }
        $map = $this->get('bns.front_map');
        $map->setWidth(880);
        $map->setHeight(300);
        $map->setAutoZoom(false);
        return array('place' => $place, 'map' => $map);
    }

    //////////////  Actions liées aux CATEGORIES  \\\\\\\\\\\\\\

    /**
     * Page des categories
     * @Route("/categories", name="BNSAppGPSBundle_back_categories", options={"expose"=true})
     * @Rights("GPS_ACCESS_BACK")
     * @Template()
     */
    public function categoriesAction()
    {
        $rm = $this->get('bns.right_manager');
        $groupId = $rm->getCurrentGroupId();
        $categories = GpsCategoryQuery::create()->orderByOrder()->findByGroupId($groupId);
        return array('categories' => $categories);
    }

    /**
     * Sélection d'une catégorie
     * @Route("/categorie-selection", name="BNSAppGPSBundle_back_category_select", options={"expose"=true})
     * @Rights("GPS_ACCESS_BACK")
     * @Template("BNSAppGPSBundle:Back:blockCategorySidebar.html.twig")
     */
    public function categorySelectAction()
    {
        $category_id = $this->getRequest()->get('category_id');
        $category = GpsCategoryQuery::create()->findOneById($category_id);
        $this->checkRights(null,$category);
        $current = $this->get('session')->get('gps_back_selected_category');
        if($current && in_array($category_id,$current)){
            unset($current[array_search($category_id,$current)]);
        }else{
            if(!$current)
                $current = array();
            $current[] = $category_id;
        }
        $this->get('session')->set('gps_back_selected_category',$current);

        return $this->forward('BNSAppGPSBundle:Back:placesList',array('forIndex' => false));
    }

    /**
     * Tri des catégories
     * @Route("/categorie-tri", name="BNSAppGPSBundle_back_category_order", options={"expose"=true})
     * @Rights("GPS_ACCESS_BACK")
     */
    public function categoryOrderAction()
    {

        foreach($this->getRequest()->get('ordered') as $key => $order){
            $category = GpsCategoryQuery::create()->findOneById($order);
            $this->checkRights(null, $category);
            $category->setOrder($key);
            $category->save();
        }
        return new Response(json_encode(array('ok')));
    }


    /**
     * Toggle d'activation des categories de lieux
     * @Route("/bascule-categorie", name="BNSAppGPSBundle_back_category_toggle_activation", options={"expose"=true})
     * @Rights("GPS_ACCESS_BACK")
     * @Template("BNSAppGPSBundle:Back:blockCategorySidebar.html.twig")
     */
    public function categoryToggleActivationAction()
    {
        $request = $this->getRequest();
        $categoryId = $request->get('category_id');
        $category = GpsCategoryQuery::create()->findOneById($categoryId);
        $this->checkRights(null,$category);
        $category->toggleActivation();
        return new Response($categoryId);
    }

    /**
     * Création d'une catégorie
     * @Route("/ajouter-categorie/{from}", name="BNSAppGPSBundle_back_category_create", options={"expose"=true})
     * @Rights("GPS_ACCESS_BACK")
     */
    public function categoryCreateAction($from = '')
    {
        if (!$this->hasFeature('gps_categories')){
            throw $this->createAccessDeniedException();
        }
        $request = $this->getRequest();
        $rm = $this->get('bns.right_manager');
        $groupId = $rm->getCurrentGroupId();
        $title = $request->get('subject_title', $request->get('title'));

        if(trim($title) != "")
        {
            $category = new GpsCategory();
            $category->setLabel($title);
            $category->setGroupId($groupId);
            $category->setIsActive(true);
            $biggest = GpsCategoryQuery::create()->orderByOrder('desc')->findOneByGroupId($groupId);
            if($biggest)
                $category->setOrder($biggest->getOrder() + 1);
            else
                $category->setOrder(1);
            $category->save();
        }
        if($from == 'modal'){
            return $this->render('BNSAppGPSBundle:Back:blockRowCategory.html.twig', array('category' => $category));
        }elseif($from == 'sidebar'){
            if($request->get('type') == "for_form")
                return $this->render('BNSAppGPSBundle:Back:blockCategoryRowSidebarForm.html.twig', array('category' => $category));
            else
                return $this->render('BNSAppGPSBundle:Back:blockCategoryRowSidebar.html.twig', array('category' => $category));
        } else {
            if (isset($category)) {
                $data = $this->get('serializer')->serialize([
                    'id' => ''.$category->getId(),
                    'title' => $category->getLabel(),
                ], 'json');

                return new Response($data);
            } else {
                return new Response(null, 400);
            }

        }
    }

    /**
     * Edition d'une catégorie
     * @Route("/editer-categorie", name="BNSAppGPSBundle_back_category_edit", options={"expose"=true})
     * @Rights("GPS_ACCESS_BACK")
     * @Template("BNSAppGPSBundle:Back:blockRowCategory.html.twig")
     */
    public function categoryEditAction()
    {
        if(!$this->hasFeature('gps_categories')) {
            throw $this->createAccessDeniedException();
        }
        $request = $this->getRequest();
        $rm = $this->get('bns.right_manager');
        $groupId = $rm->getCurrentGroupId();
        $category = GpsCategoryQuery::create()->findOneById($request->get('id'));
        $this->checkRights(null,$category);

        if(trim($request->get('subject_title')) != "" && $category->getGroupId() == $groupId)
        {
            $category->setLabel($request->get('subject_title'));
            $category->save();
        }
        return new Response();
    }

    /**
     * Suppression d'une categorie
     * @Route("/categorie-supprimer", name="BNSAppGPSBundle_back_category_delete", options={"expose"=true})
     * @Rights("GPS_ACCESS_BACK")
     * @Template()
     */
    public function deleteCategoryAction()
    {
        $category = GpsCategoryQuery::create()->findOneById($this->getRequest()->get('id'));
        $this->checkRights(null,$category);

        if($category->getId() == $this->get('session')->get('gps_back_selected_category'))
            $this->get('session')->remove('gps_back_selected_category');

        $category->delete();
        return $this->redirect($this->generateUrl('BNSAppGPSBundle_back'));
    }

    /**
     * Déplacement d'un lieu
     * @Route("/lieu-deplacer", name="BNSAppGPSBundle_back_move_place",options={"expose"=true})
     * @Rights("GPS_ACCESS_BACK")
     * @Template()
     */
    public function movePlaceAction()
    {
        $category = GpsCategoryQuery::create()->findOneById($this->getRequest()->get('categoryId'));
        $this->checkRights(null,$category);
        $place = GpsPlaceQuery::create()->findOneById($this->getRequest()->get('placeId'));
        $this->checkRights($place);
        $place->move($category);
        return $this->forward('BNSAppGPSBundle:Back:placesList');
    }
}
