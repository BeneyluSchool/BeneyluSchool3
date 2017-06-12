'use strict';

angular.module('bns.mediaLibrary')

  .factory('mediaLibraryAddFolderModal', function (btfModal) {
    return btfModal({
      controller: 'MediaLibraryAddFolderCtrl',
      controllerAs: 'modal',
      templateUrl: '/ent/angular/app/views/media-library/modals/add-folder-modal.html'
    });
  });
