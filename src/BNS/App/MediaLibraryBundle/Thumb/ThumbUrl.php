<?php
namespace BNS\App\MediaLibraryBundle\Thumb;

use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use Knp\Snappy\GeneratorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Constraints as Asset;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class ThumbUrl implements ThumbCreatorInterface
{
    /** @var  ValidatorInterface */
    protected $validator;

    /** @var  LoggerInterface */
    protected $logger;

    /** @var  \Knp\Snappy\Image */
    protected $snappyImage;

    /** @var array  */
    protected $whiteList;

    public function __construct(ValidatorInterface $validator, LoggerInterface $logger, GeneratorInterface $snappyImage, array $whitelist = null)
    {
        $this->validator = $validator;
        $this->logger = $logger;
        $this->snappyImage = $snappyImage;
        $this->whiteList = $whitelist ? : [];
    }

    /**
     * @inheritDoc
     */
    public function supports($url)
    {
        if (!is_string($url)) {
            return false;
        }

        $urlConstraint = new Asset\Url([
            'checkDNS' => false
        ]);
        $errorList = $this->validator->validate($url, $urlConstraint);
        if (0 === count($errorList)) {
            // Check ip of the domaine
            $name = parse_url($url, PHP_URL_HOST);
            if (in_array($name, $this->whiteList)) {
                return true;
            }
            $ips = gethostbynamel($name);
            if (false === $ips) {
                // can't resolve dns
                return false;
            }

            foreach ($ips as $ip) {
                if (0 !== count($this->validator->validate($ip, [new Asset\Ip(['version' => Asset\Ip::ALL_NO_RES])]))) {
                    // ip not in the good range
                    $this->logger->error('ThumbUrl invalid ip address', ['url' => $url, 'ip' => $ip]);

                    return false;
                }
            }
            $port = parse_url($url, PHP_URL_PORT);
            if (!in_array($port, [null, 80, 443], true)) {
                $this->logger->error('ThumbUrl invalid port', ['url' => $url, 'port' => $port]);
                // port not default one
                return false;
            }

            return true;
        }

        $this->logger->error('ThumbUrl invalid url', ['url' => $url, 'errors' => $errorList]);

        return false;
    }

    /**
     * @param ImagineInterface $imagine
     * @param string $url
     * @return bool|ImageInterface
     */
    public function getImage(ImagineInterface $imagine, $url, array $options = array())
    {
        if (!$this->supports($url)) {
            return null;
        }

        $binaryImage = $this->snappyImage->getOutput($url, array_merge([
            'height' => 1024, // force image size to 1280x1024
            'width' => 1280
        ], $options));

        if ($binaryImage) {
            return $imagine->load($binaryImage);
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function getThumbKey($object)
    {
        if (!$this->supports($object)) {
            return null;
        }

        return 'url:' . sha1($object);
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
     * @param $object
     * @return mixed
     */
    public function serialize($object)
    {
        return $object;
    }

    /**
     * @param string $serializedObject
     * @return string
     */
    public function unserialize($serializedObject)
    {
        return $serializedObject;
    }
}
