<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 24/01/2018
 * Time: 14:56
 */

namespace BNS\App\SearchBundle\ApiController;


use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\SearchBundle\Model\SearchSaved;
use BNS\App\SearchBundle\Model\SearchSavedQuery;
use FOS\RestBundle\Util\Codes;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\Finder\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Request;

class SearchSaveApiController extends BaseApiController
{

    /**
     * @ApiDoc(
     *  section="Search Save",
     *  resource = true,
     *  description="Liste les recherches sauvegardées",
     * )
     *
     * @Rights("SEARCH_ACCESS")
     *
     * @Rest\Get("/search/saved")
     * @Rest\View()
     */
    public function indexAction()
    {
        if (!$this->hasFeature('search_sdet_save')) {
            throw $this->createAccessDeniedException();
        }

        return SearchSavedQuery::create()
            ->filterByUser($this->getUser())
            ->lastCreatedFirst()
            ->find()
            ->getArrayCopy()
        ;
    }

    /**
     * @ApiDoc(
     *  section="Search Save",
     *  resource = true,
     *  description="Sauvegarder une recherche",
     * )
     *
     * @Rest\Post("/search/save")
     */
    public function addSearchSavedAction(Request $request)
    {
        if (!$this->hasFeature('search_sdet_save')) {
            throw $this->createAccessDeniedException();
        }
        if ( !$this->get('bns.right_manager')->hasRight('SEARCH_ACCESS')) {
            throw new AccessDeniedException();
        }
        $term = $request->get('term');
        $result = $request->get('result');
        $searchSave =  new SearchSaved();
        $searchSave->setUserId($this->getCurrentUserId())->setSearch($term)->setResults($result)->save();
        return $this->view(null, Codes::HTTP_OK);
    }

    /**
     * @ApiDoc(
     *  section="Search Save",
     *  resource = true,
     *  description="Sauvegarder une recherche",
     * )
     *
     * @Rest\Delete("/search/delete/{id}")
     */
    public function deleteSearchSavedAction($id)
    {
        if (!$this->hasFeature('search_sdet_save')) {
            throw $this->createAccessDeniedException();
        }
        if ( !$this->get('bns.right_manager')->hasRight('SEARCH_ACCESS')) {
            throw new AccessDeniedException();
        }
        $searchSaved = SearchSavedQuery::create()->findPk($id);
        if ($searchSaved->getUserId() !== $this->getCurrentUserId()) {
            throw new AccessDeniedException();
        }
        $searchSaved->delete();
        return $this->view(null, Codes::HTTP_OK);
    }

}
