<?php

namespace BNS\App\GroupBundle\Controller;

use BNS\App\AdminBundle\Form\Type\AddToGroupType;
use BNS\App\ClassroomBundle\Form\Type\NewUserInClassroomType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use BNS\App\CoreBundle\Annotation\Rights;


/**
 * @Route("/gestion/liste-blanche")
 */

class WhiteListBackController extends Controller
{
	
	
	protected function isEnv()
	{
		return $this->get('bns.right_manager')->getCurrentGroupType() == 'ENVIRONMENT';
	}
	
	
	/**
	 * @Route("/", name="BNSAppGroupBundle_back_whitelist_index")
	 * @Template()
	 * @Rights("GROUP_ACCESS_BACK")
	 */
	public function indexAction()
	{	
		$this->get('bns.right_manager')->forbidIf(!$this->isEnv());
		
		$group = $this->get('bns.right_manager')->getCurrentGroup();
		
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
