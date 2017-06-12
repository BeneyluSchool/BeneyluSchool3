'use strict';

angular.module('bns.resource')

  .factory('ResourceRestangular', function (Restangular) {
    // build a resource-specific restangular config, based on the global one
    return Restangular.withConfig(function (RestangularConfigurer) {
      // update the base url
      var baseUrl = RestangularConfigurer.baseUrl + '/resource';
      RestangularConfigurer.setBaseUrl(baseUrl);
    });
  });
