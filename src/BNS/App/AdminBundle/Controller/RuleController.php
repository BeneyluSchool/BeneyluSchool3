<?php

namespace BNS\App\AdminBundle\Controller;

//Rule
use BNS\App\CoreBundle\Form\Type\RuleType;
//SF
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Annotation\Rights;

/**
 * @Route("/regle")
 */

class RuleController extends Controller
{
	/**
	 * Formulaire d'action sur une règle
	 * 
	 * @Route("/gestion/{groupId}", name="BNSAppAdminBundle_rule_manage" , defaults={"groupId" = "creation" })
     * @Template()
	 * @Rights("ADMIN_ACCESS")
	 */
    public function manageAction($groupId)
    {
		$form = $this->createForm(new RuleType());
		if($groupId != "creation")
			$form->setData(array('rule_where_group_id' => $groupId));
		
		if ($this->getRequest()->isMethod('POST')){
			$form->bindRequest($this->getRequest());
			if ($form->isValid()){
				$params = $form->getData();
				$ruleManager = $this->get('bns.rule_manager');
				//On manipule le tableau pour correspondre à l'API
								
				$ruleWho = array(
					'domain_id'			=> $this->container->getParameter('domain_id'),
					'group_parent_id'	=> $params['rule_where_group_id'],
					'group_type_id'		=> $params['who_group_id']
				);
				
				$ruleWhere = array(
					'group_id'		=> $params['rule_where_group_id'],
					'belongs'		=> $params['rule_where_belongs']
				);
				
				if(trim($params['rule_where_group_type_id']) != ""){
					$ruleWhere['group_type_id'] = $params['rule_where_group_type_id'];
				}
				
				$rule = array(
					'state' => $params['state'],
					'rank_unique_name' => $params['rank_unique_name'],
					'who_group' => $ruleWho,
					'rule_where' => $ruleWhere
				);
				
				
				
				$ruleManager->createRule($rule);
				$group = GroupQuery::create()->findOneById($params['rule_where_group_id']);
				return $this->redirect($this->generateUrl("BNSAppAdminBundle_group_sheet_rules",array('slug' => $group->getSlug())));
			}
		}		
		return array('form' => $form->createView());
	}
}