<?php

namespace BNS\App\CoreBundle\Rule;
use BNS\App\StatisticsBundle\Model\GroupQuery;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * @author Eric Chau
 * Gestion des règles dans BNS3 et des relations avec la centrale d'Auth
 */
class BNSRuleManager
{	
	protected $api;
	protected $domainId;
    protected $groupManager;

	public function __construct($groupManager, $api, $domainId)
	{
        $this->groupManager = $groupManager;
		$this->api = $api;
		$this->domainId = $domainId;
	}

    public function findRules($rankUniqueName)
    {
        $route = array(
            'what-rank_unique_name' => $rankUniqueName
        );
        return $this->api->send(
            "rule_search",
            array('route' => $route),
            false
        );

    }


    public function getRules($groupId, $type = "all", $useCache = true)
    {
        if(!isset($this->rules)){
            $route = array(
                'where-group_id' => $groupId
            );
            $this->rules = $this->api->send(
                "rule_search",
                array('route' => $route),
                $useCache
            );
        }

        $group = GroupQuery::create()->findPk($groupId);
        $this->groupManager->setGroup($group);

        $rules = $this->rules;

        switch($type){
            case 'all':
                //all = rule_where.group_id => group.id
                return $rules;
                break;
            case 'mine':
                //Mine = celles du groupe uniquement = rule_where.group_type_id == NULL
                $returnedRules = array();
                foreach($rules as $rule){
                    if(!isset($rule['rule_where']["group_type_id"]) || $rule['rule_where']["group_type_id"] == null){
                        $returnedRules[] = $rule;
                    }
                }
                return $returnedRules;
                break;
            case 'delegated':
                //delegated = celles dédiées aux sous groupes du groupe = rule_where.group_type_id != NULL
                $returnedRules = array();

                foreach($rules as $rule){
                    if(isset($rule['rule_where']["group_type_id"]) && $rule['rule_where']["group_type_id"] != null){
                        $returnedRules[] = $rule;
                    }
                }
                return $returnedRules;
                break;
            case 'rooted':
                //rooted = celles récupérées d'autres groupes (parents) = pour tous les parents où rule_where.group_type_id != thid.groupTypeId
                $returnedRules = array();
                $myGroupTypeId = $group->getGroupTypeId();
                foreach($this->groupManager->getAncestors() as $parentGroup){
                    $this->groupManager->setGroup($parentGroup);
                    $parentRules = $this->getRules($parentGroup->getId());
                    foreach($parentRules as $parentRule){
                        if(isset($parentRule['rule_where']["group_type_id"]) && $parentRule['rule_where']['group_type_id'] == $myGroupTypeId){
                            $returnedRules[] = $parentRule;
                        }
                    }
                }
                return $returnedRules;
                break;
        }
    }

	/*
	 * Création d'une règle
	 */
	public function createRule($params)
	{
		if (!isset($params['who_group']) && !isset($params['rule_where']) && !isset($params['rank_unique_name']) && !isset($params['state']))
		{
			throw new HttpException(500, 'Parameters missing, you have to provide 4 parameters: rule_what, who_group, rule_where, rank_unique_name and state');
		}
		$response = $this->api->send('rule_create', array(
			'values' => $params,
			'route'	 => array(),
		));
	}
	
	/*
	 * Suppression d'une règle
	 */
	public function deleteRule($id = null)
	{
		if ($id == null)
		{
			throw new HttpException(500, 'Parameter missing, you have to provide rule id');
		}
		return $this->api->send('rule_delete',array('route' => array('id' => $id)));
	}
	
	/*
	 * Modification d'une règle
	 */
	public function editRule($params)
	{
		if (!isset($params['id']) && !isset($params['state']))
		{
			throw new HttpException(500, 'Parameters missing, you have to provide 2 parameters: id and state');
		}
		return $this->api->send('rule_patch',array('route' => array('id' => $params['id']),'values' => array('state' => $params['state'])));
	}

    public function resetRules($groupId)
    {
        $rules = $this->getRules($groupId, 'all',false);
        foreach($rules as $rule)
        {
            $this->deleteRule($rule['id']);
        }
    }
	
}