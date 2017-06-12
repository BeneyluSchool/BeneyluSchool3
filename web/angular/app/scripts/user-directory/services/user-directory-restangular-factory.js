'use strict';

angular.module('bns.userDirectory.restangular', [
  'restangular'
])

  .factory('UserDirectoryRestangular', function (Restangular) {
    // build a user-directory-specific Restangular config, based on the global one
    return Restangular.withConfig(function (RestangularConfigurer) {
      // update the base url
      var baseUrl = RestangularConfigurer.baseUrl + '/user-directory';
      RestangularConfigurer.setBaseUrl(baseUrl);
    });
  });
