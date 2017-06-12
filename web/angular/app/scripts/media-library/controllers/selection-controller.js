'use strict';

angular.module('bns.mediaLibrary')

  .controller('MediaLibrarySelectionCtrl', function ($scope, $rootScope, $translate, message, downloader, mediaLibrarySelectionManager, mediaLibraryManager, MediaLibraryRestangular, mediaLibraryPrivacyConfirmationModal, mediaLibraryTrashConfirmationModal, mediaLibraryConfig, mediaLibraryFoldersFilter, mediaLibraryShareManager) {
    var ctrl = this;

    ctrl.init = function () {
      mediaLibraryTrashConfirmationModal.items = {};
    };

    ctrl.deleteSelection = function () {
      var data = this.getFormattedData();

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
      mediaLibraryTrashConfirmationModal.action = 'move';
      mediaLibraryTrashConfirmationModal.activate();
    };

    ctrl.selectionCheckDeleteError = function (response) {
      message.error('MEDIA_LIBRARY.DELETE_SELECTION_ERROR');
      console.error('selectionCheckDelete', response);
    };

    ctrl.toggleFavoriteSelection = function () {
      if ($scope.shared.selectionIsAllFavorites) {
        ctrl.unfavoriteSelection();
      } else {
        ctrl.favoriteSelection();
      }
    };

    ctrl.favoriteSelection = function () {
      var data = this.getFormattedData();

      MediaLibraryRestangular.one('selection-favorite', '')
        .patch({ datas: data })
        .then(ctrl.favoriteSelectionSuccess, ctrl.favoriteSelectionError)
      ;
    };

    ctrl.favoriteSelectionSuccess = function (response) {
      var nbErrors = parseInt(response.content);
      if (nbErrors) {
        $translate('MEDIA_LIBRARY.FAVORITE_SELECTION_SUCCESS_PARTIAL', {NUM: nbErrors}, 'messageformat').then(function (translation) {
          message.info(translation);
        });
        // some errors: front model is out of sync, needs refresh
        $rootScope.$emit('mediaLibrary.dirty');
      } else {
        // get items from selection and favorite them
        mediaLibrarySelectionManager.favorite($scope.shared.selection);
        ctrl.checkAllFavorites();
        message.success('MEDIA_LIBRARY.FAVORITE_SELECTION_SUCCESS');
        $rootScope.$emit('mediaLibrary.favorites.changed');
      }
    };

    ctrl.favoriteSelectionError = function (response) {
      message.error('MEDIA_LIBRARY.FAVORITE_SELECTION_ERROR');
      console.error(response);
    };

    ctrl.unfavoriteSelection = function () {
      var data = this.getFormattedData();

      MediaLibraryRestangular.one('selection-unfavorite', '')
        .patch({ datas: data })
        .then(ctrl.unfavoriteSelectionSuccess, ctrl.unfavoriteSelectionError)
      ;
    };

    ctrl.unfavoriteSelectionSuccess = function (response) {
      var nbErrors = parseInt(response.content);
      if (nbErrors) {
        $translate('MEDIA_LIBRARY.UNFAVORITE_SELECTION_SUCCESS_PARTIAL', {NUM: nbErrors}, 'messageformat').then(function (translation) {
          message.info(translation);
        });
        // some errors: front model is out of sync, needs refresh
        $rootScope.$emit('mediaLibrary.dirty');
      } else {
        // get items from selection and unfavorite them
        mediaLibrarySelectionManager.unfavorite($scope.shared.selection);
        ctrl.checkAllFavorites();
        message.success('MEDIA_LIBRARY.UNFAVORITE_SELECTION_SUCCESS');
        $rootScope.$emit('mediaLibrary.favorites.changed');
      }
    };

    ctrl.unfavoriteSelectionError = function (response) {
      message.error('MEDIA_LIBRARY.UNFAVORITE_SELECTION_ERROR');
      console.error(response);
    };

    ctrl.togglePrivateSelection = function (skipCheck) {
      if ($scope.shared.selectionIsAllPrivates) {
        ctrl.publicizeSelection(skipCheck);
      } else {
        ctrl.privatizeSelection(skipCheck);
      }
    };

    ctrl.privatizeSelection = function (skipCheck) {
      console.log('ctrl.privatizeSelection');
      var folders = ctrl.getFolders();

      // if we have folders and check is not skipped, display a modal
      if (folders.length && !skipCheck) {
        mediaLibraryPrivacyConfirmationModal.action = 'privatize';
        mediaLibraryPrivacyConfirmationModal.folders = folders;
        mediaLibraryPrivacyConfirmationModal.activate();
        return;
      }

      var data = this.getFormattedData();

      MediaLibraryRestangular.one('selection-private', '')
        .patch({ datas: data })
        .then(ctrl.privatizeSelectionSuccess, ctrl.privatizeSelectionError)
      ;
    };

    ctrl.privatizeSelectionSuccess = function (response) {
      var nbErrors = parseInt(response.content);
      if (nbErrors) {
        $translate('MEDIA_LIBRARY.PRIVATIZE_SELECTION_SUCCESS_PARTIAL', {NUM: nbErrors}, 'messageformat').then(function (translation) {
          message.info(translation);
        });
        // some errors: front model is out of sync, needs refresh
        $rootScope.$emit('mediaLibrary.dirty');
      } else {
        message.success('MEDIA_LIBRARY.PRIVATIZE_SELECTION_SUCCESS');
        // no error: we can manually update the front model
        mediaLibrarySelectionManager.privatize($scope.shared.selection.list);
        ctrl.checkAllPrivates();
      }
    };

    ctrl.privatizeSelectionError = function (response) {
      message.error('MEDIA_LIBRARY.PRIVATIZE_SELECTION_ERROR');
      console.error('privatizeSelection', response);
    };

    ctrl.publicizeSelection = function (skipCheck) {
      var folders = ctrl.getFolders();

      // if we have folders and check is not skipped, display a modal
      if (folders.length && !skipCheck) {
        mediaLibraryPrivacyConfirmationModal.action = 'publicize';
        mediaLibraryPrivacyConfirmationModal.folders = folders;
        mediaLibraryPrivacyConfirmationModal.activate();
        return;
      }

      var data = this.getFormattedData();

      MediaLibraryRestangular.one('selection-unprivate', '')
        .patch({ datas: data })
        .then(ctrl.publicizeSelectionSuccess, ctrl.publicizeSelectionError)
      ;
    };

    ctrl.publicizeSelectionSuccess = function (response) {
      var nbErrors = parseInt(response.content);
      if (nbErrors) {
        $translate('MEDIA_LIBRARY.PUBLICIZE_SELECTION_SUCCESS_PARTIAL', {NUM: nbErrors}, 'messageformat').then(function (translation) {
          message.info(translation);
        });
        // some errors: front model is out of sync, needs refresh
        $rootScope.$emit('mediaLibrary.dirty');
      } else {
        message.success('MEDIA_LIBRARY.PUBLICIZE_SELECTION_SUCCESS');
        // no error: we can manually update the front model
        mediaLibrarySelectionManager.publicize($scope.shared.selection.list);
        ctrl.checkAllPrivates();
      }
    };

    ctrl.publicizeSelectionError = function (response) {
      message.error('MEDIA_LIBRARY.PUBLICIZE_SELECTION_ERROR');
      console.error('publicizeSelection', response);
    };

    ctrl.checkAllFavorites = function () {
      var cumulated = true;
      for (var i = 0; i < $scope.shared.selection.list.length && cumulated; i++) {
        var item = $scope.shared.selection.list[i];
        cumulated = cumulated && item.is_favorite;
      }

      $scope.shared.selectionIsAllFavorites = cumulated && !!$scope.shared.selection.list.length;
    };

    ctrl.checkAllPrivates = function () {
      var cumulated = true;
      for (var i = 0; i < $scope.shared.selection.list.length && cumulated; i++) {
        var item = $scope.shared.selection.list[i];
        cumulated = cumulated && item.is_private;
      }

      $scope.shared.selectionIsAllPrivates = cumulated && !!$scope.shared.selection.list.length;
    };

    ctrl.checkHasManageables = function () {
      var cumulated = false;
      for (var i = 0; i < $scope.shared.selection.list.length && !cumulated; i++) {
        var item = $scope.shared.selection.list[i];

        if (item.manageable) {
          cumulated = true;
        }
      }

      $scope.shared.selectionHasManageables = cumulated && !!$scope.shared.selection.list.length;
    };

    ctrl.checkAllWritables = function () {
      var cumulated = true;
      for (var i = 0; i < $scope.shared.selection.list.length && cumulated; i++) {
        var item = $scope.shared.selection.list[i];
        cumulated = cumulated && item.writable;
      }

      $scope.shared.selectionIsAllWritables = cumulated && !!$scope.shared.selection.list.length;
    };

    ctrl.checkItemTypes = function () {
      var allFiles = true;
      var allFolders = true;
      var hasFiles = false;
      var hasFolders = false;

      for (var i = 0; i < $scope.shared.selection.list.length; i++) {
        var item = $scope.shared.selection.list[i];
        if (mediaLibraryManager.isFile(item)) {
          hasFiles = true;
          allFolders = false;
        } else {
          hasFolders = true;
          allFiles = false;
        }
      }

      $scope.shared.selectionHasFiles = hasFiles && !!$scope.shared.selection.list.length;
      $scope.shared.selectionHasFolders = hasFolders && !!$scope.shared.selection.list.length;
      $scope.shared.selectionIsAllFiles = allFiles && !!$scope.shared.selection.list.length;
      $scope.shared.selectionIsAllFolders = allFolders && !!$scope.shared.selection.list.length;
    };

    ctrl.moveSelection = function () {
      // TODO: check rights
      var patchData = {};
      patchData.datas = this.getFormattedData();
      patchData['parent-marker'] = $scope.shared.context.marker;

      MediaLibraryRestangular.one('selection-move', '')
        .patch(patchData)
        .then(ctrl.moveSelectionSuccess, ctrl.moveSelectionError)
      ;
    };

    ctrl.moveSelectionSuccess = function (response) {
      var nbErrors = parseInt(response.content);
      if (nbErrors) {
        $translate('MEDIA_LIBRARY.MOVE_SELECTION_SUCCESS_PARTIAL', {NUM: nbErrors}, 'messageformat').then(function (translation) {
          message.info(translation);
        });
      } else {
        message.success('MEDIA_LIBRARY.MOVE_SELECTION_SUCCESS');
      }
      $rootScope.$broadcast('mediaLibrary.selection.moved');
    };

    ctrl.moveSelectionError = function (response) {
      console.error(response);
      message.error('MEDIA_LIBRARY.MOVE_SELECTION_ERROR');
      // $rootScope.$broadcast('mediaLibrary.selection.moved');
    };

    ctrl.copySelection = function () {
      // TODO: check rights
      var patchData = {};
      patchData.datas = this.getFormattedData();
      patchData['parent-marker'] = $scope.shared.context.marker;

      MediaLibraryRestangular.one('selection-copy', '')
        .patch(patchData)
        .then(ctrl.copySelectionSuccess, ctrl.copySelectionError)
      ;
    };

    ctrl.copySelectionSuccess = function (response) {
      var nbErrors = parseInt(response.content);
      if (nbErrors) {
        $translate('MEDIA_LIBRARY.COPY_SELECTION_SUCCESS_PARTIAL', {NUM: nbErrors}, 'messageformat').then(function (translation) {
          message.info(translation);
        });
      } else {
        message.success('MEDIA_LIBRARY.COPY_SELECTION_SUCCESS');
      }
      $rootScope.$broadcast('mediaLibrary.selection.copied');
    };

    ctrl.copySelectionError = function (response) {
      console.error(response);
      message.error('MEDIA_LIBRARY.COPY_SELECTION_ERROR');
    };

    ctrl.shareSelection = function (groups, users) {
      var files = [];
      angular.forEach($scope.shared.selection.list, function (item) {
        if (mediaLibraryManager.isFile(item)) {
          files.push(item);
        }
      });
      files = mediaLibrarySelectionManager.getFormattedData(files);
      mediaLibraryShareManager.share(files, groups, users)
        .then(function success (nbErrors) {
          if (!nbErrors) {
            $scope.emptySelection();
          }
        })
      ;
    };

    ctrl.downloadArchiveSelection = function () {
      var getData = {
        'datas[]': this.getFormattedData(),
      };
      var url = MediaLibraryRestangular.one('selection-archive', '').getRequestedUrl();

      function success () {
        message.success('MEDIA_LIBRARY.DOWNLOAD_ARCHIVE_SELECTION_SUCCESS');
      }

      function error (response) {
        console.error(response);
        message.error('MEDIA_LIBRARY.DOWNLOAD_ARCHIVE_SELECTION_ERROR');
      }

      downloader.get(url, getData, success, error);
    };

    ctrl.getFormattedData = function () {
      return mediaLibrarySelectionManager.getFormattedData($scope.shared.selection.list);
    };

    ctrl.getFolders = function () {
      return mediaLibraryFoldersFilter($scope.shared.selection.list);
    };

    ctrl.init();

    $scope.emptySelection = function () {
      $scope.shared.selection.reset();
      $rootScope.$broadcast('mediaLibrary.selection.changed');
    };

    $scope.$on('mediaLibrary.loaded', function () {
      // parse old selection and keep only items still existing in new library
      for (var i = $scope.shared.selection.list.length; i--;) {
        var item = $scope.shared.selection.list[i];
        if (mediaLibraryManager.isFile(item)) {
          if (!mediaLibraryManager.findMedia($scope.shared.library, item)) {
            $scope.shared.selection.remove(item);
          }
        } else if (mediaLibraryManager.isFolder(item)) {
          if (!mediaLibraryManager.findFolder($scope.shared.library, item)) {
            $scope.shared.selection.remove(item);
          }
        }
      }
    });

    $scope.$on('mediaLibrary.selection.deleteRequest', function () {
      ctrl.deleteSelection();
    });

    $scope.$on('mediaLibrary.selection.toggleFavoriteRequest', function () {
      ctrl.toggleFavoriteSelection();
    });

    $scope.$on('mediaLibrary.selection.togglePrivateRequest', function (event, skipCheck) {
      ctrl.togglePrivateSelection(skipCheck);
    });

    $scope.$on('mediaLibrary.selection.moveRequest', function () {
      ctrl.moveSelection();
    });

    $scope.$on('mediaLibrary.selection.copyRequest', function () {
      ctrl.copySelection();
    });

    $scope.$on('mediaLibrary.selection.shareRequest', function (event, groups, users) {
      ctrl.shareSelection(groups, users);
    });

    $scope.$on('mediaLibrary.selection.downloadArchiveRequest', function () {
      ctrl.downloadArchiveSelection();
    });

    $scope.$on('mediaLibrary.selection.changed', function () {
      ctrl.checkAllFavorites();
      ctrl.checkAllPrivates();
      ctrl.checkHasManageables();
      ctrl.checkAllWritables();
      ctrl.checkItemTypes();
      var nbSelected = $scope.shared.selection.list.length;

      if ('selection' === mediaLibraryConfig.mode) {
        var valid = true;
        var enabled = true;
        if (angular.isNumber(mediaLibraryConfig.min)) {
          valid = valid && nbSelected >= mediaLibraryConfig.min;
        }
        if (angular.isNumber(mediaLibraryConfig.max)) {
          valid = valid && nbSelected <= mediaLibraryConfig.max;
          enabled = enabled && nbSelected < mediaLibraryConfig.max;
        }
        $scope.shared.selectionValid = valid;
        $scope.shared.selectionEnabled = enabled;
      }
    });
  });
