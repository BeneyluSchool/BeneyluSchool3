<?php

namespace BNS\App\MiniSiteBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use BNS\App\CoreBundle\Model\MiniSitePeer;
use BNS\App\CoreBundle\Model\MiniSiteQuery;
use BNS\App\CoreBundle\Model\MiniSitePagePeer;
use BNS\App\CoreBundle\Model\MiniSite;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
abstract class AbstractMiniSiteController extends Controller
{
	/**
	 * @var MiniSite 
	 */
	protected $miniSite;
	
	/**
	 * @var \PropelPager 
	 */
	protected $pager;
	
	/**
	 * @return MiniSite 
	 */
	protected function getMiniSite(MiniSiteQuery $customQuery = null)
	{
		if (!isset($this->miniSite)) {
			$context = $this->get('bns.right_manager')->getContext();
			$query = MiniSiteQuery::create()
				->joinWith('MiniSitePage')
				->joinWith('MiniSitePage.MiniSitePageText', \Criteria::LEFT_JOIN)
				->joinWith('MiniSitePageText.User', \Criteria::LEFT_JOIN) // Author
				->joinWith('User.Profile', \Criteria::LEFT_JOIN)
				->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
				->add(MiniSitePeer::GROUP_ID, $context['id'])
				->addAscendingOrderByColumn(MiniSitePagePeer::RANK)
			;

			if (null != $customQuery) {
				// $query->mergeWith($customQuery) doesn't work...
				$query = $customQuery;
			}

			$results = $query->find();
			if (!isset($results[0])) {
				throw new NotFoundHttpException('Minisite for group id : ' . $context['id'] . ' is not found !');
			}
			
			$this->miniSite = $results[0];
		}
		
		return $this->miniSite;
	}
	
	/**
	 * @param string $slug
	 * @param int $numberPage
	 * 
	 * @throws NotFoundHttpException
	 */
	protected function findPageBySlug($slug)
	{
		$miniSite = $this->getMiniSite();
		$page = $miniSite->findPageBySlug($slug);
		
		if ($page === false) {
			throw new NotFoundHttpException('The page with slug : ' . $slug . ' is NOT found !');
		}
		
		return $page;
	}
	
	/**
	 * @param string $sessionName
	 * @param mixed $filter
	 */
	protected function manageFilters($sessionName, $filter)
	{
		$session = $this->getRequest()->getSession();
		if ($session->has($sessionName)) {
			$filters = $session->get($sessionName);
			// Si le filtre existe déjà, on le supprime
			if (isset($filters[$filter])) {
				unset($filters[$filter]);
			}
			// Sinon on l'ajoute
			else {
				$filters[$filter] = true;
			}
		}
		else {
			$filters = array($filter => true);
		}
		
		$session->set($sessionName, $filters);
	}
	
	/**
	 * @param null $groupManager
	 * 
	 * @return \BNS\App\CoreBundle\Group\BNSGroupManager 
	 * 
	 * @throws \RuntimeException
	 */
	protected function getEditorSubGroupManager()
	{
		$currentGroup = $this->get('bns.right_manager')->getCurrentGroup();
		$groupManager = $this->get('bns.group_manager');
		$groupManager->setGroup($currentGroup);
		
		$subGroups = $groupManager->getSubgroupsByGroupType('EDITOR_MINISITE');
		if (!isset($subGroups[0])) {
			throw new \RuntimeException('There is NO sub group in group id : ' . $currentGroup->getId() . ' !');
		}
		
		$groupManager->setGroup($subGroups[0]);
		
		return $groupManager;
	}
	
	/**
	 * @param MiniSite $miniSite
	 * 
	 * @return boolean 
	 */
	protected function isPublic(MiniSite $miniSite = null)
	{
		if (null == $miniSite) {
			$miniSite = $this->getMiniSite();
		}
		
		return $this->get('bns.right_manager')->hasRight(MiniSite::PERMISSION_ACCESS) || ($this->get('bns.group_manager')->getAttribute('MINISITE_ALLOW_PUBLIC', false) && $miniSite->isPublic());
	}
}