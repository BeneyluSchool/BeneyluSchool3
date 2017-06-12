'use strict';

angular.module('bns.mediaLibrary.mediaTypeFilters', [
  'bns.mediaLibrary.manager',
])

  .filter('mediaType', function (mediaLibraryManager) {
    return function (item) {
      return mediaLibraryManager.getMediaType(item);
    };
  })

  .filter('isMediaImage', function (mediaLibraryManager) {
    return function (item) {
      return mediaLibraryManager.isMediaImage(item);
    };
  })

  .filter('isMediaVideo', function (mediaLibraryManager) {
    return function (item) {
      return mediaLibraryManager.isMediaVideo(item);
    };
  })

  .filter('isMediaDocument', function (mediaLibraryManager) {
    return function (item) {
      return mediaLibraryManager.isMediaDocument(item);
    };
  })

  .filter('isMediaAudio', function (mediaLibraryManager) {
    return function (item) {
      return mediaLibraryManager.isMediaAudio(item);
    };
  })

  .filter('isMediaLink', function (mediaLibraryManager) {
    return function (item) {
      return mediaLibraryManager.isMediaLink(item);
    };
  })

  .filter('isMediaEmbed', function (mediaLibraryManager) {
    return function (item) {
      return mediaLibraryManager.isMediaEmbed(item);
    };
  })

  .filter('isMediaFile', function (mediaLibraryManager) {
    return function (item) {
      return mediaLibraryManager.isMediaFile(item);
    };
  })

  .filter('isMediaProvider', function (mediaLibraryManager) {
    return function (item) {
      return mediaLibraryManager.isMediaProvider(item);
    };
  })

  .filter('isMediaWorkshopDocument', function (mediaLibraryManager) {
    return function (item) {
      return mediaLibraryManager.isMediaWorkshopDocument(item);
    };
  })

  .filter('isMediaHtml', function (mediaLibraryManager) {
    return function (item) {
      return mediaLibraryManager.isMediaHtml(item);
    };
  })

  .filter('isMediaHtmlBase', function (mediaLibraryManager) {
    return function (item) {
      return mediaLibraryManager.isMediaHtmlBase(item);
    };
  })

  .filter('isDownloadable', function(mediaLibraryManager) {
    return function (item) {
      return mediaLibraryManager.isDownloadable(item);
    }
  })
;
