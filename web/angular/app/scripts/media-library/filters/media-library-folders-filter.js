'use strict';

angular.module('bns.mediaLibrary.foldersFilter', [
  'bns.mediaLibrary.manager',
])

  .filter('mediaLibraryFolders', function (mediaLibraryManager) {
    return function (list) {
      var ret = [];
      angular.forEach(list, function (item) {
        if (mediaLibraryManager.isFolder(item)) {
          ret.push(item);
        }
      });

      return ret;
    };
  });
