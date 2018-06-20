<?php
namespace BNS\App\MediaLibraryBundle\Thumb;

use BNS\App\MiniSiteBundle\Model\MiniSite;
use BNS\App\MiniSiteBundle\Model\MiniSitePage;
use BNS\App\MiniSiteBundle\Model\MiniSiteQuery;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class ThumbMinisite implements ThumbCreatorInterface
{
    /** @var  ThumbUrl */
    protected $thumbUrl;

    /** @var RouterInterface  */
    protected $router;

    public function __construct(ThumbUrl $thumbUrl, RouterInterface $router)
    {
        $this->thumbUrl = $thumbUrl;
        $this->router = $router;
    }

    /**
     * @inheritDoc
     */
    public function supports($minisiteOrPage)
    {
        return $minisiteOrPage instanceof MiniSite || $minisiteOrPage instanceof MiniSitePage;
    }

    /**
     * @param ImagineInterface $imagine
     * @param MiniSite|MiniSitePage $minisiteOrPage
     * @return ImageInterface
     */
    public function getImage(ImagineInterface $imagine, $minisiteOrPage)
    {
        if (!$this->supports($minisiteOrPage)) {
            return false;
        }

        $slug = null;
        if ($minisiteOrPage instanceOf MiniSitePage) {
            $minisiteOrPage = $minisiteOrPage->getMiniSite();
        }


        if ($minisiteOrPage instanceof MiniSite) {

            if (!$minisiteOrPage->hasPublicPages()) {
                // No public page we set a default image
                return $imagine->open(dirname(__DIR__, 5) . '/web/assets/images/apps/minisite/banner.jpg');
            }
            $slug = $minisiteOrPage->getSlug();
            if ($slug) {
                $url = $this->router->generate('BNSAppMiniSiteBundle_front', ['slug' => $slug], RouterInterface::ABSOLUTE_URL);

                return $this->thumbUrl->getImage($imagine, $url, [
//                    'margin-top' => 0,
//                    'margin-right' => 0,
//                    'margin-bottom' => 0,
//                    'margin-left' => 0,
                    'enable-javascript' => true,
                    'debug-javascript' => false,
                    'javascript-delay' => 5000,
                    'window-status' => 'done',
                ]);
            }
        }

        return null;
    }

    /**
     * @param MiniSite|MiniSitePage|mixed $object
     * @return bool|string
     */
    public function getThumbKey($object)
    {
        if (!$this->supports($object)) {
            return false;
        }
        if ($object instanceof MiniSite) {
            return 'site:' . sha1($object->getId());
        }

        return 'site:' . sha1($object->getMiniSiteId());
    }

    /**
     * @inheritDoc
     */
    public function getExtension($object)
    {
        return ThumbCreatorManager::IMAGE_JPEG;
    }

    /**
     * @param $object
     * @return array
     */
    public function getOptions($object)
    {
        return [
            'vertical_align' => 'top'
        ];
    }

    /**
     * @param MiniSite|MiniSitePage $minisiteOrPage
     * @return string
     */
    public function serialize($minisiteOrPage)
    {
        if ($minisiteOrPage instanceOf MiniSitePage) {
            $minisiteOrPage = $minisiteOrPage->getMiniSite();
        }

        if ($minisiteOrPage instanceof MiniSite) {
            $context = $this->router->getContext();
            return json_encode([
                'id' => $minisiteOrPage->getId(),
                'host' => $context->getHost(),
                'scheme' => $context->getScheme(),
                'base_url' => $context->getBaseUrl(),
            ]);
        }

        return null;
    }

    /**
     * @param string $serializedObject
     * @return Minisite|null
     */
    public function unserialize($serializedObject)
    {
        try {
            $data = json_decode($serializedObject, true);

            $context = $this->router->getContext();
            if (isset($data['base_url']) && isset($data['host']) && isset($data['scheme'])) {
                $context->setBaseUrl($data['base_url']);
                $context->setHost($data['host']);
                $context->setScheme($data['scheme']);
            }

            $id = isset($data['id']) ? $data['id'] : null;
            if ($id) {
                return MiniSiteQuery::create()->findPk($id);
            }

        } catch (\Exception $e) {

        }

        return null;
    }
}
