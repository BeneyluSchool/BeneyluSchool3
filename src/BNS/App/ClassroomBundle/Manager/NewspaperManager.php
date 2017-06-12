<?php

namespace BNS\App\ClassroomBundle\Manager;
use BNS\App\ClassroomBundle\Model\ClassroomNewspaper;
use BNS\App\ClassroomBundle\Model\ClassroomNewspaperQuery;
use BNS\App\CoreBundle\Buzz\Browser;
use BNS\App\MediaLibraryBundle\Manager\MediaCreator;
use BNS\App\MediaLibraryBundle\Model\MediaFolderUserQuery;
use Symfony\Component\Routing\Router;

/**
 * Class NewspaperManager
 *
 * @package BNS\App\ClassroomBundle\Manager
 */
class NewspaperManager
{

    protected $router;
    protected $buzz;
    protected $mediaCreator;
    protected $publicVersionUrl;

    public function __construct(Router $router, Browser $buzz, MediaCreator $mediaCreator, $publicVersionUrl)
    {
        $this->router = $router;
        $this->buzz = $buzz;
        $this->mediaCreator = $mediaCreator;
        $this->publicVersionUrl = $publicVersionUrl;
    }

    public function getForDate($date, $remote = false)
    {
        $newspaper = ClassroomNewspaperQuery::create()
            ->filterByIsCalendar(null, \Criteria::ISNULL)
            ->findOneByDate($date)
        ;
        if (!$newspaper && $remote) {
            $newspaper = $this->fetchRemote($date);
        }

        return $newspaper;
    }

    protected function fetchRemote($date)
    {
        $url = $this->publicVersionUrl . $this->router->generate('BNSAppClassroomBundle_front_expose_newspaper', [
            'date' => $date,
        ]);
        $response = $this->buzz->get($url);
        if ($response->getContent()) {
            $new = json_decode($response->getContent(),true);
            $newspaper = new ClassroomNewspaper();
            $newspaper->setDate($date);
            $newspaper->setTitle($new['title']);
            $newspaper->setMediaTitle($new['media_title']);
            $newspaper->setJoke($new['joke']);
            $newspaper->setRiddle($new['riddle']);
            $newspaper->setRiddleAnswer($new['riddle_answer']);
            $newspaper->setText($new['text']);
            if (isset($new['day_read'])) {
                $newspaper->setDayRead($new['day_read']);
            }
            if (isset($new['caption'])) {
                $newspaper->setCaption($new['caption']);
            }
            if (isset($new['lended_by'])) {
                $newspaper->setLendedBy($new['lended_by']);
            }
            // TODO handle error for retry
            //On télécharge PDF + image et place dans dossier utilisateur d'admin en forçant le nom
            $mediaFolder = MediaFolderUserQuery::create()
                ->filterByUserId(1)
                ->filterByTreeLevel(0)
                ->findOne()
            ;
            $media = $this->mediaCreator->createFromUrl($mediaFolder, 1, $new['media_url'], true, 'journal-' . $date . '.pdf');
            $newspaper->setMediaRelatedByMediaId($media);
            if ($media->getTypeUniqueName() == 'DOCUMENT') {
                $mediaPreview = $this->mediaCreator->createFromUrl($mediaFolder, 1, $new['image_preview_url'], true, 'journal-' . $date . '.jpg');
                $newspaper->setMediaRelatedByMediaPreviewId($mediaPreview);
            }
            $newspaper->save();

            return $newspaper;
        }

        return null;
    }

}
