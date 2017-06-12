'use strict';

angular.module('bns.mediaLibrary')

  /**
   * @ngdoc controller
   * @name bns.mediaLibrary.MediaLibraryAddUrlCtrl
   * @kind function
   *
   * @description
   * This controller handles addition of media as URLs, via a modal dialog.
   * Upon success, the modal is closed and the newly-added media is reloaded in
   * the current context.
   * Upon failure, an error is shown within the modal and user can modify the
   * URL before trying again.
   *
   * @requires $scope
   * @requires $rootScope
   * @requires message
   * @requires mediaLibraryAddUrlModal
   * @requires MediaLibraryRestangular
   *
   * @returns {Object} The media library add url controller
   */
  .controller('MediaLibraryAddUrlCtrl', function ($scope, $rootScope, message, mediaLibraryAddUrlModal, MediaLibraryRestangular) {
    var ctrl = this;

    ctrl.init = function () {
      $scope.model = {
        url: '',
        error: '',
      };
    };

    ctrl.addUrl = function (url) {
      var context = mediaLibraryAddUrlModal.shared.context;
      ctrl.context = context;

      if (!context) {
        console.warn('[addUrl] No context, abort');
        return;
      }

      var postData = {
        url: url,
      };
      MediaLibraryRestangular.one('media', context.marker).post('url', postData)
        .then(ctrl.postMediaUrlSuccess, ctrl.postMediaUrlError)
      ;
    };

    ctrl.postMediaUrlSuccess = function (response) {
      message.success('MEDIA_LIBRARY.ADD_LINK_SUCCESS');
      mediaLibraryAddUrlModal.deactivate();
      $rootScope.$broadcast('mediaLibrary.media.created', response.headers.location, ctrl.context);
    };

    ctrl.postMediaUrlError = function (response) {
      message.error('MEDIA_LIBRARY.ADD_LINK_ERROR');
      $scope.model.error = 'MEDIA_LIBRARY.ADD_LINK_ERROR';
      console.error('postMediaUrl', response);
    };

    ctrl.init();

    $scope.closeModal = function () {
      mediaLibraryAddUrlModal.deactivate();
    };

    $scope.confirm = function () {
      $scope.model.error = '';
      ctrl.addUrl($scope.model.url);
    };
  });
