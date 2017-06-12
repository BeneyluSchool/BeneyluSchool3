<?php

namespace BNS\App\UserBundle\AccountLink;

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Api\BNSApi;
use BNS\App\CoreBundle\Application\ApplicationManager;
use BNS\App\CoreBundle\Exception\InvalidInstallApplication;
use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\Model\BlogCategory;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\UserPeer;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Role\BNSRoleManager;
use BNS\App\CoreBundle\User\BNSUserManager;
use BNS\App\HomeworkBundle\Model\HomeworkPreferencesQuery;
use BNS\App\HomeworkBundle\Model\HomeworkSubject;
use BNS\App\MediaLibraryBundle\Manager\MediaFolderManager;
use BNS\App\MediaLibraryBundle\Manager\MediaManager;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaFolderGroup;
use BNS\App\MediaLibraryBundle\Model\MediaFolderGroupQuery;
use BNS\App\MediaLibraryBundle\Model\MediaFolderUser;
use BNS\App\MediaLibraryBundle\Model\MediaFolderUserQuery;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use BNS\App\MessagingBundle\Model\MessagingConversationPeer;
use BNS\App\MessagingBundle\Model\MessagingConversationQuery;
use BNS\App\MessagingBundle\Model\MessagingMessagePeer;
use BNS\App\MessagingBundle\Model\MessagingMessageQuery;
use BNS\App\MiniSiteBundle\Model\MiniSite;
use BNS\App\MiniSiteBundle\Model\MiniSitePage;
use BNS\App\MiniSiteBundle\Model\MiniSitePagePeer;
use BNS\App\MiniSiteBundle\Model\MiniSiteQuery;
use BNS\App\PaasBundle\Manager\PaasWithoutRequestManager;
use BNS\App\UserBundle\AccountLink\Exception\InvalidDataException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Class AccountLinkManager
 *
 * @package BNS\App\UserBundle\AccountLink
 */
class AccountLinkManager
{

    /**
     * @var LoggerInterface
     */
    private $accountLinkLogger;

    /**
     * @var ApplicationManager
     */
    protected $applicationManager;

    /**
     * @var BNSApi
     */
    protected $api;

    /**
     * @var MediaFolderManager
     */
    protected $mediaFolderManager;

    /**
     * @var BNSRoleManager
     */
    protected $roleManager;

    /**
     * @var BNSUserManager
     */
    protected $userManager;

    /**
     * @var BNSGroupManager
     */
    protected $groupManager;

    /**
     * @var PaasWithoutRequestManager
     */
    protected $paasManager;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    public function __construct(
        LoggerInterface $accountLinkLogger,
        ApplicationManager $applicationManager,
        BNSApi $api,
        MediaFolderManager $mediaFolderManager,
        BNSRoleManager $roleManager,
        BNSUserManager $userManager,
        BNSGroupManager $groupManager,
        ContainerInterface $container
    ) {
        $this->accountLinkLogger = $accountLinkLogger;
        $this->applicationManager = $applicationManager;
        $this->api = $api;
        $this->mediaFolderManager = $mediaFolderManager;
        $this->roleManager = $roleManager;
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
        $this->paasManager = $container->get('bns.paas_without_request_manager');
        $this->translator = $container->get('translator');

        // for compatibility with BNS User model
        if (!BNSAccess::getContainer()) {
            BNSAccess::setContainer($container);
        }
    }

    public function process($data)
    {
        $this->accountLinkLogger->info('Starting recovery process', $data);
        $completeSuccess = true;

        if (isset($data['user'])) {
            try {
                $success = $this->processUser($data['user']);
                $completeSuccess = $completeSuccess && $success;
            } catch (InvalidDataException $e) {
                $completeSuccess = false;
                // carry on with other processes
            }
        }

        if (isset($data['school'])) {
            try {
                $success = $this->processGroup($data['school'], 'school');
                $completeSuccess = $completeSuccess && $success;
            } catch (InvalidDataException $e) {
                $completeSuccess = false;
                // carry on with other processes
            }
        }

        if (isset($data['classroom'])) {
            try {
                $success = $this->processGroup($data['classroom'], 'classroom');
                $completeSuccess = $completeSuccess && $success;
            } catch (InvalidDataException $e) {
                $completeSuccess = false;
                // carry on with other processes
            }

            if (isset($data['classroom']['pupils'])) {
                try {
                    $success = $this->processPupils($data['classroom']);
                    $completeSuccess = $completeSuccess && $success;
                } catch (InvalidDataException $e) {
                    $completeSuccess = false;
                    // carry on with other processes
                }
            }
        }

        $this->accountLinkLogger->info('Finished recovery process', ['success' => $completeSuccess]);

        return $completeSuccess;
    }

