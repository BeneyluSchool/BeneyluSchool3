'use strict';

angular.module('bns.mediaLibrary')

  /**
   * @ngdoc controller
   * @name bns.mediaLibrary.MediaLibraryRenameFolderCtrl
   * @kind function
   *
   * @description
   * This controller handles renaming of folders, via a modal dialog.
   * Upon success, the modal is closed and the folder is refreshed in the
   * library.
   * Upon failure, an error is shown within the modal and user can modify its
   * input before trying again.
   *
   * @requires $scope
   * @requires $rootScope
   * @requires message
   * @requires mediaLibraryRenameFolderModal
   * @requires MediaLibraryRestangular
   *
   * @returns {Object} The media library rename folder controller
   */
  .controller('MediaLibraryRenameFolderCtrl', function ($scope, $rootScope, message, mediaLibraryRenameFolderModal, MediaLibraryRestangular) {
    var ctrl = this;

    ctrl.init = function () {
      // init form with current folder name
      $scope.folder = {
        label: mediaLibraryRenameFolderModal.shared.context.label,
      };
    };

    ctrl.renameFolder = function (folder) {
      var context = mediaLibraryRenameFolderModal.shared.context,
        canCreate = mediaLibraryRenameFolderModal.shared.canCreate;

      if (!(context && canCreate)) {
        message.error('MEDIA_LIBRARY.RENAME_FOLDER_ERROR_NO_CONTEXT');
        mediaLibraryRenameFolderModal.deactivate();
        return;
      }

      var marker = context.marker;
      var patchData = {
        label: folder.label,
      };
      MediaLibraryRestangular.one('media-folder', marker).one('rename')
        .patch('', patchData)
        .then(ctrl.renameMediaFolderSuccess, ctrl.renameMediaFolderError)
      ;
    };

    ctrl.renameMediaFolderSuccess = function (response) {
      message.success('MEDIA_LIBRARY.RENAME_FOLDER_SUCCESS');
      mediaLibraryRenameFolderModal.deactivate();
      $rootScope.$broadcast('mediaLibrary.folder.renamed', response);
    };

    ctrl.renameMediaFolderError = function (response) {
      message.error('MEDIA_LIBRARY.RENAME_FOLDER_ERROR');
      $scope.folder.error = 'MEDIA_LIBRARY.RENAME_FOLDER_ERROR';
      console.error('renameMediaFolder', response);
    };

    this.init();

    $scope.closeModal = function () {
      mediaLibraryRenameFolderModal.deactivate();
    };

    $scope.confirm = function () {
      $scope.folder.error = '';
      ctrl.renameFolder($scope.folder);
    };
  });
