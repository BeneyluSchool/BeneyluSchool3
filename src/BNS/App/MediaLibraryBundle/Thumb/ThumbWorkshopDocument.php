<?php
namespace BNS\App\MediaLibraryBundle\Thumb;

use BNS\App\CoreBundle\Routing\SignUrl;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaPeer;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use BNS\App\WorkshopBundle\Model\WorkshopDocument;
use BNS\App\WorkshopBundle\Model\WorkshopDocumentQuery;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use Symfony\Component\Routing\RouterInterface;


class ThumbWorkshopDocument implements ThumbCreatorInterface
{
    /** @var  ThumbUrl */
    protected $thumbUrl;

    /** @var RouterInterface */
    protected $router;

    /** @var SignUrl */
    protected $signUrl;

    public function __construct(ThumbUrl $thumbUrl, RouterInterface $router,  SignUrl $signUrl)
    {
        $this->thumbUrl = $thumbUrl;
        $this->router = $router;
        $this->signUrl = $signUrl;
    }

    /**
     * @inheritDoc
     */
    public function supports($object)
    {
        return $object instanceof WorkshopDocument ||
            (
                $object instanceof Media &&
                in_array($object->getTypeUniqueName(), [
                    MediaPeer::TYPE_UNIQUE_NAME_ATELIER_DOCUMENT,
                    MediaPeer::TYPE_UNIQUE_NAME_ATELIER_QUESTIONNAIRE,
                ])
            );
    }

    /**
     * @param ImagineInterface $imagine
     * @param WorkshopDocument|Media $object
     * @return ImageInterface
     */
    public function getImage(ImagineInterface $imagine, $object)
    {
        if (!$this->supports($object)) {
            return false;
        }

        $object = $this->getWorkshopDocument($object);
        if (!$object) {
            return null;
        }
//        if ($object->isQuestionnaire()) {
//            return $imagine->open(__DIR__ . '/../../../../../web/angular/app/images/media-library/questionnaire.png');
//        }

        $url = $this->router->generate('workshop_html', ['id' => $object->getId()], RouterInterface::ABSOLUTE_URL);
        $signedUrl = $this->signUrl->signUrlForCall('GET', $url);

        return $this->thumbUrl->getImage($imagine, $signedUrl, []);
    }

    /**
     * @param WorkshopDocument|Media|mixed $object
     * @return bool|string
     */
    public function getThumbKey($object)
    {
        if (!$this->supports($object)) {
            return false;
        }

        return 'wd:' . sha1($object->getId());
    }

    /**
     * @inheritDoc
     */
    public function getExtension($object)
    {
        if ($doc = $this->getWorkshopDocument($object)) {
            if ($doc->isQuestionnaire()) {
                return ThumbCreatorManager::IMAGE_PNG;
            }
        }

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
     * @param WorkshopDocument|Media $object
     * @return string
     */
    public function serialize($object)
    {
        if ($object instanceof WorkshopDocument || $object instanceof Media) {
            $context = $this->router->getContext();
            if ($object instanceof Media) {
                $id = $object->getWorkshopDocumentId();
            } else {
                $id = $object->getId();
            }
            return json_encode([
                'id' => $id,
                'host' => $context->getHost(),
                'scheme' => $context->getScheme(),
                'base_url' => $context->getBaseUrl(),
            ]);
        }

        return null;
    }

    /**
     * @param string $serializedObject
     * @return WorkshopDocument|Media|null
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
                return WorkshopDocumentQuery::create()->findPk($id);
            }

        } catch (\Exception $e) {

        }

        return null;
    }

    protected function getWorkshopDocument($object)
    {
        if ($object instanceof Media) {
            $id = $object->getWorkshopDocumentId();
            return WorkshopDocumentQuery::create()->findPk($id);
        }

        return $object;
    }
}