    public function processUser($config)
    {
        // validate data
        if (isset($config['new_id'])) {
            $newUser = $this->getUser($config['new_id']);
        } else {
            return $this->errorOut('Missing new user id', $config);
        }
        if (isset($config['old_id'])) {
            if ($config['new_id'] == $config['old_id']) {
                return $this->errorOut('New and old users have same id', $config);
            }
            $oldUser = $this->getUser($config['old_id']);
        } else {
            return $this->errorOut('Missing old user id', $config);
        }

        // recover apps
        if (isset($config['data'])) {
            foreach ($config['data'] as $appName) {
                try {
                    $this->recoverUserAppData($newUser, $oldUser, $appName);
                    $this->accountLinkLogger->info('Recovered user app: '.$appName);
                } catch (InvalidDataException $e) {
                    $this->accountLinkLogger->warning('Unknown user app', ['app'=>$appName]);
                } catch (\Exception $e) {
                    $this->accountLinkLogger->error('Error while recovering user app', [
                        'app' => $appName,
                        'error' => $e->getMessage(),
                    ]);

                    throw $e;
                }
            }
        } else {
            $this->accountLinkLogger->info('No user app to recover');
        }

        $this->accountLinkLogger->info('User recovery finished');

        return true;
    }

    public function processGroup($config, $type)
    {
        // validate data
        if (!in_array($type, ['classroom', 'school'])) {
            return $this->errorOut('Invalid group type', ['type', $type]);
        }
        if (isset($config['new_id'])) {
            $newGroup = $this->getGroup($config['new_id'], $type);
        } else {
            return $this->errorOut(sprintf('Missing new %s id', $type), $config);
        }
        if (isset($config['old_id'])) {
            if ($config['new_id'] == $config['old_id']) {
                return $this->errorOut(sprintf('New and old %s have same id', $type), $config);
            }
            $oldGroup = $this->getGroup($config['old_id'], $type);
        } else {
            return $this->errorOut(sprintf('Missing old %s id', $type), $config);
        }

        // recover subscriptions
        $this->accountLinkLogger->info(sprintf('Start %s recovery', $type));
        $this->paasManager->moveGroupSubscriptions($oldGroup->getId(), $newGroup->getId(), $type);
        $this->accountLinkLogger->info(sprintf('Recovered %s paas subscriptions', $type));

        // recovers apps
        if (isset($config['data'])) {
            foreach ($config['data'] as $appName) {
                try {
                    $this->recoverGroupAppData($newGroup, $oldGroup, $appName);
                    $this->accountLinkLogger->info(sprintf('Recovered %s app: %s', $type, $appName));
                } catch (InvalidDataException $e) {
                    $this->accountLinkLogger->warning(sprintf('Unknown %s app', $type), ['app' => $appName]);
                } catch (\Exception $e) {
                    $this->accountLinkLogger->error(sprintf('Error while recovering %s app', $type), [
                        'app' => $appName,
                        'error' => $e->getMessage()
                    ]);

                    throw $e;
                }
            }
        } else {
            $this->accountLinkLogger->info(sprintf('No %s app to recover', $type));
        }

        $this->markGroupAsRecovered($newGroup);
        $this->markGroupAsRecovered($oldGroup);

        $this->accountLinkLogger->info(sprintf('Finished %s recovery', $type));

        return true;
    }

