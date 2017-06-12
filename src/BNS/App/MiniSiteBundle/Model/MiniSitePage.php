<?php

namespace BNS\App\MiniSiteBundle\Model;

use \BNS\App\CoreBundle\Model\User;
use \BNS\App\MiniSiteBundle\Model\om\BaseMiniSitePage;
use \Symfony\Component\Validator\ExecutionContext;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class MiniSitePage extends BaseMiniSitePage
{
	/**
	 * @param PropelObjectCollection $miniSitePageNews
	 */
	public function replaceMiniSitePageNews($miniSitePageNews)
	{
		$this->collMiniSitePageNewss = $miniSitePageNews;
	}
	
	/**
	 * Simple shortcut
	 * 
	 * @return boolean 
	 */
	public function isActivated()
	{
		return $this->getIsActivated();
	}
	
	/**
	 * Simple shortcut
	 * 
	 * @return boolean
	 */
	public function isHome()
	{
		return $this->getIsHome();
	}
	
	/**
	 * Disable the page if activated and enable the page is disabled
	 */
	public function switchActivation()
	{
		if ($this->isActivated()) {
			$this->setIsActivated(false);
		}
		else {
			$this->setIsActivated(true);
		}
	}
	
	/**
	 * Simple shortcut
	 * 
	 * @return boolean 
	 */
	public function isPublic()
	{
		return $this->getIsPublic();
	}
	
	/**
	 * 
	 */
	public function switchConfidentiality()
	{
		if ($this->isPublic()) {
			$this->setIsPublic(false);
		}
		else {
			$this->setIsPublic(true);
		}
	}

	/**
	 * Add ONE view to the page
	 */
	public function addView()
	{
		$this->setViews($this->getViews() + 1);
	}

	/**
	 * @param \BNS\App\CoreBundle\Model\User $user
	 *
	 * @return boolean
	 */
	public function isEditor(User $user)
	{
		$editors = $this->getMiniSitePageEditors();
		foreach ($editors as $editor) {
			if ($user->getId() == $editor->getUserId()) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Constraint validation
	 */
	public function isTypeExists($context)
	{
		if ('' != $this->type && null != $this->type) {
			$types = array_flip(MiniSitePagePeer::getValueSet(MiniSitePagePeer::TYPE));
			if (!in_array($this->type, $types)) {
                $context->buildViolation('INVALID_PAGE_TYPE')
                    ->atPath('type')
                    ->setTranslationDomain('MINISITE')
                    ->addViolation();
			}
		}
	}
}