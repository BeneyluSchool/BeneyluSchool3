<?php

namespace BNS\App\SearchBundle\ApiController;


use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\CoreBundle\Controller\BaseApiController;

use BNS\App\CoreBundle\Model\User;
use BNS\App\MediaLibraryBundle\Model\MediaFolderGroup;
use BNS\App\MediaLibraryBundle\Model\MediaFolderGroupQuery;
use BNS\App\MediaLibraryBundle\Model\MediaFolderUserQuery;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\SearchBundle\Model\SearchWhiteList;
use BNS\App\SearchBundle\Controller\FrontController;
use BNS\App\CoreBundle\Translation\TranslatorTrait;
use BNS\App\SearchBundle\Model\SearchWhiteListQuery;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcher;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Hateoas\Configuration\Route;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class SearchWhiteListApiController
 *
 * @package BNS\App\UserBundle\ApiController
 */
class SearchWhiteListApiController extends BaseApiController
{
    /**
     * @ApiDoc(
     *  section="Search WhiteList",
     *  resource = true,
     *  description="Get a group's WhiteList",
     * )
     *
     * @Rest\Get("/search/white-list")
     * @Rest\View(serializerGroups={"Default", "media_search"})
     */
    public function getSearchWhiteListAction()
    {
        $rm = $this->get('bns.right_manager');
        $group = $rm->getCurrentGroup();
        $links = $this->get('bns.search_manager')->getLinks($group);
        $whiteList = $this->get('bns.search_manager')->getWhiteList($group->getId());

        foreach ($links as $link) {
            $link->searchStatus = false;
            if (in_array($link->getId(), $whiteList)) {
                $link->searchStatus = true;
            }
        }

        return array(
            'links' => $links,
        );
    }

