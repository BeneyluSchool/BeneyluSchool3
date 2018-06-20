<?php
namespace BNS\App\MediaLibraryBundle\Download;

use BNS\App\MediaLibraryBundle\Manager\MediaThumbCreator;
use BNS\App\MediaLibraryBundle\Thumb\ThumbCreatorManager;
use FOS\RestBundle\Util\Codes;
use Gaufrette\Adapter;
use Gaufrette\Util\Path;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class Download
{
    /** @var MediaDownloadValidator  */
    protected $downloadValidator;

    /** @var  MediaDownloadManager */
    protected $downloadManager;

    /** @var ThumbCreatorManager  */
    protected $thumbCreatorManager;

    /**
     * @var  MediaThumbCreator
     * @deprecated old way to create thumb
     */
    protected $mediaThumbCreator;

    /** @var  Adapter */
    protected $localAdapter;

    /** @var  string path to local folder */
    protected $resourceFolder;

    public function __construct(
        MediaDownloadValidator $downloadValidator,
        MediaDownloadManager $downloadManager,
        MediaThumbCreator $mediaThumbCreator,
        ThumbCreatorManager $thumbCreatorManager,
        Adapter $localAdapter,
        $resourceFolder
    ) {
        $this->downloadValidator = $downloadValidator;
        $this->downloadManager = $downloadManager;
        $this->mediaThumbCreator = $mediaThumbCreator;
        $this->thumbCreatorManager = $thumbCreatorManager;
        $this->localAdapter = $localAdapter;
        $this->resourceFolder = $resourceFolder;
    }

    public function downloadAction(Request $request)
    {
        try {
            $this->validate($request);

            $path = $request->get('pattern');
            $filename = $request->get('filename');
            $mimeType = $request->get('mime_type');
            $size = $request->get('size');
            $expire = (int)$request->get('temp_url_expires', 0);

            if (!$path || !$filename) {
                throw new NotFoundHttpException();
            }
            $fullPath = $path . $filename;
            // new thumb
            $response = $this->handleThumb($fullPath);
            if (!$response) {
                // media download
                $response = $this->handleMediaDownload($path, $filename, $size);
            }
            if (!$response instanceof Response) {
                if ($this->localAdapter->exists($response)) {
                    $response = $this->sendLocalFile($response, $filename, $mimeType);
                }
            }

            if ($response instanceof Response) {
                $response->setMaxAge(abs($expire - time()));

                return $response;
            }

        } catch (HttpException $e) {
            return new Response($e->getMessage(), $e->getStatusCode(), $e->getHeaders());
        } catch (\Exception $e) {
            // TODO log this error
        }

        return new Response('', Codes::HTTP_NOT_FOUND);
    }

    protected function sendLocalFile($fullPath, $filename, $mimeType)
    {
        // Allow x-sendfile
        BinaryFileResponse::trustXSendfileTypeHeader();
        $fullPath = Path::normalize($this->resourceFolder . '/' . $fullPath);

        $headers = ['X-Sendfile-Type' => 'X-Sendfile'];
        if ($mimeType) {
            $headers['Content-Type'] = $mimeType;
        }
        $response = new BinaryFileResponse(
            $fullPath,
            Codes::HTTP_OK,
            $headers,
            false
        );
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);

        return $response;
    }

    /**
     * @param string $fullPath path to media
     * @return RedirectResponse|false|string
     */
    protected function handleThumb($fullPath)
    {
        if ($this->thumbCreatorManager->isPathValid($fullPath)) {
            $hasThumb = $this->thumbCreatorManager->hasThumb($fullPath);
            if ('expired' === $hasThumb) {
                $this->thumbCreatorManager->askRebuild($fullPath);
            } elseif (true !== $hasThumb) {
                if (!$this->thumbCreatorManager->createThumbFromPath($fullPath)) {
                    return new RedirectResponse('/ent/medias/images/404.jpg', Response::HTTP_FOUND);
                }
            }

            if (false !== $url = $this->downloadManager->generateSwiftTemporaryUrl($fullPath)) {
                // send 301 to allow cache control
                return new RedirectResponse($url, Response::HTTP_MOVED_PERMANENTLY);
            }

            return $fullPath;
        } else {
            return false;
        }
    }

    /**
     * handle download of Media or old thumbnail
     * @param $path
     * @param $filename
     * @param $size
     * @return string|RedirectResponse
     */
    protected function handleMediaDownload($path, $filename, $size)
    {
        $fullPath = $path;
        $needThumb = false;
        if (in_array($size, array_keys(MediaThumbCreator::$thumbnails))) {
            if ($this->localAdapter->isDirectory($fullPath . $size)) {
                $fullPath .= '_' .$size;
            } else {
                $fullPath .= $size;
            }
            $needThumb = true;
        } else if (null !== $size && 'original' !== $size) {
            // invalid size parameter
            throw new NotFoundHttpException();
        }

        if (!$needThumb) {
            $fullPath .= $filename;
        }
        if (!$this->localAdapter->exists($fullPath)) {
            if (!$needThumb && (false !== $url = $this->downloadManager->generateSwiftTemporaryUrl($fullPath))) {
                // send 301 to allow cache control
                return new RedirectResponse($url, Response::HTTP_MOVED_PERMANENTLY);
            }
            if (!$needThumb || !$this->mediaThumbCreator->createLocalThumbForKey($path . $filename, $fullPath, $size)) {
                throw new NotFoundHttpException();
            }
        }

        return $fullPath;
    }

    /**
     * validate the request
     * @param Request $request
     */
    protected function validate(Request $request)
    {
        if (!$this->downloadValidator->validateUrl($request)) {
            throw new NotFoundHttpException();
        }
    }
}
