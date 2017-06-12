'use strict';

angular.module('bns.mediaLibrary.filesFilter', [
  'bns.mediaLibrary.manager',
])

  .filter('mediaLibraryFiles', function (mediaLibraryManager) {
    return function (list) {
      var ret = [];
      angular.forEach(list, function (item) {
        if (mediaLibraryManager.isFile(item)) {
          ret.push(item);
        }
      });

      return ret;
    };
  });
