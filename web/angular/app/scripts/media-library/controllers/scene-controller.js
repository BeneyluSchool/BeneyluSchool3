'use strict';

angular.module('bns.mediaLibrary')

  /**
   * @ngdoc controller
   * @name bns.mediaLibrary.MediaLibrarySceneCtrl
   * @kind function
   *
   * @description
   * The media library scene controller is responsible for loading the base
   * view of a folder, from its slug.
   *
   * @requires $scope
   * @requires $rootScope
   * @requires $stateParams
   * @requires $state
   * @requires message
   * @requires treeUtils
   * @requires CollectionMap
   * @requires MediaLibraryRestangular
   * @requires mediaLibraryManager
   * @requires mediaLibrarySelectionManager
   * @requires mediaLibraryConfig
   * @requires mediaLibraryTrashConfirmationModal
   *
   * @return {Object} The Media Library Scene Controller
   */
  .controller('MediaLibrarySceneCtrl', function ($scope, $rootScope, $stateParams, $state, $translate, message, treeUtils, CollectionMap, MediaLibraryRestangular, mediaLibraryManager, mediaLibrarySelectionManager, mediaLibraryConfig, mediaLibraryTrashConfirmationModal) {
    var ctrl = this;

    ctrl.init = function () {
      var slug = $stateParams.slug;
      if (!slug) {
        ctrl.redirectDefault();
        return;
      }

      ctrl.loadFolder(slug);
      ctrl.initForContext();
    };

    /**
     * Handles context-specific initialization
     *
     * TODO: find a better way to do this
     */
    ctrl.initForContext = function () {
      var context = $scope.shared.context;

      if (!context) {
        return;
      }

      $scope.shared.contextSelection = new CollectionMap([], 'unique_key');

      if ('trash' === context.slug) {
        mediaLibraryTrashConfirmationModal.items = {};

        ctrl.deleteContextSelection = function () {
          // borrow formatter of selection manager
          var data = mediaLibrarySelectionManager.getFormattedData($scope.shared.contextSelection.list);

          MediaLibraryRestangular.one('selection-check-delete', '')
            .patch({ datas: data })
            .then(ctrl.selectionCheckDeleteSuccess, ctrl.selectionCheckDeleteError)
          ;
        };

        ctrl.selectionCheckDeleteSuccess = function (response) {
          if (!(response.content && (response.content.canDelete.length || response.content.cantDelete.length))) {
            message.info('MEDIA_LIBRARY.NOTHING_TO_DELETE');
            return;
          }

          mediaLibraryTrashConfirmationModal.items = {
            valid: response.content.canDelete,
            invalid: response.content.cantDelete,
          };
          mediaLibraryTrashConfirmationModal.action = 'remove';
          mediaLibraryTrashConfirmationModal.activate();
        };

        ctrl.selectionCheckDeleteError = function (response) {
          message.error('MEDIA_LIBRARY.DELETE_SELECTION_ERROR');
          console.error('selectionCheckDelete', response);
        };

        ctrl.restoreContextSelection = function () {
          var data = mediaLibrarySelectionManager.getFormattedData($scope.shared.contextSelection.list);

          MediaLibraryRestangular.one('selection-restore', '')
            .patch({ datas: data })
            .then(ctrl.selectionRestoreSuccess, ctrl.selectionRestoreError)
          ;
        };

        ctrl.selectionRestoreSuccess = function (response) {
          var nbErrors = parseInt(response.content);
          if (nbErrors) {
            $translate('MEDIA_LIBRARY.RESTORE_SELECTION_SUCCESS_PARTIAL', {NUM: nbErrors}, 'messageformat').then(function (translation) {
              message.info(translation);
            });
          } else {
            message.success('MEDIA_LIBRARY.RESTORE_SELECTION_SUCCESS');
          }
          $rootScope.$emit('mediaLibrary.trash.restored');
        };

        ctrl.selectionRestoreError = function (response) {
          console.error(response);
          message.error('MEDIA_LIBRARY.RESTORE_SELECTION_ERROR');
        };

        ctrl.emptyTrash = function () {
          MediaLibraryRestangular.one('media-corbeille-check', '')
            .get()
            .then(ctrl.selectionCheckDeleteSuccess, ctrl.checkEmtpyTrashError) // confirm deletion as for a manual selection
          ;
        };

        ctrl.checkEmtpyTrashError = function (response) {
          message.error('MEDIA_LIBRARY.EMPTY_TRASH_ERROR');
          console.error('checkEmptyTrash', response);
        };

        ctrl.restoreTrash = function () {
           MediaLibraryRestangular.one('media-corbeille-restore', '')
            .get()
            .then(ctrl.restoreTrashSuccess, ctrl.restoreTrashError)
          ;
        };

        ctrl.restoreTrashSuccess = function () {
          message.success('MEDIA_LIBRARY.RESTORE_TRASH_SUCCESS');
          $rootScope.$emit('mediaLibrary.trash.restored');
        };

        ctrl.restoreTrashError = function (response) {
          message.error('MEDIA_LIBRARY.RESTORE_TRASH_ERROR');
          console.error('restoreTrash', response);
        };

        var unregisterEmptyRequest = $rootScope.$on('mediaLibrary.trash.emptyRequest', function () {
          ctrl.emptyTrash();
        });

        var unregisterRestoreRequest = $rootScope.$on('mediaLibrary.trash.restoreRequest', function () {
          ctrl.restoreTrash();
        });

        $scope.$on('$destroy', function () {
          unregisterEmptyRequest();
          unregisterRestoreRequest();
        });
      }
    };

    /**
     * Redirects to the default library location
     */
    ctrl.redirectDefault = function () {
      ctrl.pendingRedirectDefault = false;

      // library not yet loaded, abort.
      if (!$scope.shared.library) {
        ctrl.pendingRedirectDefault = true;
        $rootScope.$emit('mediaLibrary.needed');
        return;
      }

      var defaultFolder = $scope.shared.library.my_folder;

      if (!defaultFolder) {
        defaultFolder = $scope.shared.library.group_folders[0];
      }

      $state.go('app.mediaLibrary.base.folders.details', { slug: defaultFolder.slug });
    };

    /**
     * Tries to find a folder in the library, from its slug
     *
     * @param {String} slug
     */
    ctrl.loadFolder = function (slug) {
      if (!slug) {
        // TODO: 404
        console.warn('No slug specified, cannot load scene');
        return;
      }

      // library not yet loaded, abort.
      if (!$scope.shared.library) {
        $rootScope.$emit('mediaLibrary.needed');
        return;
      }

      var comparator = function (item) {
        return item.slug === slug;
      };
      var found = treeUtils.find($scope.shared.library.my_folder, comparator);
      if (!found) {
        found = treeUtils.find($scope.shared.library.group_folders, comparator);
      }
      if (!found) {
        found = treeUtils.find($scope.shared.library.special_folders, comparator);
      }

      if (found) {
        $scope.setContext(found);
      } else {
        // TODO: 404
        console.warn('No folder found for slug', slug);
      }
    };

    ctrl.init();

    $scope.selectAll = function (remove) {
      var targetCollection;
      if ($scope.shared.globalSelectionEnabled) {
        targetCollection = $scope.shared.selection;
      } else {
        targetCollection = $scope.shared.contextSelection;
      }

      angular.forEach($scope.shared.context.children, function (folder) {
        if (remove) {
          targetCollection.remove(folder);
        } else {
          if ($scope.isSelectable(folder)) {
            targetCollection.add(folder);
          }
        }
      });

      angular.forEach($scope.shared.context.medias, function (file) {
        if (remove) {
          targetCollection.remove(file);
        } else {
          if ($scope.isSelectable(file)) {
            targetCollection.add(file);
          }
        }
      });

      $rootScope.$broadcast('mediaLibrary.selection.changed');
    };

    // library loaded, find current folder
    $scope.$on('mediaLibrary.loaded', function () {
      if (ctrl.pendingRedirectDefault) {
        ctrl.redirectDefault();
      } else {
        ctrl.loadFolder($stateParams.slug);
      }
    });

    var unregisterContextChange = $rootScope.$on('mediaLibrary.context.changed', function () {
      ctrl.initForContext();
    });

    var unregisterContextDeleteRequest = $rootScope.$on('mediaLibrary.contextSelection.deleteRequest', function () {
      ctrl.deleteContextSelection();
    });

    var unregisterContextRestoreRequest = $rootScope.$on('mediaLibrary.contextSelection.restoreRequest', function () {
      ctrl.restoreContextSelection();
    });

    $scope.$on('$destroy', function () {
      unregisterContextChange();
      unregisterContextDeleteRequest();
      unregisterContextRestoreRequest();
    });
  });