    /**
     * @ApiDoc(
     *  section="Search WhiteList",
     *  resource = true,
     *  description="Add a search url",
     * )
     *
     * @Rest\Post("/search/url")
     * @Rest\View(serializerGroups={"Default", "media_search"})
     * @RightsSomeWhere("MEDIA_LIBRARY_ACCESS")
     */
    public function addSearchUrlAction(Request $request)
    {
        $url = $request->get('url');
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $translator = $this->get('translator');

        $creator = $this->get('bns.media.creator');
        if ($scheme == null) {
            $url = "http" . "://" . $url;
            $scheme = "http";
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (!$url || !$creator->isValidURL($url) || !$host || !$scheme) {
            return View::create([
                'error' => $translator->trans('INVALID_URL', [], 'MAIN')
            ], Codes::HTTP_BAD_REQUEST);
        }

        $linksFolderLabel = $translator->trans('LINKS_FOLDER', array(), 'SEARCH');

        $group = $this->get('bns.right_manager')->getCurrentGroup();

        $mediaFolder = MediaFolderGroupQuery::create()
            ->filterByGroup($group)
            ->filterByIsUserFolder(false)
            ->filterByTreeLevel(0, \Criteria::GREATER_THAN)
            ->filterByLabel($linksFolderLabel)
            ->filterByStatusDeletion(1)
            ->findOne();

        if (!$mediaFolder) {
            $mediaFolderId = MediaFolderGroupQuery::create()
                ->filterByGroup($group)
                ->filterByIsUserFolder(false)
                ->filterByTreeLeft(1)// find only root folder
                ->select('id')
                ->findOne();
            // create a media folder with search links in it
            $mediaFolder = $this->get('bns.media_folder.manager')->create($linksFolderLabel, $mediaFolderId, 'GROUP');
        }

        try {

            if ($media = $creator->createFromUrl($mediaFolder, $this->get('bns.right_manager')->getUserSessionId(), $scheme . "://" . $host)) {

                // enable the link for the search
                SearchWhiteListQuery::create()->filterByGroupId($group->getId())->filterByMedia($media)->findOneOrCreate()->save();

                $media->searchStatus = true;

                return $media;
            }

        } catch (\Exception $e) {
        }

        return View::create([
            'error' => $translator->trans('INVALID_URL', [], 'MAIN')
        ], Codes::HTTP_BAD_REQUEST);
    }

    /**
     * @ApiDoc(
     *  section="Search WhiteList",
     *  resource = true,
     *  description="Toggle the link",
     * )
     *
     * @Rest\Post("/search/media/{mediaId}/toggle")
     * @Rest\View(serializerGroups={"Default","media_search"})
     */
    public function toggleLinkAction($mediaId, Request $request)
    {
        $linkStatus = $request->get('toggle');

        $searchManager = $this->get('bns.search_manager');
        $rightManager = $this->get('bns.right_manager');
        $contextId = $rightManager->getCurrentGroupId();

        $link = MediaQuery::create()->findOneById($mediaId);
        if($this->get('bns.media_library_right.manager')->canReadMedia($link))
        {
            $searchManager->updateUniqueKey($contextId);
            $query = SearchWhiteListQuery::create()->filterByGroupId($contextId)->filterByMediaId($mediaId);
            if($query->findOne() && !$linkStatus ) {
                $query->delete();
            } elseif (!$query->findOne() && $linkStatus ) {
                $query->findOneOrCreate()->save();
            }

            return array('link' => $link,'status' => $linkStatus);
        }else{
            throw new AccessDeniedException();
        }
    }

    /**
     * @ApiDoc(
     *  section="Search WhiteList",
     *  resource = true,
     *  description="Get general WhiteList",
     * )
     *
     * @Rest\Get("/search/general-white-list")
     * @Rest\View(serializerGroups={"Default", "media_search"})
     */
    public function getSearchGeneralWhiteListAction()
    {
        $whiteListGeneral = array_unique(unserialize($this->get('bns.right_manager')->getCurrentGroupManager()->getAttribute('WHITE_LIST')));
        $whiteListUse = $this->get('bns.right_manager')->getCurrentGroupManager()->getAttribute('WHITE_LIST_USE_PARENT');
        return array(
            'white_list_general' => $whiteListGeneral,
            'white_list_use' => $whiteListUse,
            'can_administrate' => $this->get('bns.right_manager')->hasRight('SEARCH_ACCESS_BACK')
        );
    }

    /**
     * @ApiDoc(
     *  section="Search WhiteList",
     *  resource = true,
     *  description="Toggle the general WhiteList",
     * )
     *
     * @Rest\Post("/search/general-white-list/toggle")
     * @Rest\View(serializerGroups={"Default","detail"})
     */
    public function toggleGeneralWhiteListAction(Request $request)
    {
        if ( $request->get('toggle') ) {
            $whiteListGeneral = 1;
        } else {
            $whiteListGeneral = 0;
        }
        $this->get('bns.right_manager')->getCurrentGroupManager()->setAttribute('WHITE_LIST_USE_PARENT',$whiteListGeneral);
        //Mise à jour de la clé pour la cache Google
        $this->get('bns.search_manager')->updateUniqueKey($this->get('bns.right_manager')->getCurrentGroupId());

        return array( 'status' => $whiteListGeneral);
    }

    /**
     * @ApiDoc(
     *  section="Search WhiteList",
     *  resource = true,
     *  description="Get final WhiteList",
     * )
     *
     * @Rest\Get("/search/white-list/url")
     * @Rest\View(serializerGroups={"Default", "media_search"})
     */
    public function getSearchWhiteListUrlAction()
    {
        $group = $this->get('bns.right_manager')->getCurrentGroup();

        $this->get('stat.search')->visit();


        $imagesUrls = [ 'medialandesImage' => 'https://beneylu.com/ent/medias/images/search/medialandes.png' ,
            'canopeImage' => 'https://beneylu.com/ent/medias/images/search/canope.png',
            'universalisImage' => 'https://beneylu.com/ent/medias/images/search/universalis.jpg'
        ];


        return array(
            'white_list_url' => $this->get('bns.search_manager')->getSearchWhiteListUrl($group),
            'has_medialandes' => $this->get('bns.right_manager')->hasMedialandes(true,true),
            'hasUai' => $this->get('bns.right_manager')->getCurrentGroup()->getAttribute('UAI') != false,
            'images' => $imagesUrls,
            'cse' => $this->getParameter('google_cse_search_code'),
        );
    }

    /**
     * @ApiDoc(
     *  section="Search WhiteList",
     *  resource = true,
     *  description="Edit a search url",
     * )
     *
     * @Rest\Patch("/search/url/{id}")
     * @param $request
     * @param $id
     * @Rest\View(serializerGroups={"Default", "media_search"})
     * @RightsSomeWhere("MEDIA_LIBRARY_ACCESS")
     */
    public function editSearchUrlAction(Request $request, $id)
    {
        $url = $request->get('media_value');
        $translator = $this->get('translator');
        $media = MediaQuery::create()->findPk($id);
        if (!$media) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }
        if (!$this->get('bns.media_library_right.manager')->canManageMedia($media)) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);


        $creator = $this->get('bns.media.creator');
        if ($scheme == null) {
            $url = "http" . "://" . $url;
            $scheme = "http";
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (!$url || !$creator->isValidURL($url) || !$host || !$scheme) {
            return View::create([
                'error' => $translator->trans('INVALID_URL', [], 'MAIN')
            ], Codes::HTTP_BAD_REQUEST);
        }

        try {
            if ($creator->isValidURL($scheme . '://' . $host)) {
                $media->setLabel($scheme . '://' . $host)->setValue($scheme . '://' . $host)->save();

                // enable the link for the search
                $search = SearchWhiteListQuery::create()->filterByMediaId($id)->findOne();
                $media->searchStatus = true;
                $search->setMedia($media)->save();


                return $media;
            }
        } catch (\Exception $e) {


            return View::create([
                'error' => $translator->trans('INVALID_URL', [], 'MAIN')
            ], Codes::HTTP_BAD_REQUEST);
        }
    }
}
