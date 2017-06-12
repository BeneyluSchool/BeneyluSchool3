<?php

namespace BNS\App\SearchBundle\Manager;

use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\MediaLibraryBundle\Manager\MediaFolderManager;
use BNS\App\MediaLibraryBundle\Manager\MediaManager;
use BNS\App\MediaLibraryBundle\Model\MediaFolderGroupPeer;
use BNS\App\MediaLibraryBundle\Model\MediaFolderGroupQuery;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use BNS\App\SearchBundle\Model\SearchInternet;


use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerInterface;
use BNS\App\SearchBundle\Model\SearchInternetQuery;
use BNS\App\SearchBundle\Model\SearchWhiteListQuery;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Router;

class SearchManager
{
    protected $kernel;
    protected $router;

    public function __construct(KernelInterface $kernel, Router $router)
    {
        $this->kernel = $kernel;
        $this->router = $router;
    }

    public function getWhiteList($groupId)
	{
		$wl = SearchWhiteListQuery::create()->setFormatter('PropelArrayFormatter')->findByGroupId($groupId);
		$return = array();

		foreach($wl as $link){
			$return[] = $link['MediaId'];
		}
		return $return;
	}

	public function getWhiteListObjects($groupId)
	{
		$wl = $this->getWhiteList($groupId);
		return MediaQuery::create()->filterByStatusDeletion(1)->findById($wl);
	}

	public function updateUniqueKey($groupId)
	{
		$group = GroupQuery::create()->findOneById($groupId);
		$group->setAttribute('WHITE_LIST_UNIQUE_KEY',md5($groupId . date('U')));
	}

	public function toggleWhiteList($mediaId, $groupId)
	{
		//Mise à jour du unique Key du groupe
		$this->updateUniqueKey($groupId);
		$query = SearchWhiteListQuery::create()->filterByGroupId($groupId)->filterByMediaId($mediaId);
		if($query->findOne()){
			$query->delete();
			return false;
		}else{
			$query->findOneOrCreate()->save();
			return true;
		}
	}

    public function getLinks(Group $group)
    {

        $groupsFoldersIds = MediaFolderGroupQuery::create()
            ->select(MediaFolderGroupPeer::ID)
            ->filterByStatusDeletion(MediaFolderManager::STATUS_ACTIVE)
            ->filterByGroupId($group->getId())
            ->find()->toArray();

        return MediaQuery::create()
            ->filterByMediaFolderType('GROUP')
            ->filterByMediaFolderId($groupsFoldersIds)
            ->filterByTypeUniqueName('LINK')
            ->filterByStatusDeletion(MediaManager::STATUS_ACTIVE)
            ->find();
    }

    public function addSearch($term, $user)
    {
        $search = new SearchInternet();
        $search->setUser($user);
        $search->setLabel($term);
        $search->save();

    }

    public function getSearchWhiteListUrl($group)
    {
        // Envoi de l'Url du XML d'annotations pourle moteur de recherche Google
        switch ($this->kernel->getEnvironment()) {
            case 'app_dev':
                return "https://beneylu.com/ent/recherche/white-list/5b08df329298621be24e6e2fc0913259";
            default:
                $key = $group->getAttribute('WHITE_LIST_UNIQUE_KEY');
                // Si clé non initialisée, on l'initialise
                if ($key == null || $key == '') {
                    $this->updateUniqueKey($group->getId());
                    $key = $group->getAttribute('WHITE_LIST_UNIQUE_KEY');
                }
                return $this->router->generate('BNSAppResourceBundle_white_list_xml', array(
                    'key' => $key
                ), true);
        }
    }
}
