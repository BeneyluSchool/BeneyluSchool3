'use strict';

angular.module('bns.mediaLibrary')

  /**
   * @ngdoc controller
   * @name bns.mediaLibrary.MediaLibraryPrivacyConfirmationCtrl
   * @kind function
   *
   * @description
   * This controller handles confirmation of privacy change setting for folders
   *
   * @requires $scope
   * @requires mediaLibraryPrivacyConfirmationModal
   *
   * @returns {Object} The media library privacy confirmation controller
   */
  .controller('MediaLibraryPrivacyConfirmationCtrl', function ($scope, $rootScope, mediaLibraryPrivacyConfirmationModal) {
    var ctrl = this;

    ctrl.init = function () {
      $scope.privatize = 'privatize' === mediaLibraryPrivacyConfirmationModal.action;
      $scope.publicize = 'publicize' === mediaLibraryPrivacyConfirmationModal.action;
      $scope.folders = mediaLibraryPrivacyConfirmationModal.folders;
    };

    ctrl.init();

    $scope.closeModal = function () {
      mediaLibraryPrivacyConfirmationModal.deactivate();
    };

    $scope.confirm = function () {
      // user has confirmed, broadcast event again and ask to skip check
      $rootScope.$broadcast('mediaLibrary.selection.togglePrivateRequest', true);
      mediaLibraryPrivacyConfirmationModal.deactivate();
    };
  });
