'use strict';

angular.module('bns.mediaLibrary')

  .factory('mediaLibraryRenameFolderModal', function (btfModal) {
    return btfModal({
      controller: 'MediaLibraryRenameFolderCtrl',
      controllerAs: 'modal',
      templateUrl: '/ent/angular/app/views/media-library/modals/rename-folder-modal.html'
    });
  });
