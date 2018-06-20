<?php
namespace BNS\App\MediaLibraryBundle\Download;

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\MediaLibraryBundle\Adapter\RemoteAdapter;
use BNS\App\MediaLibraryBundle\FileSystem\BNSFileSystemManager;
use BNS\App\MediaLibraryBundle\Manager\MediaManager;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Thumb\ThumbCreatorManager;
use BNS\App\PaasBundle\Manager\PaasManager;
use Gaufrette\Adapter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class MediaDownloadManager
{
    /** @var int  */
    protected $expires = 3600;

    protected $secret;

    protected $baseUrl;

    protected $remoteBaseUrl;

    /**
     * @var  RemoteAdapter
     */
    protected $remoteAdapter;

    /**
     * @var MediaManager
     */
    protected $mediaManager;

    protected $paasManager;

    protected $isRemote = false;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /** @var  ThumbCreatorManager */
    protected $thumbCreatorManager;

    public function __construct(BNSFileSystemManager $fileSystemManager, MediaManager $mediaManager, ContainerInterface $container, $secret, $options = array(), ThumbCreatorManager $thumbCreatorManager)
    {
        $this->secret = $secret;
        $this->mediaManager = $mediaManager;
        $this->container = $container;

        if (isset($options['expires'])) {
            $this->expires = (int) $options['expires'];
        }

        if (isset($options['base_url'])) {
            $this->baseUrl = $options['base_url'];
        }

        if (isset($options['remote_base_url'])) {
            $this->remoteBaseUrl = $options['remote_base_url'];
        }

        if ($fileSystemManager->getAdapter() instanceof RemoteAdapter) {
            $this->isRemote = true;
            $this->remoteAdapter = $fileSystemManager->getAdapter();
        }
        $this->thumbCreatorManager = $thumbCreatorManager;
    }

    /**
     * the time in second allowed for temporary url
     * @return int
     */
    public function getExpires()
    {
        return $this->expires;
    }

    /**
     * the time in second allowed for temporary url
     * @param int $expires
     */
    public function setExpires($expires)
    {
        $this->expires = $expires;
    }

    public function getExpiry()
    {
        // this optimize generated url to use at least expires incremented by half of expires
        $number = time() + $this->expires;
        $increment = round($this->expires / 2);
        $offset = $this->expires;

        return ceil(($number - $offset) / $increment ) * $increment + $offset;
    }

    public function getDownloadUrl(Media $media)
    {
        if ($media->getFromPaas()) {
            // Paas
            $url = $media->getDownloadUrl(false);
            if ($url) {
                if (preg_match('#^http#', $url)) {
                    return $url;
                }

                return $this->getPaasManager()->getPaasUrl() . $url;
            }
            $paasId = $media->getExternalId();

            return $this->getPaasManager()->getUrlFromPaasId($paasId);
        }

        return $this->generateTemporaryUrl($media);
    }

    public function getImageDownloadUrl(Media $media, $size = 'original')
    {
        if ($media->getExternalSource()) {
            if (null === $media->getFromPaasId()) {
                switch ($size) {
                    case 'thumbnail':
                    case 'small':
                        $image = $media->getImageThumbnailUrl();
                        break;
                    case 'medium':
                        $image = $media->getImageMediumUrl();
                        break;
                    default :
                        $image = $media->getImageThumbnailUrl();
                        break;
                }

                return $image . '?';
            } elseif ($media->getDownloadUrl()) {
                return $this->getDownloadUrl($media);
            }
        }
        $this->mediaManager->setMediaObject($media);
        if (!$this->mediaManager->isThumbnailable()) {
            return false;
        }

        return $this->generateLocalTemporaryUrl($media, $size);
    }

    public function generateTemporaryUrl(Media $media, $size = null)
    {
        if ($this->isRemote && ('original' === $size || null === $size)) {
            return $this->generateSwiftTemporaryUrl($media->getFilePath());
        }

        return $this->generateLocalTemporaryUrl($media, $size);
    }

    public function generateSwiftTemporaryUrl($path, $method = 'GET')
    {
        if (!$this->isRemote) {
            return false;
        }

        $expiry = $this->getExpiry();
//        We don't use the normal way to prevent API call
//        $url = $this->remoteAdapter->getContainer()->getUrl($path);
//        $urlPath = urldecode($url->getPath());
        $urlPath = parse_url($this->remoteBaseUrl, \PHP_URL_PATH);      // get remote url without domain
        $urlPath .= $path;                                              // add the unencoded file path
        $body = sprintf("%s\n%d\n%s", $method, $expiry, $urlPath);
        $hash = hash_hmac('sha1', $body, $this->secret);

        $url = $this->remoteBaseUrl . rawurlencode($path);              // rebuild the full url, encoded

        return sprintf('%s?temp_url_sig=%s&temp_url_expires=%d', $url, $hash, $expiry);
    }

    public function generateLocalTemporaryUrl(Media $media, $size = null)
    {
        $path = $media->getFilePathPattern();
        $filename = $media->getFilename();
        $mimeType = $media->getFileMimeType();

        if (null !== $size && 'original' !== $size) {
            if ($fullPath = $this->generateThumbTemporaryUrl($media, $size)) {
                return $fullPath;
            }
        }

        return $this->generateUrl($path, $filename, $mimeType, $size);
    }

    public function generateThumbTemporaryUrl($object, $size)
    {
        if ($fullPath = $this->thumbCreatorManager->getPath($object, $size)) {
            $path = dirname($fullPath) . '/';
            $filename = basename($fullPath);
            $mimeType = 'images/' . pathinfo($fullPath, PATHINFO_EXTENSION);

            return $this->generateUrl($path, $filename, $mimeType, $size);
        }

        return false;
    }

    public function generateUrl($path, $filename, $mimeType, $size)
    {
        $params = array(
            'pattern' => $path,
            'filename' => $filename,
            'mime_type' => $mimeType,
        );

        ksort($params);

        $expiry = $this->getExpiry();

        $urlPath = '/media_download.php?' . http_build_query($params);
        $body = sprintf("%s\n%d\n%s", 'GET', $expiry, $urlPath);
        $hash = hash_hmac('sha1', $body, $this->secret);

        $params['temp_url_sig'] = $hash;
        $params['temp_url_expires'] = $expiry;
        if ($size) {
            $params['size'] = $size;
        }

        return $this->baseUrl . '/media_download.php?' . http_build_query($params);
    }

    /**
     * @return PaasManager
     */
    protected function getPaasManager()
    {
        if (!$this->paasManager) {
            // TODO do this the right way fix scope "Request" issue
            $this->paasManager = $this->container->get('bns.paas_manager');
        }

        return $this->paasManager;
    }
}
