'use strict';

angular.module('bns.mediaLibrary.isFileFilter', [
  'bns.mediaLibrary.manager',
])

  .filter('isMediaLibraryFile', function (mediaLibraryManager) {
    return function (item) {
      return mediaLibraryManager.isFile(item);
    };
  });
