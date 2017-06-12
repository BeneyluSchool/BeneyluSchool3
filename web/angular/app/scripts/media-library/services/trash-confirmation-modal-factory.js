'use strict';

angular.module('bns.mediaLibrary')

  .factory('mediaLibraryTrashConfirmationModal', function (btfModal) {
    return btfModal({
      controller: 'MediaLibraryTrashConfirmationCtrl',
      controllerAs: 'modal',
      templateUrl: '/ent/angular/app/views/media-library/modals/trash-confirmation-modal.html'
    });
  });
