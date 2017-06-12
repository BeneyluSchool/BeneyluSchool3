<?php

namespace BNS\App\GroupBundle\Controller;

use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\GroupBundle\Controller\BackController;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;


/**
 * @Route("/gestion/medialandes")
 */

class BackMedialandesController extends CommonController
{

    protected function hasMedialandes()
    {
        $gm = $this->get('bns.group_manager');
        $env = $gm->getEnvironment();
        $value = true;
        $authorisedEnv = $this->container->getParameter('authorised.medialandes.env');
        if(!in_array($env->getId(),$authorisedEnv))
        {
            $value = false;
        }
        return $this->container->hasParameter('has_medialandes') && $this->container->getParameter('has_medialandes') == true && $value;
    }

	/**
	 * @Route("/", name="BNSAppGroupBundle_back_medialandes_index")
	 * @Template()
	 * @Rights("GROUP_ACCESS_BACK")
	 */
	public function indexAction()
	{
		$this->get('bns.right_manager')->forbidIf(!$this->get('bns.right_manager')->hasMedialandes());

		$group = $this->get('bns.right_manager')->getCurrentGroup();

        $medialandesList = unserialize($group->getAttribute('MEDIALANDES_LIST'));

		if($this->getRequest()->isMethod('POST')){
			$new_medialandes_list = array();
            if(is_array($this->getRequest()->get('uai')))
            {
                foreach($this->getRequest()->get('uai') as $uai){
                    if(trim($uai) != "")
                    {
                        $new_medialandes_list[] = $uai;
                    }
                }
            }
			$group->setAttribute('MEDIALANDES_LIST',  serialize($new_medialandes_list));
            $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('FLASH_LIST_SAVE_SUCCESS', array(), 'GROUP'));
			$medialandesList = $new_medialandes_list;
		}
		return array(
            'group' => $group,
            'medialandes_list' => $medialandesList,
        );
	}
}
