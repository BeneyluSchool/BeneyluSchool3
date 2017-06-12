'use strict';

angular.module('bns.mediaLibrary')

  .factory('mediaLibraryPrivacyConfirmationModal', function (btfModal) {
    return btfModal({
      controller: 'MediaLibraryPrivacyConfirmationCtrl',
      controllerAs: 'modal',
      templateUrl: '/ent/angular/app/views/media-library/modals/privacy-confirmation-modal.html'
    });
  });
