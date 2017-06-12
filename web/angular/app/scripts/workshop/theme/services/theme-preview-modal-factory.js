'use strict';

angular.module('bns.workshop.theme')

  .factory('workshopThemePreviewModal', function (btfModal) {
    return btfModal({
      controller: 'WorkshopThemesPreviewCtrl',
      controllerAs: 'modal',
      templateUrl: '/ent/angular/app/views/workshop/theme/preview-modal.html'
    });
  });
