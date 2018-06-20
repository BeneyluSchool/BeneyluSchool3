<?php

namespace BNS\App\MediaLibraryBundle\Controller;

use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class FrontController extends Controller
{

    /**
     * @Route("/iframe", name="BNSAppMediaLibraryBundle_iframe", options={"expose"=true})
     * @Template("BNSAppMediaLibraryBundle:Front:iframe.html.twig")
     */
    public function callIframeAction ()
    {
        return array();
    }

    /**
     * @Route("/embedded", name="BNSAppMediaLibraryBundle_embedded", options={"expose"=true})
     * @Template("BNSAppMediaLibraryBundle:Front:index.html.twig")
     */
    public function embeddedAction()
    {
        return array(
            'embedded' => true,
        );
    }

    /**
     * @param Media $media
     * @param $type
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/view/{type}/{id}", name="BNSAppMediaLibraryBundle_view", options={"expose"=true})
     */
    public function viewAction($id, $type, Request $request)
    {
        $editable = $request->get('editable', false);
        $size = $request->get('size', 'small');
        $media = $this->get('bns.media.manager')->find($id);
        if (!$this->get('bns.media_library_right.manager')->canReadMedia($media)) {
            throw new AccessDeniedHttpException();
        }

        return $this->render('BNSAppMediaLibraryBundle:MediaBlock/' . ucfirst($type) . ':' . strtolower($media->getTypeUniqueName()) . '.html.twig', array(
            'media' => $media,
            'editable' => $editable,
            'size' => $size,
        ));
    }

    /**
     * @param Media $media
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/download/{id}", name="BNSAppMediaLibraryBundle_download", options={"expose"=true})
     */
    public function downloadAction($id){
        $media = $this->get('bns.media.manager')->find($id);
        if (!$this->get('bns.media_library_right.manager')->canReadMedia($media)) {
            throw new AccessDeniedHttpException();
        }

        $media->setDownloadCount($media->getDownloadCount() + 1);
        $media->save();

        return $this->redirect($this->get('bns.media.download_manager')->getDownloadUrl($media));
    }

    /**
     * @param int $id
     * @param string $size
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/media/{id}/image-url/{size}", options={"expose"=true}, requirements={"size"="[a-z0-9_]+"})
     */
    public function imageUrlAction(Request $request, $id, $size)
    {
        $media = $this->get('bns.media.manager')->find($id);
        if (!$this->get('bns.media_library_right.manager')->canReadMediaJoined($media, $request->get('objectType'), (int) $request->get('objectId'))) {
            throw $this->createAccessDeniedException();
        }

        $url = $this->get('bns.media.download_manager')->getImageDownloadUrl($media, $size);
        if ($url) {
            return $this->redirect($url);
        }

        throw $this->createNotFoundException();
    }

}
