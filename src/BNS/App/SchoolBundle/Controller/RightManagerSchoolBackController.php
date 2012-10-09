<?php

namespace BNS\App\SchoolBundle\Controller;

use BNS\App\CoreBundle\Model\Module;

use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupTypePeer;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/admin")
 *
 */
class RightManagerSchoolBackController extends Controller
{
    /**
     * @Route("/{slug}/right-manager", name="BNSAppSchoolBundle_right_manager_index")
     * @Template()
     */
    public function rightManagerIndexAction($slug)
    {
        $school = $this->getSchoolBySlug($slug);
        
    	return array(
    		'school' => $school,
    	);
    }
	
    /**
     * Permet d'enlever/donner une permission à un rôle d'utilisateur
     * Si la permission est déjà possédé par le rôle en question alors l'appel de cette méthode enlèvera la permission;
     * dans le cas contraire, la permission sera alors accordée
     * TODO: à compléter lorsque l'on pourra s'appuyer sur les droits, et plus précisément les id des rôles et des permissions
     * 
     * @Route("/{slug}/right-manager/role/{role}/switch-permission-{permission}", name="BNSAppSchoolBundle_right_manager_switch_permission", options={"expose"=true})
     * @param String $slug
     * @param int $role
     * @param int $permission
     */
    public function permissionSwitchAJAXAction($slug, $role, $permission)
    {
    	// AJAX ??
    	if (!$this->getRequest()->isXmlHttpRequest())
    	{
    		throw new NotFoundHttpException();
    	}
    	$school = $this->getSchoolBySlug($slug);
    	
    	/*
    	 *  TODO: utiliser l'API pour gérer les droits du groupe $slug pour attribuer/retirer la permission
    	 *  $permission au rôle $role
    	 */
    	return new Response(json_encode((rand(0, 1) == 0? true : false)));
    }
    
    /**
	 * Permet d'enlever/donner l'accès à un module pour un rôle d'utilisateur donné
     * Si le module est déjà disponible pour le rôle en question alors l'appel de cette méthode rendra le module inacessible 
     * pour le rôle renseigné en paramètre; dans le cas contraire, le module sera rendu disponible
     * TODO: à compléter lorsque l'on pourra s'appuyer sur les droits, et plus précisément les id des rôles et des modules
     * 
     * @Route("/{slug}/right-manager/role/{role}/switch-module-{module}", name="BNSAppSchoolBundle_right_manager_switch_module", options={"expose"=true})
     * 
     * @param unknown_type $slug
     * @param unknown_type $role
     * @param unknown_type $module
     * @throws NotFoundHttpException
     */
    public function moduleSwitchAJAXAction($slug, $role, $module)
    {
    	// AJAX ??
    	if (!$this->getRequest()->isXmlHttpRequest())
    	{
    		throw new NotFoundHttpException();
    	}
    	$school = $this->getSchoolBySlug($slug);
    	
    	/*
    	 *  TODO: utiliser l'API pour gérer les droits du groupe $slug pour autoriser/interdire l'utilisation
    	 *  du module $module au utilisateur ayant le rôle $role
    	*/
    	return new Response(json_encode((rand(0, 1) == 0? true : false)));
    }
    
    /**
     * Récupérer l'objet de type groupe associé au slug $slug fourni en paramètre; le groupe doit être du type School
     *
     * @param String $slug slug à partir duquel on souhaite identifier et récupérer un objet de type Group
     * @throws NotFoundHttpException si le slug fourni n'est pas reconnu, l'exception NotFoundHttpException
     * @throws Exception si le groupe associé au slug fourni n'est pas une école alors une exception est levé
     * @return Group est un objet du type Group qui correspond à l'objet que l'on a voulu récupérer à partir du slug
     */
    private function getSchoolBySlug($slug)
    {
    	$group = GroupQuery::create()
	    	->joinWith('GroupType')
	    	->joinWith('GroupType.GroupTypeI18n')
    	->findOneBySlug($slug);
    	
    	if (null == $group)
    	{
    		throw new NotFoundHttpException('The group with the slug ' . $slug . ' does not exist!');
    	}
    	
    	if ('SCHOOL' != $group->getGroupType()->getType())
    	{
    		throw new Exception('The group must be a school!');
    	}
    	
    	return $group;
    }
}
