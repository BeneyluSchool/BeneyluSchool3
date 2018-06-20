<?php

namespace BNS\App\WorkshopBundle\ApiController;
use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\MediaLibraryBundle\Manager\MediaManager;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\ResourceBundle\Model\Resource;
use BNS\App\WorkshopBundle\Model\WorkshopContentInterface;
use BNS\App\WorkshopBundle\Model\WorkshopPage;
use BNS\App\WorkshopBundle\Model\WorkshopDocument;
use BNS\App\WorkshopBundle\Model\WorkshopWidget;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetGroup;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class BaseWorkshopApiController extends BaseApiController
{
    /**
     * Vérifie l'accès à l'atelier
     */
    protected function checkWorkshopAccess()
    {
        $rm = $this->get('bns.right_manager');
        $rm->forbidIf(!$rm->hasRightSomeWhere('WORKSHOP_ACCESS'));
    }


    /////////////////   METHODES de vérification d'accès en management   \\\\\\\\\\\\\\\\\\\\\\
    /**
     * Toutes ces méthodes cascadent les unes avec les autres
     * pour arriver sur la vérification au niveau de la ressource
     */

    protected function canReadResource(Resource $resource)
    {
        return $this->get('bns.resource_right_manager')->canReadResource($resource);
    }

    protected function canManageResource(Resource $resource)
    {
        $this->checkWorkshopAccess();
        return $this->get('bns.resource_right_manager')->canManageResource($resource);
    }

    protected function canViewWorkshopDocument(WorkshopDocument $workshopDocument)
    {
        if (MediaManager::STATUS_QUESTIONNAIRE_COMPETITION === $workshopDocument->getMedia()->getStatusDeletion()) {
            return true;
        }
        try {
            $this->canManageWorkshopDocument($workshopDocument);
        } catch (AccessDeniedHttpException $ex) {
            if (!$this->get('bns.media_library_right.manager')->canReadMedia($workshopDocument->getMedia())) {
                throw new AccessDeniedHttpException();
            }
        }
    }

    protected function canViewWorkshopDocumentJoined(WorkshopDocument $workshopDocument, $objectType, $objectId)
    {
        if (!$this->get('bns.media_library_right.manager')->canReadMediaJoined($workshopDocument->getMedia(), $objectType, $objectId)) {
            throw new AccessDeniedHttpException();
        }
    }

    protected function canManageWorkshopDocument(WorkshopDocument $workshopDocument)
    {
        if ($workshopDocument->getMedia()->getStatusDeletion() === MediaManager::STATUS_QUESTIONNAIRE_COMPETITION ) {
            $competition = $workshopDocument->getCompetition();

            return $this->get('bns.competition.competition.manager')->canManageCompetition($competition, $this->getUser());
        }
        // related media is deleted: forbid access
        if ($workshopDocument->getMedia()->getStatusDeletion() !== MediaManager::STATUS_ACTIVE_INT) {
            throw new AccessDeniedHttpException();
        }

        if (!$this->get('bns.workshop.content.manager')->canManage($workshopDocument->getWorkshopContent(), $this->getUser())) {
            throw new AccessDeniedHttpException();
        }

        return true;
    }

    protected function canManageWorkshopPage(WorkshopPage $workshopPage)
    {
        return $this->canManageWorkshopDocument($workshopPage->getWorkshopDocument());
    }

    protected function canManageWorkshopWidgetGroup(WorkshopWidgetGroup $workshopWidgetGroup)
    {
        return $this->canManageWorkshopPage($workshopWidgetGroup->getWorkshopPage());
    }

    protected function canManageWorkshopWidget(WorkshopWidget $workshopWidget)
    {
        return $this->canManageWorkshopWidgetGroup($workshopWidget->getWorkshopWidgetGroup());
    }

    /**
     * Gets the list of user ids that can be authors in the workshop and that current user can manage.
     *
     * @return array
     */
    protected function getManagedAuthorIds()
    {
        return $this->get('bns.workshop.right_manager')->getManagedAuthorIds($this->getUser());
    }

    public function checkContributorUserIds(WorkshopContentInterface $document, $ids)
    {
        $canRemove = $this->get('bns.user_manager')->setUser($this->getUser())->hasRightSomeWhere('WORKSHOP_ACTIVATION');
        $previousIds = $this->get('bns.workshop.content.manager')->getContributorUserIds($document->getWorkshopContent());

        $validIds = array();
        foreach ($ids as $id) {
            if ($this->get('bns.user_manager')->setUserById($id)->hasRightSomeWhere('WORKSHOP_ACCESS')) {
                $validIds[] = $id;
            }
        }

        // force previous ids if user has no right to remove them
        if (!$canRemove) {
            foreach ($previousIds as $id) {
                if (!in_array($id, $validIds)) {
                    $validIds[] = $id;
                }
            }
        }

        return $validIds;
    }

    public function checkContributorGroupIds(WorkshopContentInterface $document, $ids)
    {
        $userDirectoryManager = $this->get('bns.user_directory.manager');
        $userManager = $this->get('bns.user_manager')->setUser($this->getUser());
        $canRemove = $userManager->hasRightSomeWhere('WORKSHOP_ACTIVATION');
        $previousIds = $this->get('bns.workshop.content.manager')->getContributorGroupIds($document->getWorkshopContent());

        $validIds = array();
        $possibleIds = $userDirectoryManager->getGroupsWhereAccess($this->getUser(), $userDirectoryManager::VIEW_WORKSHOP_CONTRIBUTORS);
        $possibleIds = array_keys($possibleIds);
        foreach ($ids as $id) {
            if (in_array($id, $possibleIds)) {
                $validIds[] = $id;
            }
        }

        // force previous ids if user has no right to remove them
        if (!$canRemove) {
            foreach ($previousIds as $id) {
                if (!in_array($id, $validIds)) {
                    $validIds[] = $id;
                }
            }
        }

        return $validIds;
    }

}
