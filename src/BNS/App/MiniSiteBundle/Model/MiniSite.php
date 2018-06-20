<?php

namespace BNS\App\MiniSiteBundle\Model;

use \BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Translation\TranslatorTrait;
use \BNS\App\MiniSiteBundle\Model\om\BaseMiniSite;
use BNS\App\CoreBundle\Model\GroupQuery;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class MiniSite extends BaseMiniSite
{
    use TranslatorTrait;

	const PERMISSION_ACCESS			= 'MINISITE_ACCESS';
	const PERMISSION_ACCESS_BACK	= 'MINISITE_ACCESS_BACK';
	const PERMISSION_ADMINISTRATION	= 'MINISITE_ADMINISTRATION';

	public $logoUrl = null;

	/**
	 * @param string $slug
	 *
	 * @return MiniSitePage|boolean False if not found
	 */
	public function findPageBySlug($slug)
	{
		return $this->findPageBy('slug', $slug);
	}

	/**
	 * @param int $id
	 *
	 * @return MiniSitePage|boolean False if not found
	 */
	public function findPageById($id)
	{
		return $this->findPageBy('id', $id);
	}

	/**
	 * @return MiniSitePage
	 */
	public function getHomePage()
	{
		$homePage = $this->findPageBy('isHome', true);
        if(!$homePage)
        {
            foreach($this->getMiniSitePages() as $page)
            {
                $page->setIsHome(true);
                $page->save();
                return $page;
            }
        }
        return $homePage;
	}

    public function hasPublicPages()
    {
        return MiniSitePageQuery::create()
            ->filterByMiniSite($this)
            ->filterByIsPublic(true)
            ->count() > 0;
    }

	/**
	 * @param string $columnName
	 * @param mixed $object
	 *
	 * @return MiniSitePage|boolean
	 */
	private function findPageBy($columnName, $object)
	{
		$pages = $this->getMiniSitePages();
		$methodName = 'get' . ucfirst($columnName);

		foreach ($pages as $page) {
			if ($page->$methodName() == $object) {
				return $page;
			}
		}

		return false;
	}

	/**
	 * @param string $slug
	 * @param MiniSitePage $pageToReplace
	 *
	 * @throws \InvalidArgumentException
	 */
	public function replaceMiniSitePage($slug, MiniSitePage $pageToReplace)
	{
		if (!isset($this->collMiniSitePages)) {
			$this->getMiniSitePages();
		}

		$foundKey = null;
		foreach ($this->collMiniSitePages as $i => $page) {
			if ($page->getSlug() == $slug) {
				$foundKey = $i;
				break;
			}
		}

		if (null == $foundKey) {
			throw new \InvalidArgumentException('The page with slug : ' . $slug . ' does NOT exist !');
		}

		$this->collMiniSitePages[$foundKey] = $pageToReplace;
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
	 * Switch public state
	 */
	public function switchPublic()
	{
		if ($this->isPublic()) {
			$this->setIsPublic(false);
		}
		else {
			$this->setIsPublic(true);
		}
	}

	/**
	 * Only activated minisite pages
	 *
	 * @return array<MiniSitePage>
	 */
	public function getActivatedMiniSitePages()
	{
		$pages = $this->getMiniSitePages();
		$activatedPages = array();

		foreach ($pages as $page) {
			if ($page->isActivated()) {
				$activatedPages[] = $page;
			}
		}

		return $activatedPages;
	}

	/**
	 * @param \BNS\App\CoreBundle\Model\User $user
	 *
	 * @return boolean
	 */
	public function isEditor(User $user)
	{
		$pages = $this->getMiniSitePages();
		foreach ($pages as $page) {
			if ($page->isEditor($user)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return string
	 */
	public function __toString()
	{
		return '#' . $this->getId() . ' - ' . $this->getTitle();
	}

	public function getBannerUrl()
	{
		$media = $this->getResource();

		if ($media) {
			return $media->getImageUrl('banner_minisite_front');
		}

		return null;
	}

	public function getGroupLabel()
	{
		$group = GroupQuery::create()->findOneById($this->getGroupId());
		return $group->getLabel();
	}

    public function getPublic ()
    {
        $count = MiniSitePageQuery::create()
                    ->filterByMinisiteId($this->getId())
                    ->filterByIsPublic(1)
                    ->count();
        if($count > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Makes sure this minisite has a city page, if eligible.
     *
     * @param Bool $check Whether to check if site is eligible. Defaults to true
     * @return MiniSitePage|bool
     */
    public function ensureCityPage($check = true)
    {
        if ($check) {
            $group = $this->getGroup();
            if (!in_array($group->getType(), ['CITY', 'SCHOOL'])) {
                return false;
            }
        }

        $cityPage = $this->getCityPage();
        if (!$cityPage) {
            $cityPage = self::createCityPage($this->getId(), $this->getTranslator());
            $this->addMiniSitePage($cityPage);
        }

        return $cityPage;
    }

    public static function createCityPage($minisiteId, TranslatorInterface $translator)
    {
        $cityPage = new MiniSitePage();
        $cityPage->setMiniSiteId($minisiteId);
        $cityPage->setType(MiniSitePagePeer::TYPE_CITY);
        $cityPage->setTitle($translator->trans('TITLE_CITY_INFORMATIONS', [], 'MINISITE'));
        $cityPage->setIsPublic(true);
        $cityPage->setIsActivated(true);
        $cityPage->save();

        return $cityPage;
    }

    public function getCityPage()
    {
        return MiniSitePageQuery::create()
            ->filterByMiniSite($this)
            ->filterByType(MiniSitePagePeer::TYPE_CITY)
            ->findOne()
        ;
    }
}
