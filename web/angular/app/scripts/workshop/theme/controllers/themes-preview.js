'use strict';

angular.module('bns.workshop.theme')

  .controller('WorkshopThemesPreviewCtrl', function (workshopThemePreviewModal, $scope, $rootScope) {
    var ctrl = this;

    ctrl.init = function () {
      $scope.theme = workshopThemePreviewModal.theme;
    };

    /**
     * Closes the modal
     */
    $scope.closeModal = function () {
      workshopThemePreviewModal.deactivate();
    };

    $scope.confirm = function () {
      $rootScope.$broadcast('theme.chosen', workshopThemePreviewModal.theme);
      workshopThemePreviewModal.deactivate();
    };

    ctrl.init();
  });
