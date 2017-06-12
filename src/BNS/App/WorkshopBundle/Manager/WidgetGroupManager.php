<?php

namespace BNS\App\WorkshopBundle\Manager;

use BNS\App\CoreBundle\Model\User;
use BNS\App\RealtimeBundle\Publisher\RealtimePublisher;
use BNS\App\WorkshopBundle\Exception\InvalidConfigurationException;
use BNS\App\WorkshopBundle\Model\WorkshopWidget;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetExtendedSetting;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetGroup;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetGroupPeer;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetGroupQuery;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetGroupVersion;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetGroupVersionQuery;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetPeer;

/**
 * Class WidgetGroupManager
 *
 * @package BNS\App\WorkshopBundle\Manager
 */
class WidgetGroupManager
{

    /**
     * @var WidgetConfigurationManager
     */
    private $widgetConfigurationManager;

    /**
     * @var ContributionManager
     */
    private $contributionManager;

    /**
     * @var RealtimePublisher
     */
    private $publisher;

    public function __construct(WidgetConfigurationManager $widgetConfigurationManager, ContributionManager $contributionManager, RealtimePublisher $publisher)
    {
        $this->widgetConfigurationManager = $widgetConfigurationManager;
        $this->contributionManager = $contributionManager;
        $this->publisher = $publisher;
    }

    /**
     * Creates a WidgetGroup, and its embedded Widgets, based on the given data
     *
     * @param array $data An associative array of data:
     *                    'code' => (required) widget configuration code to use
     *                    'zone' => (optional) zone number of the widget group
     *                    'position' => (optional) position of the widget group
     *                                  in its zone
     * @return WorkshopWidgetGroup
     * @throws InvalidConfigurationException
     */
    public function createFromConfiguration($data)
    {
        // fetch the WidgetConfiguration that will be used as a guide
        if (!isset($data['code'])) {
            throw new InvalidConfigurationException("A configuration code is required");
        }
        $configuration = $this->widgetConfigurationManager->findOneBy('code', $data['code']);
        if (!$configuration) {
            throw new InvalidConfigurationException(sprintf("Could not find configuration for code '%s'", $data['code']));
        }

        // create a new WidgetGroup based on the configuration
        $widgetGroup = new WorkshopWidgetGroup();
        $type = $configuration['widget_group_type'];
        $widgetGroup->setType($type);

        // optionally, set its position within the page
        $zone = isset($data['zone']) ? $data['zone'] : 1;
        $widgetGroup->setZone($zone);

        // optionally, set its position within the zone
        $position = isset($data['position']) ? $data['position'] : null;
        $widgetGroup->setPosition($position);

        // create each embedded widget, as per the configuration
        foreach ($configuration['widget_types'] as $widgetType) {
            $widget = new WorkshopWidget();
            // subtype (optional) is specified after a colon
            $types = explode(':', $widgetType);
            $widget->setType($types[0]);
            if (isset($types[1])) {
                $widget->setSubtype($types[1]);
            }
            $widgetGroup->addWorkshopWidget($widget);
        }

        return $widgetGroup;
    }

