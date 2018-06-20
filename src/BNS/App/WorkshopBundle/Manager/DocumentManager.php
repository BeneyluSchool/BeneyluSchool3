<?php

namespace BNS\App\WorkshopBundle\Manager;

use BNS\App\MediaLibraryBundle\Manager\MediaCreator;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\ResourceBundle\Creator\BNSResourceCreator;
use BNS\App\WorkshopBundle\Model\WorkshopContentPeer;
use BNS\App\WorkshopBundle\Model\WorkshopDocument;
use BNS\App\WorkshopBundle\Model\WorkshopDocumentPeer;
use BNS\App\WorkshopBundle\Model\WorkshopDocumentQuery;
use BNS\App\WorkshopBundle\Model\WorkshopPage;
use BNS\App\WorkshopBundle\Model\WorkshopQuestionnaire;

/**
 * Class DocumentManager
 *
 * @package BNS\App\WorkshopBundle\Manager
 */
class DocumentManager
{

    /**
     * @var ThemeManager
     */
    private $themeManager;

    /**
     * @var ContentManager
     */
    private $contentManager;

    public function __construct(ThemeManager $themeManager, ContentManager $contentManager)
    {
        $this->themeManager = $themeManager;
        $this->contentManager = $contentManager;
    }

    /**
     * Creates a new WorkshopDocument, sets sensible defaults, and returns it.
     * It is *not* saved.
     *
     * @return WorkshopDocument
     */
    public function create($destination = null, $questionnaire = null)
    {
        if ($questionnaire) {
            $workshopDocument = new WorkshopQuestionnaire();
        } else {
            $workshopDocument = new WorkshopDocument();
        }
        $this->contentManager->setup($workshopDocument, null, null, $destination);

        // set the document theme to default
        $workshopDocument->setThemeCode($this->themeManager->getDefaultThemeCode());

        // add an empty new page
        $firstPage = new WorkshopPage();
        if ($questionnaire) {
            $firstPage->setLayoutCode('full');
        }
        $workshopDocument->addWorkshopPage($firstPage);

        return $workshopDocument;
    }

    /**
     * Makes a copy of the given WorkshopDocument and returns it. Related pages, widget groups and widgets are all
     * copied too, as well as Contributions.
     *
     * @param WorkshopDocument|int $document A WorkshopDocument, or its ID.
     * @param Media $media
     * @return mixed
     * @throws \Exception
     */
    public function copy($document, Media $media)
    {
        if (!$document instanceof WorkshopDocument) {
            $document = WorkshopDocumentQuery::create()->findPk($document);
        }

        if (!$document) {
            throw new \InvalidArgumentException("Invalid document: " . $document);
        }

        $con = \Propel::getConnection(WorkshopContentPeer::DATABASE_NAME);
        $con->beginTransaction();
        try {
            $contributoUserIds = null;
            $contributoGroupIds = null;
            if ('USER' === $media->getMediaFolderType()) {
                $contributoUserIds = array($media->getUserId());
                $contributoGroupIds = false;
            }

            $newContent = $this->contentManager->copy($document->getWorkshopContent(), $contributoUserIds, $contributoGroupIds);
            $newContent->save($con);

            $newDocument = $document->copy();
            $newDocument->setWorkshopContent($newContent);
            $newDocument->setMedia($media);
            $newDocument->getMedia()->setLabel('Copie de ' . $document->getLabel());
            $newDocument->save($con);

            foreach ($document->getWorkshopDocumentContributions() as $contribution) {
                $newContribution = $contribution->copy();
                $newDocument->addWorkshopDocumentContribution($newContribution);
                $newContribution->save($con);
            }

            foreach ($document->getWorkshopPages() as $page) {
                $newPage = $page->copy();
                $newDocument->addWorkshopPage($newPage);
                $newPage->save($con);

                foreach ($page->getWorkshopWidgetGroups() as $widgetGroup) {
                    $newWidgetGroup = $widgetGroup->copy();
                    $newPage->addWorkshopWidgetGroup($newWidgetGroup);
                    $newWidgetGroup->save($con);

                    foreach ($widgetGroup->getWorkshopWidgets() as $widget) {
                        $newWidget = $widget->copy();
                        $newWidgetGroup->addWorkshopWidget($newWidget);
                        $newWidget->save($con);
                    }
                }
            }
            $con->commit();
        } catch (\Exception $e) {
            $con->rollBack();
            throw $e;
        }

        return $newDocument;
    }

}