    public function processPupils($config)
    {
        // validate data
        $type = 'classroom';
        $success = true;
        if (isset($config['new_id'])) {
            $newGroup = $this->getGroup($config['new_id'], $type);
        } else {
            return $this->errorOut(sprintf('Missing new %s id', $type), $config);
        }
        if (isset($config['old_id'])) {
            if ($config['new_id'] == $config['old_id']) {
                return $this->errorOut(sprintf('New and old %s have same id', $type), $config);
            }
            $oldGroup = $this->getGroup($config['old_id'], $type);
        } else {
            return $this->errorOut(sprintf('Missing old %s id', $type), $config);
        }
        if (isset($config['pupils']) && is_array($config['pupils'])) {
            $this->accountLinkLogger->info('Start pupils recovery', $config['pupils']);
            $successes = $this->recoverPupils($newGroup, $oldGroup, $config['pupils']);
            $success = count($config['pupils']) === $successes;
            $this->accountLinkLogger->info('Finished pupil recovery');
        } else {
            $this->accountLinkLogger->info('No pupils to recover');
        }

        return $success;
    }

    public function markGroupAsRecovered(Group $group)
    {
        $group->setAafLinked(true)->save();
    }

    public function recoverPaasSubscriptions($newGroupId, $oldGroupId)
    {
        $newGroup = GroupQuery::create()->joinWith('GroupType')->findPk($newGroupId);
        $oldGroup = GroupQuery::create()->joinWith('GroupType')->findPk($oldGroupId);
        if (!$newGroup) {
            throw new \InvalidArgumentException('No group found for id: ' . $newGroupId);
        }
        if (!$oldGroup) {
            throw new \InvalidArgumentException('No group found for id: ' . $oldGroupId);
        }

        $this->paasManager->moveClassroomSubscriptions($oldGroupId, $newGroupId);
    }

    public function recoverGroupAppData(Group $newGroup, Group $oldGroup, $appName)
    {
        switch ($appName) {
            case 'BLOG':
                $this->installApplication('BLOG', $newGroup, $oldGroup);
                $this->recoverGroupBlog($newGroup, $oldGroup);
                break;
            case 'CALENDAR':
                $this->installApplication('CALENDAR', $newGroup, $oldGroup);
                $this->recoverGroupCalendar($newGroup, $oldGroup);
                break;
            case 'HOMEWORK':
                $this->installApplication('HOMEWORK', $newGroup, $oldGroup);
                $this->recoverGroupHomework($newGroup, $oldGroup);
                break;
            case 'LIAISONBOOK':
                $this->installApplication('LIAISONBOOK', $newGroup, $oldGroup);
                $this->recoverGroupLiaisonbook($newGroup, $oldGroup);
                break;
            case 'MEDIA_LIBRARY':
                $this->installApplication('MEDIA_LIBRARY', $newGroup, $oldGroup);
                $this->recoverGroupMediaLibrary($newGroup, $oldGroup);
                break;
            case 'MINISITE':
                $this->installApplication('MINISITE', $newGroup, $oldGroup);
                $this->recoverGroupMinisite($newGroup, $oldGroup);
                break;
            default:
                throw new InvalidDataException('Unknown app to recover: ' . $appName);
        }

        // there are no listeners when called by worker, so clear cache manually
        $this->api->resetGroupUsers($newGroup->getId(), true);
    }

    public function recoverUserAppData(User $newUser, User $oldUser, $appName)
    {
        switch ($appName) {
            case 'MEDIA_LIBRARY':
                $this->recoverUserMediaLibrary($newUser, $oldUser);
                break;
            case 'MESSAGING':
                $this->recoverUserMessaging($newUser, $oldUser);
                break;
            case 'PROFILE':
                $this->recoverUserProfile($newUser, $oldUser);
                break;
            default:
                throw new InvalidDataException('Unknown app to recover: ' . $appName);
        }
    }

