'use strict';

angular.module('bns.mediaLibrary.uploader', [])

  .factory('mediaLibraryUploader', function (FileUploader, MediaLibraryRestangular) {
    var uploader = new FileUploader();
    var baseUrl = MediaLibraryRestangular.configuration.baseUrl;

    // Prepare only the base url. Actual url is context-dependant, and updated
    // JIT before upload
    uploader.url = baseUrl + '/media';

    uploader.autoUpload = true;

    // predefined filter to forbid upload of folders does not work in chrome, so
    // do our own check
    uploader.filters.unshift({
      name: 'isFile',
      fn: function isFile (item) {
        return true;
      }
    });

    return uploader;
  });
