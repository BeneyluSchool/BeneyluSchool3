<?php

namespace BNS\App\CoreBundle\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * @author Eric Chau
 * Gestion des règles dans BNS3 et des relations avec la centrale d'Auth
 */
class BNSRuleManager
{	
	protected $api;
	protected $domain_id;

	public function __construct($api,$domain_id)
	{
		$this->api = $api;
		$this->domain_id = $domain_id;
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
	
}