    /**
     * @param Group $newGroup
     * @param Group $oldGroup
     * @param array $pupilsMap
     * @return int the number of pupils recovered
     */
    public function recoverPupils(Group $newGroup, Group $oldGroup, $pupilsMap)
    {
        $successes = 0;
        foreach ($pupilsMap as $newId => $oldId) {
            try {
                $newPupil = $this->getUser($newId);
                $oldPupil = $this->getUser($oldId);
                $this->recoverPupil($newGroup, $oldGroup, $newPupil, $oldPupil);
                $this->accountLinkLogger->info('Recovered pupil', ['new_id' => $newId, 'old_id' => $oldId]);
                $successes++;
            } catch (\Exception $e) {
                // error while recovering pupil, log it and proceed with next one
                $this->accountLinkLogger->error('Error while recovering pupil', [
                    'new_id' => $newId,
                    'old_id' => $oldId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $successes;
    }

    public function linkParents($newParentId, $oldParentId)
    {
        $newParent = UserQuery::create()->findPk($newParentId);
        $oldParent = UserQuery::create()->findPk($oldParentId);
        if (!$newParent) {
            throw new \InvalidArgumentException('No user found for id: ' . $newParentId);
        }
        if (!$oldParent) {
            throw new \InvalidArgumentException('No user found for id: ' . $oldParentId);
        }

        $oldParent->setAafId($newParent->getAafId());
        $oldParent->setAafAcademy($newParent->getAafAcademy());
        $oldParent->setFirstName($newParent->getFirstName());
        $oldParent->setLastName($newParent->getLastName());
        $oldParent->setGender($newParent->getGender());
        if ($newParent->getEmail()) {
            $oldParent->setEmail($newParent->getEmail());
        }
        $oldParent->setAafLinked(true);
        $oldParent->save();
        $newParent->setAafAcademy(null);
        $newParent->setAafId(null);
        $newParent->save();

        $this->userManager->deleteUser($newParent);

        return true;
    }

    /**
     * Installs the application in the given group, if it was installed in the old group.
     *
     * @param $applicationName
     * @param $group
     * @param $oldGroup
     */
    protected function installApplication($applicationName, $group, $oldGroup)
    {
        try {
            $apps = $this->applicationManager->getInstalledApplications($oldGroup);
            foreach ($apps as $app) {
                if ($app->getUniqueName() === $applicationName) {
                    $this->applicationManager->installApplication($applicationName, $group);
                }
                break;
            }
        } catch (InvalidInstallApplication $e) {
            // trying to install base or system app, ignore
        }
    }

    protected function recoverGroupBlog(Group $newGroup, Group $oldGroup)
    {
        $newBlog = $newGroup->getBlog();
        $oldBlog = $oldGroup->getBlog();

        $con = \Propel::getConnection(UserPeer::DATABASE_NAME);
//        $con->beginTransaction();
        try {
            // detach articles from all current blogs, and add them to the new blog
            foreach ($oldBlog->getBlogArticles(null, $con) as $article) {
                if ($article->getBlogReferenceId() === $oldBlog->getId()) {
                    $article->setBlogReferenceId($newBlog->getId());
                }
                foreach ($article->getBlogs(null, $con) as $blogToRemove) {
                    $article->removeBlog($blogToRemove);
                }
                $article->addBlog($newBlog);
                $article->save($con, true);
            }

            // move comments to the new blog (relation to article is untouched)
            foreach ($oldBlog->getBlogArticleComments(null, $con) as $comment) {
                $comment->setBlog($newBlog);
                $comment->save($con, true);
            }

            // move categories to the new blog
            $newRootCategory = $newBlog->getRootCategory($con);
            $oldRootCategory = $oldBlog->getRootCategory($con);
            /** @var BlogCategory $category */
            foreach ($oldRootCategory->getChildren(null, $con) as $category) {
                $category->moveToLastChildOf($newRootCategory, $con);
            }

//            $con->commit();
        } catch (\Exception $e) {
//            $con->rollBack();
            throw $e;
        }
    }

    protected function recoverGroupCalendar(Group $newGroup, Group $oldGroup)
    {
        $newAgenda = $newGroup->getAgenda();
        $oldAgenda = $oldGroup->getAgenda();

        $con = \Propel::getConnection(UserPeer::DATABASE_NAME);
//        $con->beginTransaction();
        try {
            foreach ($oldAgenda->getAgendaEvents(null, $con) as $agendaEvent) {
                $agendaEvent->setAgenda($newAgenda);
                $agendaEvent->save($con, true);
            }
//            $con->commit();
        } catch (\Exception $e) {
//            $con->rollBack();
            throw $e;
        }
    }

    protected function recoverGroupHomework(Group $newGroup, Group $oldGroup)
    {
        $newMediaFolderGroupRoot = null;
        $con = \Propel::getConnection(UserPeer::DATABASE_NAME);
//        $con->beginTransaction();
        try {
            // Detach homeworks from all current groups, and add them to the new group.
            // HomeworkDue and HomeworkTask are untouched.
            foreach ($oldGroup->getHomeworks(null, $con) as $homework) {
                foreach ($homework->getGroups(null, $con) as $groupToRemove) {
                    $homework->removeGroup($groupToRemove);
                }
                $homework->addGroup($newGroup);

                if ($homework->getHasLocker()) {
                    $folder = MediaFolderGroupQuery::create()
                        ->filterByIsLocker(true)
                        ->filterByHomework($homework)
                        ->filterByGroup($oldGroup)
                        ->filterByStatusDeletion(MediaManager::STATUS_ACTIVE)
                        ->findOne($con)
                    ;
                    if ($folder) {
                        $newMediaFolderGroupRoot = $newMediaFolderGroupRoot ?: $newGroup->getMediaFolderRoot($con);
                        $folder->moveToLastChildOf($newMediaFolderGroupRoot, $con);
                    }
                }
                $homework->save($con);
            }

            // move subjects to the new group
            $newRootSubject = HomeworkSubject::fetchRoot($newGroup->getId(), $con);
            $oldRootSubject = HomeworkSubject::fetchRoot($oldGroup->getId(), $con);
            /** @var HomeworkSubject $subject */
            foreach ($oldRootSubject->getChildren(null, $con) as $subject) {
                $subject->moveToLastChildOf($newRootSubject, $con);
            }

            // copy preferences
            $newPreferences = HomeworkPreferencesQuery::create()->findOrInit($newGroup->getId(), $con);
            $oldPreferences = HomeworkPreferencesQuery::create()->findOrInit($oldGroup->getId(), $con);
            $newPreferences->setDays($oldPreferences->getDays());
            $newPreferences->setActivateValidation($oldPreferences->getActivateValidation());
            $newPreferences->setShowTasksDone($oldPreferences->getShowTasksDone());
            $newPreferences->save($con);

//            $con->commit();
        } catch (\Exception $e) {
//            $con->rollBack();
            throw $e;
        }
    }

    protected function recoverGroupLiaisonbook(Group $newGroup, Group $oldGroup)
    {
        $con = \Propel::getConnection(UserPeer::DATABASE_NAME);
//        $con->beginTransaction();
        try {
            foreach ($oldGroup->getLiaisonBooks(null, $con) as $liaisonBook) {
                $liaisonBook->setGroupId($newGroup->getId());
                $liaisonBook->save($con, true);
            }
//            $con->commit();
        } catch (\Exception $e) {
//            $con->rollBack();
            throw $e;
        }
    }

    protected function recoverGroupMediaLibrary(Group $newGroup, Group $oldGroup)
    {
        try {
            $oldRoot = $oldGroup->getMediaFolderRoot();
            $newRoot = $newGroup->getMediaFolderRoot();
            $oldFolders = MediaFolderGroupQuery::create()
                ->filterByGroup($oldGroup)
                ->filterByIsUserFolder(false)
                ->filterByIsLocker(false)
                ->filterByTreeLevel(1)
                ->find()
            ;

            $this->moveFolders($newRoot, $oldRoot, $oldFolders);

            // update size of the new folder root
            $newGroup->addResourceSize($oldGroup->getResourceUsedSize());
        } catch (\Exception $e) {
            throw $e;
        }
    }

    protected function recoverGroupMinisite(Group $newGroup, Group $oldGroup)
    {
        $con = \Propel::getConnection(UserPeer::DATABASE_NAME);
//        $con->beginTransaction();
        try {
            /** @var MiniSite $oldMiniSite */
            $oldMiniSite = $oldGroup->getMiniSites(null, $con)->getFirst();
            if (!$oldMiniSite) {
                // no old minisite to recover
                return;
            }
            /** @var MiniSite $newMiniSite */
            $newMiniSite = $newGroup->getMiniSites(null, $con)->getFirst();
            if (!$newMiniSite) {
                $newMiniSite = new MiniSite();
                $newMiniSite->setGroupId($newGroup->getId());
                $newMiniSite->setTitle($this->translator->trans('TITLE_SITE_NAME', ['%name%' => $newGroup->getLabel()], 'MINISITE'));
                $newMiniSite->save($con);

                // Create the homepage, many queries are based on the homepage
                $homePage = new MiniSitePage();
                $homePage->setMiniSite($newMiniSite);
                $homePage->setTitle($this->translator->trans('TITLE_WELCOME', [], 'MINISITE'));
                $homePage->setIsActivated(true);
                $homePage->setType(MiniSitePagePeer::TYPE_TEXT);
                $homePage->setIsHome(true);
                $homePage->save($con);
            }

            foreach ($oldMiniSite->getMiniSitePages(null, $con) as $page) {
                if ($page->isHome()) {
                    continue;
                }

                $page->setMiniSite($newMiniSite);
                $page->save($con);
            }

            foreach ($oldMiniSite->getMiniSiteWidgets(null, $con) as $widget) {
                $widget->setMiniSite($newMiniSite);
                $widget->save($con);
            }

//            $con->commit();
        } catch (\Exception $e) {
//            $con->rollBack();
            throw $e;
        }
    }

    protected function recoverUserMediaLibrary(User $newUser, User $oldUser)
    {
        $con = \Propel::getConnection(UserPeer::DATABASE_NAME);
//        $con->beginTransaction();
        try {
            $oldRoot = $oldUser->getMediaFolderRoot();
            $newRoot = $newUser->getMediaFolderRoot();
            $oldFolders = MediaFolderUserQuery::create()
                ->filterByUserId($oldUser->getId())
                ->filterByTreeLevel(1)
                ->find($con)
            ;

            $this->moveFolders($newRoot, $oldRoot, $oldFolders, $con);
//            $con->commit();

            // update size of the new folder root
            $newUser->addResourceSize($oldUser->getResourceUsedSize());
        } catch (\Exception $e) {
//            $con->rollBack();
            throw $e;
        }
    }

    protected function recoverUserMessaging(User $newUser, User $oldUser)
    {
        $con = \Propel::getConnection(UserPeer::DATABASE_NAME);
//        $con->beginTransaction();
        try {
            // array [ column => select criteria ]
            $queries = [
                MessagingMessagePeer::AUTHOR_ID => MessagingMessageQuery::create()
                    ->filterByAuthorId($oldUser->getId()),
                MessagingConversationPeer::USER_ID => MessagingConversationQuery::create()
                    ->filterByUserId($oldUser->getId()),
                MessagingConversationPeer::USER_WITH_ID => MessagingConversationQuery::create()
                    ->filterByUserWithId($oldUser->getId()),
            ];
            foreach ($queries as $column => $select) {
                $update = new \Criteria();
                $update->add($column, $newUser->getId());
                \BasePeer::doUpdate($select, $update, $con);
            }

//            $con->commit();
        } catch (\Exception $e) {
//            $con->rollBack();
            throw $e;
        }
    }

    protected function recoverUserProfile(User $newUser, User $oldUser)
    {
        $con = \Propel::getConnection(UserPeer::DATABASE_NAME);
//        $con->beginTransaction();
        try {
            $oldProfile = $oldUser->getProfile();
            $newProfile = $newUser->getProfile();

            // copy profile info
            $newProfile->setJob($oldProfile->getJob());
            $newProfile->setDescription($oldProfile->getDescription());
            if ($oldProfile->getAvatarId()) {
                $newProfile->setAvatarId($oldProfile->getAvatarId());
            }

            // move profile feeds
            foreach ($oldProfile->getProfileFeeds() as $profileFeed) {
                $profileFeed->setProfile($newProfile);
            }
            $newProfile->setNbFeeds($newProfile->getNbFeeds() + $oldProfile->getNbFeeds());

            // move profile preferences
            foreach ($oldProfile->getProfilePreferences() as $profilePreference) {
                $profilePreference->setProfile($newProfile);
            }

            $newProfile->save($con);
//            $con->commit();
        } catch (\Exception $e) {
//            $con->rollBack();
            throw $e;
        }
    }

    /**
     * Folder-type agnostic utility to move folders from one root to another.
     *
     * @param MediaFolderGroup|MediaFolderUser $newRoot
     * @param MediaFolderGroup|MediaFolderUser $oldRoot
     * @param MediaFolderGroup[]|MediaFolderUser[] $folders
     * @param \PDO $con
     */
    protected function moveFolders($newRoot, $oldRoot, $folders, $con = null)
    {
        // move folders to the new root (medias inside these folders are moved along)
        foreach ($folders as $folder) {
            if ($folder->isInTree()) {
                $folder->moveToLastChildOf($newRoot, $con);
            } else {
                $folder->insertAsLastChildOf($newRoot);
                $folder->save($con);
            }
        }

        // move medias in the old root to the new root
        /** @var Media[] $oldMedias */
        $oldMedias = MediaQuery::create()
            ->filterByMediaFolderId($oldRoot->getId())
            ->filterByMediaFolderType($oldRoot->getType())
            ->find($con)
        ;
        foreach ($oldMedias as $media) {
            $media->setMediaFolderId($newRoot->getId());
            $media->save($con);
        }
    }

    protected function recoverPupil(Group $newGroup, Group $oldGroup, User $newPupil, User $oldPupil, LoggerInterface $logger = null)
    {
        try {
            // copy new data to old pupil
            $oldPupil->setAafAcademy($newPupil->getAafAcademy());
            $oldPupil->setAafId($newPupil->getAafId());
            $oldPupil->save();
            $newPupil->setAafAcademy(null);
            $newPupil->setAafId(null);
            $newPupil->save();

            if ($logger) {
                $logger->info('swapped aaf id');
            }

            // move old pupil to new classroom
            $this->moveUser($oldPupil, 'PUPIL', $newGroup, $oldGroup);
            if ($logger) {
                $logger->info('moved pupil to new classroom');
            }

            // move old parents to new classroom
            foreach ($oldPupil->getParents() as $oldParent) {
                $this->moveUser($oldParent, 'PARENT', $newGroup, $oldGroup);
                if ($logger) {
                    $logger->info('moved a parent to new classroom');
                }
            }
            if ($logger) {
                $logger->info('finished moving parents');
            }

            // add new parents to old pupil
            foreach ($newPupil->getParents() as $newParent) {
                $this->userManager->addParent($oldPupil, $newParent);
                if ($logger) {
                    $logger->info('linked a parent');
                }
            }
            if ($logger) {
                $logger->info('finished linking parents');
            }

            // delete new pupil
            $this->userManager->deleteUser($newPupil);
            if ($logger) {
                $logger->info('deleted new pupil');
            }

        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Moves user with role to a group from another group.
     *
     * @param User $user
     * @param string $role
     * @param Group $toGroup
     * @param Group $fromGroup
     * @throws \Exception
     */
    protected function moveUser(User $user, $role, Group $toGroup, Group $fromGroup)
    {
        // remove user role all the way up to the environment
        $environment = $this->groupManager->setGroup($fromGroup)->getEnvironment();
        if (!$environment) {
            throw new \Exception('User not in environment: '.$user->getId());
        }
        $this->groupManager->setGroup($environment);
        $this->groupManager->removeUser($user, $role);

        // user may have been archived, restore it
        $this->userManager->restoreUser($user);

        // finally assign role in destination group
        $this->roleManager->setGroupTypeRoleFromType($role);
        $this->roleManager->assignRole($user, $toGroup->getId());
    }

    /**
     * @param $message
     * @param array $context
     * @throws InvalidDataException
     * @return null
     */
    protected function errorOut($message, $context = [])
    {
        if (!is_array($context)) {
            $context = [];
        }
        $this->accountLinkLogger->error($message, $context);

        throw new InvalidDataException($message);
    }

    protected function getUser($id)
    {
        $user = UserQuery::create()
            ->filterById($id)
            ->findOne()
        ;
        if (!$user) {
            $this->errorOut('Given user id not found', ['id' => $id]);
        }

        return $user;
    }

    protected function getGroup($id, $type)
    {
        $group = GroupQuery::create()
            ->filterById($id)
            ->useGroupTypeQuery()
                ->filterByType(strtoupper($type))
            ->endUse()
            ->findOne()
        ;
        if (!$group) {
            $this->errorOut('Given group not found for id and type', ['id' => $id, 'type' => $type]);
        }

        return $group;
    }

}
