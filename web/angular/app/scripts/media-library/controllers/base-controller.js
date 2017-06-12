'use strict';

angular.module('bns.mediaLibrary')

  /**
   * @ngdoc controller
   * @name MediaLibraryBaseCtrl
   * @kind function
   *
   * @description
   * The media library base controller, handles common data.
   *
   * **Methods**
   * - `loadLibrary()` - loads the media library of the current user
   *
   * @returns {Object} The media library base controller
   *
   * @requires $scope
   * @requires $rootScope
   * @requires $window
   * @requires $state
   * @requires mediaLibraryManager
   * @requires mediaLibraryConfig
   * @requires mediaLibraryUploader
   * @requires MediaLibraryRestangular
   * @requires mediaLibraryAPI
   * @requires mediaLibraryAddFolderModal
   * @requires mediaLibraryAddUrlModal
   * @requires mediaLibraryRenameFolderModal
   * @requires mediaLibraryFilesFilter
   * @requires CollectionMap
   * @requires objectHelpers
   * @requires message
   */
  .controller('MediaLibraryBaseCtrl', function ($scope, $rootScope, $window, $state, mediaLibraryManager, mediaLibraryConfig, mediaLibraryUploader, MediaLibraryRestangular, mediaLibraryAPI, mediaLibraryAddFolderModal, mediaLibraryAddUrlModal, mediaLibraryRenameFolderModal, mediaLibraryFilesFilter, CollectionMap, objectHelpers, message) {
    var ctrl = this;

    ctrl.init = function () {
      $rootScope.lastFolderHistory = null;

      // holder for shared objects
      $scope.shared = {};

      // CollectionMap of selected items
      $scope.shared.selection = new CollectionMap([], 'unique_key');

      // Whether current selection is valid
      $scope.shared.selectionValid = true;

      // Whether selection is enabled (i.e. checkboxes are visible)
      $scope.shared.selectionEnabled = true;

      // CollectionMap of context-specific selection
      $scope.shared.contextSelection = null;

      // Cache here whether all items in selection are favorites
      $scope.shared.selectionIsAllFavorites = false;

      // Cache here whether all items in selection are private
      $scope.shared.selectionIsAllPrivates = false;

      // Cache here whether some items in selection are manageable
      $scope.shared.selectionHasManageables = false;

      // Cache here whether all items in selection are writable
      $scope.shared.selectionIsAllWritables = false;

      // Cache here whether selection has some files
      $scope.shared.selectionHasFiles = false;

      // Cache here whether selection has some folders
      $scope.shared.selectionHasFolders = false;

      // Cache here whether all items in selection are files
      $scope.shared.selectionIsAllFiles = false;

      // Cache here whether all items in selection are folders
      $scope.shared.selectionIsAllFolders = false;

      // CollectionMap, lazily fed with viewed medias to keep them in cache
      $scope.shared.mediaCache = new CollectionMap([], 'id');

      // need a reference to the uploader, for dropzone
      $scope.uploader = mediaLibraryUploader;

      $scope.isSelectionMode = 'selection' === mediaLibraryConfig.mode;
      $scope.isViewMode = 'view' === mediaLibraryConfig.mode;
      $scope.submode = mediaLibraryConfig.submode;

      this.library = MediaLibraryRestangular.one('base-init', '');
    };

    ctrl.loadLibrary = function () {
      if (ctrl.loadingLibrary) {
        return;
      }

      ctrl.loadingLibrary = true;
      this.library.get()
        .then(ctrl.getBaseInitSuccess, ctrl.getBaseInitError)
      ;
    };

    ctrl.getBaseInitSuccess = function (library) {
      mediaLibraryManager.setupSpecialFolders(library);
      $scope.shared.library = library;
      $scope.$broadcast('mediaLibrary.loaded');

      ctrl.getBaseInitFinally();
    };

    ctrl.getBaseInitError = function (result) {
      console.error('getBaseInit', result);
      message.error('MEDIA_LIBRARY.INIT_ERROR');

      ctrl.getBaseInitFinally();
    };

    ctrl.getBaseInitFinally = function () {
      ctrl.loadingLibrary = false;
    };

    ctrl.addMediaFolder = function (folder) {
      if (!($scope.shared.context && $scope.shared.canCreate)) {
        console.warn('Trying to add folder without context');
        return;
      }
      $scope.shared.context.children.push(folder);
      $scope.$broadcast('mediaLibrary.folders.changed');
    };

    ctrl.getMediaFolderSuccess = function (folder) {
      if (!$scope.shared.library) {
        return;
      }

      // merge fresh folder with the one already in the model
      var target;

      // chances are, current folder is still the one being refreshed
      if ($scope.shared.context.unique_key === folder.unique_key) {
        target = $scope.shared.context;
      }

      // if not, find it in the library
      if (!target) {
        target = mediaLibraryManager.findFolder($scope.shared.library, folder);
      }

      if (target) {
        // better than angular.extend(target, folder);
        objectHelpers.softMerge(target, folder, 'unique_key');

        // check rights again with fresh data
        ctrl.checkRights();

        ctrl.getMediaFolderFinally(target);
      }
    };

    ctrl.getTrashFolderSuccess = function (response) {
      if (!$scope.shared.library) {
        return;
      }

      // build a fake folder with new content, to be merged
      var toMerge = {
        children: response.MEDIA_FOLDERS,
        medias: response.MEDIAS,
      };
      objectHelpers.softMerge($scope.shared.library.trash_folder, toMerge, 'unique_key');

      ctrl.getMediaFolderFinally($scope.shared.library.trash_folder);
    };

    ctrl.getExternalFolderSuccess = function (response) {
      if (!$scope.shared.library) {
        return;
      }

      // build a fake folder with new content, to be merged
      var toMerge = {
        medias: response,
      };
      objectHelpers.softMerge($scope.shared.library.external_folder, toMerge, 'unique_key');

      ctrl.getMediaFolderFinally($scope.shared.context);
    };

    ctrl.getFavoritesFolderSuccess = function (response) {
      if (!$scope.shared.library) {
        return;
      }

      // build a fake folder with new content, to be merged
      var toMerge = {
        children: response.MEDIA_FOLDERS,
        medias: response.MEDIAS,
      };
      objectHelpers.softMerge($scope.shared.library.favorites_folder, toMerge, 'unique_key');

      ctrl.getMediaFolderFinally($scope.shared.library.favorites_folder);
    };

    ctrl.getRecentsFolderSuccess = function (response) {
      if (!$scope.shared.library) {
        return;
      }

      // build a fake folder with new content, to be merged
      var toMerge = {
        medias: response,
      };
      objectHelpers.softMerge($scope.shared.library.recents_folder, toMerge, 'unique_key');

      ctrl.getMediaFolderFinally($scope.shared.library.recents_folder);
    };

    ctrl.getMediaFolderError = function (result) {
      message.error('MEDIA_LIBRARY.GET_FOLDER_ERROR');
      console.error('getMediaFolder', result);

      // with a little luck, context has not changed since request was fired
      ctrl.getMediaFolderFinally($scope.shared.context);
    };

    ctrl.getMediaFolderFinally = function (folder) {
      folder.loaded = true;
    };

    ctrl.addMedia = function (media, target) {
      target = target ? target : $scope.shared.context;

      if (!(target && mediaLibraryManager.canCreate(target))) {
        console.warn('Cannot add media here', target);
        return;
      }
      target.medias.push(media);
      // update root folder usage
      if (media.size) {
        var targetRoot = $scope.shared.userTree.getRoot(target);
        if (!targetRoot) {
          targetRoot = $scope.shared.groupTree.getRoot(target);
        }
        if (targetRoot && targetRoot.usage) {
          targetRoot.usage.current = parseInt(targetRoot.usage.current) + parseInt(media.size);
        }
      }
    };

    ctrl.getMediaError = function (response) {
      message.error('VIEWER.GET_MEDIA_ERROR');
      console.error('getMedia', response);
    };

    ctrl.moveMedia = function (media, dest) {
      // update local model
      if (mediaLibraryManager.canReadContent(dest)) {
        if (!dest.medias) {
          dest.medias = [];
        }
        dest.medias.push(media);
      }

      // messagey API. If ok: nothing to do, else reload
      var patchData = {};
      patchData['parent-marker'] = dest.marker;
      MediaLibraryRestangular.one('media', media.id)
        .one('move', '')
        .patch(patchData)
        .then(ctrl.patchMediaMoveSuccess, ctrl.patchMediaMoveError)
      ;
    };

    ctrl.patchMediaMoveSuccess = function () {
      message.success('MEDIA_LIBRARY.MOVE_MEDIA_SUCCESS');
    };

    ctrl.patchMediaMoveError = function (response) {
      console.error(response);
      message.error('MEDIA_LIBRARY.MOVE_MEDIA_ERROR');
      ctrl.loadLibrary();
    };

    ctrl.moveFolder = function (folder, dest) {
      // update local model
      if (!dest.children) {
        dest.children = [];
      }
      dest.children.push(folder);
      $scope.$broadcast('mediaLibrary.folders.changed');

      // Notify API. If ok: nothing to do, else reload
      var patchData = {};
      patchData['parent-marker'] = dest.marker;
      MediaLibraryRestangular.one('media-folder', folder.marker)
        .one('move', '')
        .patch(patchData)
        .then(ctrl.patchMediaFolderMoveSuccess, ctrl.patchMediaFolderMoveError)
      ;
    };

    ctrl.patchMediaFolderMoveSuccess = function () {
      message.success('MEDIA_LIBRARY.MOVE_MEDIA_FOLDER_SUCCESS');
    };

    ctrl.patchMediaFolderMoveError = function (response) {
      console.error(response);
      message.error('MEDIA_LIBRARY.MOVE_MEDIA_FOLDER_ERROR');
      ctrl.loadLibrary();
    };

    ctrl.refreshContext = function () {
      if (!$scope.shared.context) {
        console.warn('Cannot refresh without context');
        return;
      }

      $scope.shared.context.loaded = false;

      if (mediaLibraryManager.isTrash($scope.shared.context)) {
        MediaLibraryRestangular.one('base-corbeille', '')
          .get()
          .then(ctrl.getTrashFolderSuccess, ctrl.getMediaFolderError)
        ;
      // } else if (mediaLibraryManager.isExternal($scope.shared.context)) {
      //   MediaLibraryRestangular.one('base-external', '')
      //     .get()
      //     .then(ctrl.getExternalFolderSuccess, ctrl.getMediaFolderError)
      //   ;
      } else if (mediaLibraryManager.isFavorites($scope.shared.context)) {
        MediaLibraryRestangular.one('base-favoris', '')
          .get()
          .then(ctrl.getFavoritesFolderSuccess, ctrl.getMediaFolderError)
        ;
      } else if (mediaLibraryManager.isRecents($scope.shared.context)) {
        MediaLibraryRestangular.one('base-recents', '')
          .get()
          .then(ctrl.getRecentsFolderSuccess, ctrl.getMediaFolderError)
        ;
      } else if (!$scope.shared.context.id) {
        // virtual folder, keep current content
        ctrl.getMediaFolderSuccess($scope.shared.context);
      } else {
        MediaLibraryRestangular.one('media-folder', $scope.shared.context.marker)
          .get()
          .then(ctrl.getMediaFolderSuccess, ctrl.getMediaFolderError)
        ;
      }
    };

    ctrl.checkRights = function () {
      $scope.shared.canUpload = mediaLibraryManager.canUpload($scope.shared.context);
      $scope.shared.canCreate = mediaLibraryManager.canCreate($scope.shared.context);
      $scope.shared.canWrite = mediaLibraryManager.canWrite($scope.shared.context);
      $scope.shared.canToggleLocker = mediaLibraryManager.canToggleLocker($scope.shared.context);
      $scope.shared.isTrash = mediaLibraryManager.isTrash($scope.shared.context);
      $scope.shared.globalSelectionEnabled = !$scope.shared.isTrash;
    };

    ctrl.init();

    /**
     * Sets the media-library context to the given model object (typically a
     * folder)
     *
     * @param {Object} model
     */
    $scope.setContext = function (model) {
      $scope.shared.context = model;
      $rootScope.lastFolderHistory = model;
      $rootScope.$emit('mediaLibrary.context.changed', model);
    };

    /**
     * Toggles selection state for the given item, i.e. adds or removes it from
     * the collection.
     *
     * @param {Object} item
     */
    $scope.toggleSelection = function (item) {
      var hasItem = $scope.shared.selection.toggle(item);
      if (hasItem) {
        $scope.$broadcast('mediaLibrary.selection.added', item);
      } else {
        $scope.$broadcast('mediaLibrary.selection.removed', item);
      }
      $scope.$broadcast('mediaLibrary.selection.changed', item);
    };

    /**
     * Controls whether an item can be selected, i.e. it has a checkbox, can be
     * in the selection and can be joined as a resource.
     *
     * @param {Object} item The item to test (file/folder)
     * @returns {Boolean}
     */
    $scope.isSelectable = function (item) {
      // system items are not to be toyed with!
      if (mediaLibraryManager.isSystem(item)) {
        return false;
      }

      return $scope.isSelectionEnabled(item);
    };

    /**
     * Controls whether an item is visuellay enabled for selection
     *
     * @param {Object} item The item to test (file/folder)
     * @returns {Boolean}
     */
    $scope.isSelectionEnabled = function (item) {
      // items that are already in selection are de-facto selectable
      if ($scope.shared.selection.has(item)) {
        return true;
      }

      // selection is globally disabled
      if (!$scope.shared.selectionEnabled) {
        return false;
      }

      if (mediaLibraryManager.isFromPaas(item)) {
        // PaaS medias are not available in selection mode
        return 'selection' !== mediaLibraryConfig.mode;
      }

      if (mediaLibraryManager.isFolder(item)) {
        // folders are not available when in selection mode
        return 'selection' !== mediaLibraryConfig.mode;
      } else {
        if (!mediaLibraryConfig.type || 'ALL' === mediaLibraryConfig.type) {
          return true;
        }

        return (mediaLibraryConfig.type === item.type_unique_name);
      }
    };

    $scope.dropCallback = function (from, to, item) {
      if (to.model.unique_key === item.unique_key) {
        return false;
      }

      // borrow nav structure to check if drop in child
      if ($scope.shared.userTree.isAncestor(to.model, item)) {
        return false;
      } else if ($scope.shared.groupTree.isAncestor(to.model, item)) {
        return false;
      }

      if (angular.isArray(from.model)) {
        from.scope.$apply(function () {
          from.model.splice(from.index, 1);
        });
        to.scope.$apply(function () {
          if (mediaLibraryManager.isFile(item)) {
            ctrl.moveMedia(item, to.model);
          } else if (mediaLibraryManager.isFolder(item)) {
            ctrl.moveFolder(item, to.model);
          }
        });
      } else {
        console.info('src is not an array, aborting');

        return false;
      }
    };

    $scope.navFolder = function (folder) {
      var toName = 'app.mediaLibrary.base.folders.details',
        toParams = { slug: folder.slug },
        sameState = $state.is(toName, toParams);

      $state.go(toName, toParams);

      // No context change, since state stays the same. So, do stuff manually
      if (sameState) {
        ctrl.refreshContext();
      }
    };

    $scope.viewMedia = function (media) {
      $scope.shared.mediaCache.add(media); // add media to cache, so viewer doesn't fetch it
      $state.go('app.mediaLibrary.base.media', { id: media.id });
    };

    $scope.viewMediaSelection = function () {
      if (!$scope.shared.selection.list.length) {
        return;
      }

      var firstMedia = mediaLibraryFilesFilter($scope.shared.selection.list)[0];
      if (firstMedia) {
        $scope.viewMedia(firstMedia);
      }
    };

    $scope.navigateBack = function () {
      var target;
      if ($state.is('app.mediaLibrary.base.media')) {
        if ($rootScope.lastFolderHistory && $rootScope.lastFolderHistory.slug) {
          target = $rootScope.lastFolderHistory;
        }
      } else if ($state.is('app.mediaLibrary.base.folders.details') && $scope.shared.parent) {
        target = $scope.shared.parent;
      }

      if (target) {
        return $state.go('app.mediaLibrary.base.folders.details', { slug: target.slug });
      }

      // no previous folder found, go back to media library index
      return $state.go('app.mediaLibrary.base.folders.details');
    };

    $scope.addFolder = function () {
      mediaLibraryAddFolderModal.shared = $scope.shared;
      mediaLibraryAddFolderModal.activate();
    };

    $scope.triggerUploadBrowse = function () {
      angular.element('#uploader').click();
    };

    $scope.addUrl = function () {
      mediaLibraryAddUrlModal.shared = $scope.shared;
      mediaLibraryAddUrlModal.activate();
    };

    $scope.renameFolder = function () {
      mediaLibraryRenameFolderModal.shared = $scope.shared;
      mediaLibraryRenameFolderModal.activate();
    };

    $scope.closeMediaLibrary = function () {
      $rootScope.$emit('mediaLibrary.closeRequest');
    };

    $scope.$on('mediaLibrary.folder.created', function (event, location) {
      // insert the newly created folder
      MediaLibraryRestangular.oneUrl('media-folder', location).get()
        .then(ctrl.addMediaFolder, ctrl.getMediaFolderError)
      ;
    });

    $scope.$on('mediaLibrary.folder.renamed', function (event, folder) {
      // refresh library with data from renamed folder
      var existingFolder = mediaLibraryManager.findFolder($scope.shared.library, folder);
      if (existingFolder) {
        existingFolder.label = folder.label;
        existingFolder.slug = folder.slug;
      }

      // current context has been renamed <=> url changed
      // => navigate to the new folder, but do not create an additional history state
      if ($scope.shared.context && $scope.shared.context.unique_key === folder.unique_key) {
        $state.go('app.mediaLibrary.base.folders.details', { slug: folder.slug }, { location: 'replace' });
      }
    });

    $scope.$on('mediaLibrary.media.created', function (event, location, target) {
      // target explicitly given, abort if isn't current context
      if (target && target.unique_key !== $scope.shared.context.unique_key) {
        return;
      }

      if (!location) {
        return;
      }

      MediaLibraryRestangular.oneUrl('media', location).get()
        .then(function getMediaSuccess (response) {
          // enforce target at the moment of inserting the media (it could have
          // changed)
          ctrl.addMedia(response, target);
        }, ctrl.getMediaError)
      ;
    });

    $scope.$on('mediaLibrary.selection.deleted', function () {
      // refresh library
      ctrl.loadLibrary();
    });

    $scope.$on('mediaLibrary.selection.moved', function () {
      // refresh library
      ctrl.loadLibrary();
    });

    $scope.$on('mediaLibrary.selection.copied', function () {
      // refresh library
      ctrl.loadLibrary();
    });

    var unregisterContextChanged = $rootScope.$on('mediaLibrary.context.changed', function () {
      // check rights instantly with the new context (data may be outdated)
      ctrl.checkRights();

      // ask for a data refresh
      ctrl.refreshContext();
    });

    var unregisterTrashRemoved = $rootScope.$on('mediaLibrary.trash.removed', function () {
      ctrl.loadLibrary();
    });

    var unregisterTrashRestored = $rootScope.$on('mediaLibrary.trash.restored', function () {
      ctrl.loadLibrary();
    });

    // refresh fav folder
    var unregisterFavoritesChanged = $rootScope.$on('mediaLibrary.favorites.changed', function () {
      if (mediaLibraryManager.isFavorites($scope.shared.context)) {
        ctrl.refreshContext();
      }
    });

    // the front model is out of sync and needs a refresh
    var unregisterDirty = $rootScope.$on('mediaLibrary.dirty', function () {
      ctrl.loadLibrary();
    });

    var unregisterNeeded = $rootScope.$on('mediaLibrary.needed', function () {
      ctrl.loadLibrary();
    });

    var unregisterSelectionJoinRequest = $rootScope.$on('mediaLibrary.selection.joinRequest', function (event, selection) {
      if (!selection) {
        selection = $scope.shared.selection.list;
      }

      // talk to invoker from iframe
      if ($window.top.angular) {
        $window.top.angular.element('body').trigger('mediaLibrary.selection.done', { selection: selection });
      }

      angular.element('body').trigger('mediaLibrary.selection.done', { selection: selection });
    });

    var unregisterCloseRequest = $rootScope.$on('mediaLibrary.closeRequest', function () {
      if ($scope.isSelectionMode) {
        angular.element('body').trigger('mediaLibrary.selection.abort');
      } else if ($scope.isViewMode) {
        angular.element('body').trigger('mediaLibrary.close');
      }
    });

    var unregisterToggleLocker = $rootScope.$on('mediaLibrary.context.toggleLockerRequest', function () {
      console.log('on mediaLibrary.context.toggleLockerRequest');
      mediaLibraryAPI.toggleLocker($scope.shared.context);
    });

    $scope.$on('$destroy', function () {
      unregisterContextChanged();
      unregisterTrashRemoved();
      unregisterTrashRestored();
      unregisterFavoritesChanged();
      unregisterDirty();
      unregisterNeeded();
      unregisterSelectionJoinRequest();
      unregisterCloseRequest();
      unregisterToggleLocker();
    });
  });
