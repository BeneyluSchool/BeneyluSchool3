<?php

namespace BNS\App\MediaLibraryBundle\Parser;
use BNS\App\MediaLibraryBundle\Manager\MediaManager;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class PublicMediaParser
 *
 * @package BNS\App\MediaLibraryBundle\Parser
 */
class PublicMediaParser
{

    protected $container;

    protected $mediaDownloadManager;

    public function __construct(ContainerInterface $container)
    {
        // TODO inject service and remove container (scope "Request" issue, right manager)
        $this->container = $container;
        $this->mediaDownloadManager = $container->get('bns.media.download_manager');
    }

    public function parse($text, $needConnexion = false, $size = 'medium', $light = false, $linkDuration = null)
    {
        $expires = null;
        if ($linkDuration) {
            $expires = $this->mediaDownloadManager->getExpires();
            $this->mediaDownloadManager->setExpires($linkDuration);
        }
        try {
            $html = new \simple_html_dom($text);

            if (null == $html->root) {
                return '';
            }

            $resourcesLinks = $html->find('img[data-slug="*"],source[data-slug="*"],a[data-slug="*"]');
            foreach ($resourcesLinks as $resourceLink) {

                if (null != $resourceLink->attr &&
                    isset($resourceLink->attr['data-slug']) &&
                    isset($resourceLink->attr['data-id']) &&
                    isset($resourceLink->attr['data-uid'])
                ) {

                    try{
                        $resource = MediaQuery::create()
                            ->filterByStatusDeletion(MediaManager::STATUS_ACTIVE)
                            ->findOneById($resourceLink->attr['data-id'])
                        ;
                        if ($resource) {
                            if (!$needConnexion) {
                                $publicLink = $this->createVisualisationUrlResource($resource, $size);
                            } else {
                                if ($this->container->get('bns.media_library_right.manager')->canReadMedia($resource, $light)) {
                                    $publicLink = $this->createVisualisationUrlResource($resource, $size);
                                } else {
                                    $publicLink = "/medias/images/media-library/image.jpg";
                                }
                            }
                        } else {
                            $publicLink = "/medias/images/media-library/image.jpg";
                        }

                    } catch (\Exception $e) {
                        $publicLink = "/medias/images/media-library/image.jpg";
                    }

                    // File
                    if ($resourceLink->tag == 'a') {
                        $resourceLink->attr['href'] = $publicLink;
                    }
                    // Image || Sound & video
                    elseif ($resourceLink->tag == 'img' || $resourceLink->tag == 'source') {
                        $resourceLink->attr['src'] = $publicLink;
                    } elseif ($resourceLink->tag == 'audio') {
                        $resourceLink->attr['src'] = $publicLink;
                    }
                } else {
                    $resourceLink->outertext = '';
                }
            }

            $html = $html->save();
            if ($expires) {
                $this->mediaDownloadManager->setExpires($expires);
            }
        } catch (\Exception $e) {
            if ($expires) {
                $this->mediaDownloadManager->setExpires($expires);
            }
            throw new \Exception($e);
        }

        return $html;
    }

    public function createVisualisationUrlResource(Media $media = null, $size = 'original')
    {
        if ($media) {
            if (null === $size || 'original' === $size) {
                return $media->getDownloadUrl();
            } else {
                if ($media->isImage()) {
                    return $this->mediaDownloadManager->getImageDownloadUrl($media, $size);
                }

                return $media->getDownloadUrl();
            }
        }

        return false;
    }

}
