'use strict';

angular.module('bns.mediaLibrary')

  .factory('mediaLibraryAddUrlModal', function (btfModal) {
    return btfModal({
      controller: 'MediaLibraryAddUrlCtrl',
      controllerAs: 'modal',
      templateUrl: '/ent/angular/app/views/media-library/modals/add-url-modal.html'
    });
  });
