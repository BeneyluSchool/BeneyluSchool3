<?php

namespace BNS\App\ResourceBundle\Model;

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\ResourceBundle\BNSResourceManager;
use BNS\App\ResourceBundle\Model\om\BaseResourceQuery;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ResourceQuery extends BaseResourceQuery
{
	/*
	 * Fonction principale de récupération de ressources
	 */
	public static function getResources($params = null)
	{
		if ($params == null) {
			throw new NotFoundHttpException("Can't Find resources, please provide params");
		}
		
		// Limite d'affichage
		if (!isset($params['limit'])) {
			$limit = 10;
		}
		else {
			$limit = $params['limit'];
		}
		
		// Page affichée
		$page = $params['page'];

		$query = self::create('r')
			->join('r.ResourceLinkUser rlu', \Criteria::LEFT_JOIN)
			->join('rlu.ResourceLabelUser rlau', \Criteria::LEFT_JOIN)
			->join('r.ResourceLinkGroup rlg', \Criteria::LEFT_JOIN)
			->join('rlg.ResourceLabelGroup rlag', \Criteria::LEFT_JOIN)
		;

		if (isset($params['query'])) {
			$query->filterByLabel('%' . $params['query'] . '%');
		}

		if (isset($params['scope']) && isset($params['scope']['user']) && isset($params['scope']['group'])) {
			if (isset($params['scope']['group'])) {
				$query->condition('groupScope', 'rlag.GroupId = ?', $params['scope']['group']);
			}

			if (isset($params['scope']['user']) && is_array($params['scope']['user'])) {
				$query->condition('userScope', 'rlau.UserId IN ?', $params['scope']['user']);
			}

			$query->where(array('userScope', 'groupScope'), \Criteria::LOGICAL_OR);
		}

		if (isset($params['current_label'])) {
			if ($params['current_label'] != null) {
				$current_label = $params['current_label'];

				if (get_class($current_label) == 'BNS\App\ResourceBundle\Model\ResourceLabelUser') {
					$query->where('rlau.Id = ?', $current_label->getId());
				}
				elseif (get_class($current_label) == 'BNS\App\ResourceBundle\Model\ResourceLabelGroup') {
					$query->where('rlag.Id = ?', $current_label->getId());
				}
			}
		}
		
		if (isset($params['filters'])) {
			if (count($params['filters']) > 0) {
				$query->where('r.TypeUniqueName IN ?', $params['filters']);
			}
		}

		if (isset($params['favorite_filter']) && isset($params['user_id'])) {
			if ($params['favorite_filter'] == true) {
				$query->join('ResourceFavorites rf');
				$query->where('rf.UserId = ?', $params['user_id']);
			}
		}

		if ($params['type'] == 'garbage') {
			// Si corbeille filtre sur l'utilisateur en cours
			$query->where('rlau.UserId = ?', BNSAccess::getUser()->getId());
			$query->orWhere('rlag.GroupId = ?', $params['scope']['group']);
			$query->where('rlu.Status = ?', 0)
				->orWhere('rlg.Status = ?', 0)
			;
		}
		else {
			$query->where('r.StatusDeletion = ?', BNSResourceManager::STATUS_ACTIVE)
				->where('rlu.Status = ?', 1)
				->orWhere('rlg.Status = ?', 1)
			;
			
			// Is private process
			if (!isset($params['can_manage']) || isset($params['can_manage']) && $params['can_manage'] === false) {
				$query->privateProcess(BNSAccess::getUser()->getId());
			}
		}

		// Si navigation dans ressources, on filtre les privées ou non
		if (isset($params['with_privates'])) {
			if ($params['with_privates'] === false) {
				$query->where('r.IsPrivate = ?', false);
			}
		}

		if (isset($params['sort'])) {
			switch ($params['sort']) {
				case "chrono":
					$query->orderByCreatedAt('DESC');
				break;
				case "alpha":
					$query->orderByLabel('ASC');
				break;
			}
		}
		else {
			// Tri par défaut
			$query->orderByCreatedAt('desc');
		}

		// C'est une recherche
		if ($params['type'] == 'search') {
			$query->groupBy('r.Id');
		}

		// Sinon on pagine
		return $query->paginate($page, $limit);
	}
	
	/**
	 * @param int $userId
	 * 
	 * @return \BNS\App\ResourceBundle\Model\om\BaseResourceQuery
	 */
	public function privateProcess($userId)
	{
		$this->condition('my_folders', 'r.UserId = ?', $userId);
		$this->condition('other_folders', 'r.UserId != ?',  $userId);
		$this->condition('public_resources', 'r.IsPrivate = ?', false);

		$this->combine(array('other_folders', 'public_resources'), \Criteria::LOGICAL_AND, 'foreign_folders');
		$this->where(array('foreign_folders', 'my_folders'), \Criteria::LOGICAL_OR);
		
		return $this;
	}
}