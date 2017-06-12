'use strict';

angular.module('bns.mediaLibrary.restangular', [
  'restangular'
])

  .factory('MediaLibraryRestangular', function (Restangular) {
    // build a media-library-specific Restangular config, based on the global one
    return Restangular.withConfig(function (RestangularConfigurer) {
      // update the base url
      var baseUrl = RestangularConfigurer.baseUrl + '/media-library';
      RestangularConfigurer.setBaseUrl(baseUrl);
    });
  });