    /**
     * Applies WidgetGroup order in all the page.
     *
     * @param WorkshopWidgetGroup $widgetGroup
     * @param array $oldValues
     * @throws \Exception
     */
    public function applyOrderInPage(WorkshopWidgetGroup $widgetGroup, $oldValues)
    {
        $pageId = $widgetGroup->getPageId();
        $zone = $widgetGroup->getZone();
        $position = $widgetGroup->getPosition();
        $oldZone = $oldValues['zone'];
        $oldPosition = $oldValues['position'];

        $con = \Propel::getConnection(WorkshopWidgetGroupPeer::DATABASE_NAME);
        $con->beginTransaction();
        try {
            // reorder all widgetGroups in the old zone
            /** @var WorkshopWidgetGroup[] $oldZoneWidgetGroups */
            $oldZoneWidgetGroups = WorkshopWidgetGroupQuery::create()
                ->filterByPageId($pageId)
                ->filterByZone($oldZone)
                ->find($con)
            ;
            $oldZonePositions = array();
            foreach ($oldZoneWidgetGroups as $wg) {
                if ($wg->getId() !== $widgetGroup->getId()) {
                    $oldZonePositions[$wg->getId()] = $wg->getPosition();
                    if ($wg->getPosition() > $oldPosition) {
                        $oldZonePositions[$wg->getId()]--;
                    }
                }
            }
            WorkshopWidgetGroupPeer::reorder($oldZonePositions, $con);

            // reorder all widgetGroups in the new zone
            /** @var WorkshopWidgetGroup[] $newZoneWidgetGroups */
            $newZoneWidgetGroups = WorkshopWidgetGroupQuery::create()
                ->filterByPageId($pageId)
                ->filterByZone($zone)
                ->find($con)
            ;
            $newZonePositions = array();
            foreach ($newZoneWidgetGroups as $wg) {
                if ($wg->getId() !== $widgetGroup->getId()) {
                    $newZonePositions[$wg->getId()] = $wg->getPosition();
                    if ($wg->getPosition() >= $position) {
                        $newZonePositions[$wg->getId()]++;
                    }
                }
            }
            $newZonePositions[$widgetGroup->getId()] = $position;
            WorkshopWidgetGroupPeer::reorder($newZonePositions, $con);

            // TODO: handle sort after a layout change, when zone numbers must be merged

            $con->commit();
        } catch (\Exception $e) {
            $con->rollBack();
            throw $e;
        }
    }

    /**
     * Saves the given WidgetGroup, with an optional User as contributor
     *
     * @param WorkshopWidgetGroup $widgetGroup
     * @param int|User $user
     * @param bool $notify
     * @param \PropelPDO $connexion
     * @throws \Exception
     * @throws \PropelException
     */
    public function save(WorkshopWidgetGroup $widgetGroup, $user = null, $notify = false, $connexion = null)
    {
        // create local con if needed
        if ($connexion) {
            $con = $connexion;
        } else {
            $con = \Propel::getConnection(WorkshopWidgetGroupPeer::DATABASE_NAME);
            $con->beginTransaction();
        }

        // erase all old versions
        $this->cleanVersions($widgetGroup, $con, 0);

        $widgetGroup->save($con); // this should created a version n°1

        if ($user) {
            $document = $widgetGroup->getWorkshopPage()->getWorkshopDocument();
            $this->contributionManager->addContribution($user, $document, $con);
        }

        // persist local con
        if (!$connexion) {
            $con->commit();
        }

        if ($notify) {
            $this->publisher->publish('WorkshopDocument('.$widgetGroup->getWorkshopPage($con)->getDocumentId().'):widget_groups:save', $widgetGroup);
        }
    }

    /**
     * Saves the given WidgetGroup as a draft of the given User
     *
     * @param WorkshopWidgetGroup $widgetGroup
     * @param User $user
     * @throws \Exception
     * @throws \PropelException
     */
    public function setDraft(WorkshopWidgetGroup $widgetGroup, User $user)
    {
        // erase all versions except the first (the public one)
        $this->cleanVersions($widgetGroup, null, 1);

        // register a new version, as draft (should be n°2)
        $widgetGroup->setVersionCreatedBy($user->getId())
            ->setVersionComment('DRAFT')
            ->save()
        ;

        // rolback to the 'public' first version of the object, so that draft is invisible. Disable versioning, to avoid
        // creation of versions recording the rollback...
        WorkshopWidgetGroupPeer::disableVersioning();
        WorkshopWidgetPeer::disableVersioning();
        $widgetGroup->toVersion(1)->save();
        foreach ($widgetGroup->getWorkshopWidgets() as $widget) {
            $widget->toVersion(1)->save();
        }

        // reenable versioning
        WorkshopWidgetGroupPeer::enableVersioning();
        WorkshopWidgetPeer::enableVersioning();
    }

