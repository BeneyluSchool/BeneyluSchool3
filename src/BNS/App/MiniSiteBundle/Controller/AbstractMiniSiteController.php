<?php

namespace BNS\App\MiniSiteBundle\Controller;

use \BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Controller\BaseController;
use \BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\Model\User;
use \BNS\App\MiniSiteBundle\Model\MiniSite;
use \BNS\App\MiniSiteBundle\Model\MiniSitePage;
use \BNS\App\MiniSiteBundle\Model\MiniSitePageText;
use \BNS\App\MiniSiteBundle\Model\MiniSitePeer;
use \BNS\App\MiniSiteBundle\Model\MiniSiteQuery;
use \Symfony\Bundle\FrameworkBundle\Controller\Controller;
use \Symfony\Component\HttpFoundation\Response;
use \Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
abstract class AbstractMiniSiteController extends BaseController
{
	/**
	 * @var MiniSite
	 */
	protected $miniSite;

	/**
	 * @return MiniSite
	 */
	protected function getMiniSite(MiniSiteQuery $customQuery = null)
	{
		if (!isset($this->miniSite)) {
			if (null != $customQuery) {
				// $query->mergeWith($customQuery) doesn't work...
				$query = $customQuery;
			}
			else {
				$query = MiniSiteQuery::create('ms')
					->joinWith('ms.MiniSitePage msp')
					->joinWith('msp.MiniSitePageEditor mpse', \Criteria::LEFT_JOIN)
					->joinWith('mpse.User u', \Criteria::LEFT_JOIN)
					->joinWith('msp.MiniSitePageText mspt', \Criteria::LEFT_JOIN)
					->where('ms.GroupId = ?', $context = $this->get('bns.right_manager')->getCurrentGroupId())
					->orderBy('msp.Rank')
				;
			}

			$results = $query->find();
			if (!isset($results[0])) {
				if (BNSAccess::isConnectedUser()) {
					$context = $this->get('bns.right_manager')->getContext();

					// Create minisite if not exists
					if (MiniSiteQuery::create('ms')
							->where('ms.GroupId = ?', $context['id'])
						->count() == 0) {
						$group = $this->get('bns.right_manager')->getCurrentGroup();
						$this->miniSite = MiniSitePeer::create(array(
							'group_id' => $group->getId(),
							'label'	   => $group->getLabel()
						),
							$this->get('translator'),
							$this->get('bns.group_manager')
						);
					}
					else {
						throw new NotFoundHttpException('No minisite found for this query parameters, please see logs.');
					}
				}
				else {
					return null;
				}
			}
			else {
				$this->miniSite = $results[0];
			}
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
	 * @return BNSGroupManager
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
	 * @param boolean $isFront
	 *
	 * @return Response
	 */
	public function redirectHome($isFront = false)
	{
		$route = 'BNSAppMiniSiteBundle_back';
		if ($isFront) {
			$route = 'BNSAppMiniSiteBundle_front';
		}

		return $this->redirect($this->generateUrl($route));
	}

    /**
     * @return Response
     */
    public function redirectNotEditor()
    {
        return $this->redirect($this->generateUrl('BNSAppMiniSiteBundle_back_not_editor'));
    }

    /**
	 * @param MiniSitePage $miniSite
	 */
	protected function addViewToPage($page)
	{
		$page->addView();
		$page->save();
	}

	/**
	 * @param type $user
	 * @param type $page
	 *
	 * @return boolean
	 */
	protected function isEditorPage($user, $page)
	{
		if ($this->get('bns.right_manager')->hasRight('MINISITE_ADMINISTRATION') || $page->isEditor($user)) {
			return true;
		}

		return false;
	}

	/**
	 * @param MiniSitePage $page
	 */
	protected function createPageText($page)
	{
		// Creating new page text
		$pageText = new MiniSitePageText();
		$pageText->setAuthorId($this->getUser()->getId());
		$pageText->setLastModificationAuthorId($this->getUser()->getId());
		$pageText->setPageId($page->getId());
		$pageText->save();
		$page->setMiniSitePageText($pageText);
	}

	/**
	 * @param MiniSitePage $page
	 * @return Array|User[]
	 */
	protected function getPageAudience(MiniSitePage $page)
	{
		$groupManager = $this->get('bns.group_manager')->setGroup($page->getMiniSite()->getGroup());
		if ($page->isPublic()) {
			// all users in the group the page belongs to
			return $groupManager->getUsers();
		} else {
			return $groupManager->getUsersByPermissionUniqueName('MINISITE_ACCESS', true);
		}
	}

}
