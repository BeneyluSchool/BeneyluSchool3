'use strict';

angular.module('bns.mediaLibrary')

  /**
   * @ngdoc controller
   * @name bns.mediaLibrary.MediaLibraryTrashConfirmationCtrl
   * @kind function
   *
   * @description
   * This controller handles confirmation of the deletion of documents and
   * folders (putting them in the trash), via a modal dialog.
   * Upon success, the modal is closed and the trash is updated with the valid
   * items. Invalid items stay in their original location.
   *
   * @requires $scope
   * @requires $rootScope
   * @requires message
   * @requires mediaLibraryTrashConfirmationModal
   * @requires MediaLibraryRestangular
   * @requires mediaLibrarySelectionManager
   * @requires mediaLibraryFoldersFilter
   * @requires mediaLibraryFilesFilter
   *
   * @returns {Object} The media library trash confirmation controller
   */
  .controller('MediaLibraryTrashConfirmationCtrl', function ($scope, $rootScope, message, mediaLibraryTrashConfirmationModal, MediaLibraryRestangular, mediaLibrarySelectionManager, mediaLibraryFoldersFilter, mediaLibraryFilesFilter) {
    var ctrl = this;

    ctrl.init = function () {
      $scope.items = mediaLibraryTrashConfirmationModal.items;
      $scope.invalidFolders = mediaLibraryFoldersFilter($scope.items.invalid);
      $scope.invalidFiles = mediaLibraryFilesFilter($scope.items.invalid);
      $scope.isRemove = 'remove' === mediaLibraryTrashConfirmationModal.action;
      $scope.isMove = 'move' === mediaLibraryTrashConfirmationModal.action;

      // valid items can be interacted with, so watch them
      $scope.$watchCollection('items.valid', function () {
        $scope.validFolders = mediaLibraryFoldersFilter($scope.items.valid);
        $scope.validFiles = mediaLibraryFilesFilter($scope.items.valid);
      });
    };

    ctrl.moveToTrash = function (items) {
      var data = mediaLibrarySelectionManager.getFormattedData(items);

      MediaLibraryRestangular.one('selection-delete')
        .patch({ datas: data })
        .then(ctrl.selectionDeleteSuccess, ctrl.selectionDeleteError)
      ;
    };

    ctrl.selectionDeleteSuccess = function (response) {
      var errors = parseInt(response.content);

      if (errors) {
        if ($scope.isMove) {
          message.info('MEDIA_LIBRARY.DELETE_MOVE_SELECTION_SUCCESS_NOT_ALL');
        } else if ($scope.isRemove) {
          message.info('MEDIA_LIBRARY.DELETE_REMOVE_SELECTION_SUCCESS_NOT_ALL');
        }
      } else {
        if ($scope.isMove) {
          message.success('MEDIA_LIBRARY.DELETE_MOVE_SELECTION_SUCCESS');
        } else if ($scope.isRemove) {
          message.success('MEDIA_LIBRARY.DELETE_REMOVE_SELECTION_SUCCESS');
        }
      }

      mediaLibraryTrashConfirmationModal.deactivate();
      if ($scope.isMove) {
        $rootScope.$broadcast('mediaLibrary.selection.deleted');
      } else if ($scope.isRemove) {
        $rootScope.$broadcast('mediaLibrary.trash.removed');
      }
    };

    ctrl.selectionDeleteError = function (response) {
      message.error('MEDIA_LIBRARY.DELETE_SELECTION_ERROR');
      console.error('selectionDelete', response);
      mediaLibraryTrashConfirmationModal.deactivate();
    };

    ctrl.init();

    $scope.removeValidItem = function (item) {
      var found = false;
      for (var i = 0; i < $scope.items.valid.length && !found; i++) {
        if (item === $scope.items.valid[i]) {
          found = true;
          $scope.items.valid.splice(i, 1);
        }
      }
    };

    $scope.closeModal = function () {
      mediaLibraryTrashConfirmationModal.deactivate();
    };

    $scope.confirm = function () {
      ctrl.moveToTrash($scope.items.valid);
    };
  });
