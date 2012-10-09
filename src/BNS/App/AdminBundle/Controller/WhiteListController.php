<?php

namespace BNS\App\AdminBundle\Controller;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use BNS\App\CoreBundle\Model\GroupQuery;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use BNS\App\CoreBundle\Annotation\Rights;
use Criteria;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;


/**
 * Controller pour toutes les actions liées à la manipulation des templates de mail
 * @Route("/search")
 */

class WhiteListController extends Controller
{
	/**
	 * Liste des environnements pour choix
	 * @Route("/", name="BNSAppAdminBundle_white_list")
	 * @Template()
	 * @Rights("ADMIN_ACCESS")
	 */
    public function indexAction()
    {
		return array('groups' => GroupQuery::create()->filterByGroupTypeId(1)->find());
    }
	
	/**
	 * Edition d'un template d'Email : pas de création
	 * @Route("/editer/{groupId}", name="BNSAppAdminBundle_white_list_edit")
	 * @Template()
	 * @Rights("ADMIN_ACCESS")
	 */
    public function editAction($groupId)
    {
		$group = GroupQuery::create()->findPk($groupId);
		if($group->getGroupType()->getType() != "ENVIRONMENT"){
			throw new \Exception("Uniquement valable sur les envirronnements");
		}
		$whiteList = unserialize($group->getAttribute('WHITE_LIST'));
		
		if($this->getRequest()->isMethod('POST')){
			$new_white_list = array();
			foreach($this->getRequest()->get('url') as $url){
				if(trim($url) != "")
					$new_white_list[] = $url;
			}
			$group->setAttribute('WHITE_LIST',  serialize($new_white_list));
			$whiteList = $new_white_list;
		}
		
		return array('group' => $group,'white_list' => $whiteList);
    }
}