    /**
     * Applies saved drafts for the given user ID.
     *
     * @param $userId
     * @param bool $notify
     * @throws \Exception
     */
    public function applyUserDrafts($userId, $notify = false)
    {
        $con = \Propel::getConnection(WorkshopWidgetGroupPeer::DATABASE_NAME);
        $con->beginTransaction();

        $drafts = WorkshopWidgetGroupVersionQuery::create()
            ->filterByVersionCreatedBy($userId)
            ->filterByVersionComment('DRAFT', \Criteria::EQUAL)
            ->find($con)
        ;

        try {
            /** @var WorkshopWidgetGroupVersion $draft */
            foreach ($drafts as $draft) {
                $widgetGroup = $draft->getWorkshopWidgetGroup($con);
                $widgetGroup->toVersion($widgetGroup->getLastVersionNumber($con), $con);  // use the last version
                $this->save($widgetGroup, $userId, $notify, $con);
            }
            $con->commit();
        } catch (\Exception $e) {
            $con->rollBack();

            throw $e;
        }
    }

    /**
     * Removes all drafts of the given WidgetGroup.
     *
     * @param WorkshopWidgetGroup $widgetGroup
     */
    public function removeDrafts(WorkshopWidgetGroup $widgetGroup)
    {
        $this->cleanVersions($widgetGroup, null, 1);

        WorkshopWidgetGroupPeer::disableVersioning();
        WorkshopWidgetPeer::disableVersioning();

        $widgetGroup->save();

        WorkshopWidgetGroupPeer::enableVersioning();
        WorkshopWidgetPeer::enableVersioning();
    }

    /**
     * Duplicate the given WidgetGroup.
     *
     * @param WorkshopWidgetGroup $widgetGroup
     */
    public function duplicate(WorkshopWidgetGroup $widgetGroup)
    {
        $newWidgetGroup = new WorkshopWidgetGroup();
        $newWidgetGroup->setPageId($widgetGroup->getPageId());
        $newWidgetGroup->setType($widgetGroup->getType());
        $newWidgetGroup->setZone($widgetGroup->getZone());
        $newWidgetGroup->setPosition($widgetGroup->getPosition());

        $widgets = $widgetGroup->getWorkshopWidgets();
        foreach ($widgets as $widget) {
            $newWidget = new WorkshopWidget();
            $newWidget->setPosition($widget->getPosition());
            $newWidget->setType($widget->getType());
            $newWidget->setMediaId($widget->getMediaId());
            $newWidget->setContent($widget->getContent());
            $newWidget->setSettings($widget->getSettings());
            $newWidget->setVersion(1);

            $extSettings = $widget->getWorkshopWidgetExtendedSetting();
            if ($extSettings) {
                $newExtSettings = new WorkshopWidgetExtendedSetting();
                $newExtSettings->setChoices($extSettings->getChoices());
                $newExtSettings->setCorrectAnswers($extSettings->getCorrectAnswers());
                $newExtSettings->setAdvancedSettings($extSettings->getAdvancedSettings());

                $newWidget->setWorkshopWidgetExtendedSetting($newExtSettings);
            }

            $newWidget->setWorkshopWidgetGroup($newWidgetGroup);
        }
        return $newWidgetGroup;
    }

    /**
     * Deletes all versions ov the given WidgetGroup (and related Widgets) having a version number greater than the
     * given one.
     *
     * @param WorkshopWidgetGroup $widgetGroup
     * @param \PropelPDO $con
     * @param int $minimumVersion Defaults to 0: erases all versions
     */
    protected function cleanVersions(WorkshopWidgetGroup $widgetGroup, \PropelPDO $con = null, $minimumVersion = 0) {

        foreach ($widgetGroup->getAllVersions($con) as $version) {
            if ($version->getVersion() > $minimumVersion) {
                $version->delete($con);
            }
        }

        foreach ($widgetGroup->getWorkshopWidgets(null, $con) as $widget) {
            foreach ($widget->getAllVersions($con) as $version) {
                if ($version->getVersion() > $minimumVersion) {
                    $version->delete($con);
                }
            }
            $widget->setVersion($minimumVersion);
        }

        $widgetGroup->setVersion($minimumVersion);
    }

}
