'use strict';

angular.module('bns.mediaLibrary')

  /**
   * @ngdoc controller
   * @name bns.mediaLibrary.MediaLibraryAddFolderCtrl
   * @kind function
   *
   * @description
   * This controller handles addition of folders, via a modal dialog.
   * Upon success, the modal is closed and the newly-added folder is reloaded in
   * the current context.
   * Upon failure, an error is shown within the modal and user can modify its
   * input before trying again.
   *
   * @requires $scope
   * @requires $rootScope
   * @requires message
   * @requires mediaLibraryAddFolderModal
   * @requires MediaLibraryRestangular
   *
   * @returns {Object} The media library add folder controller
   */
  .controller('MediaLibraryAddFolderCtrl', function ($scope, $rootScope, message, mediaLibraryAddFolderModal, MediaLibraryRestangular) {
    var ctrl = this;

    ctrl.init = function () {
      $scope.folder = {
        created: false,   // for starter kit validation
        label: '',
      };
    };

    ctrl.addFolder = function (folder) {
      var context = mediaLibraryAddFolderModal.shared.context,
        canCreate = mediaLibraryAddFolderModal.shared.canCreate;

      if (!(context && canCreate)) {
        message.error('MEDIA_LIBRARY.ADD_FOLDER_ERROR_NO_CONTEXT');
        mediaLibraryAddFolderModal.deactivate();
        return;
      }

      var marker = context.marker;
      var postData = {
        label: folder.label,
      };
      MediaLibraryRestangular.one('media-folder', marker).post('', postData)
        .then(ctrl.postMediaFolderSuccess, ctrl.postMediaFolderError)
      ;
    };

    ctrl.postMediaFolderSuccess = function (response) {
      $scope.folder.created = true;
      message.success('MEDIA_LIBRARY.ADD_FOLDER_SUCCESS');
      mediaLibraryAddFolderModal.deactivate();
      $rootScope.$broadcast('mediaLibrary.folder.created', response.headers.location);
    };

    ctrl.postMediaFolderError = function (response) {
      message.error('MEDIA_LIBRARY.ADD_FOLDER_ERROR');
      $scope.folder.error = 'MEDIA_LIBRARY.ADD_FOLDER_ERROR';
      console.error('postMediaFolder', response);
    };

    this.init();

    $scope.closeModal = function () {
      mediaLibraryAddFolderModal.deactivate();
    };

    $scope.confirm = function () {
      $scope.folder.error = '';
      ctrl.addFolder($scope.folder);
    };
  });
