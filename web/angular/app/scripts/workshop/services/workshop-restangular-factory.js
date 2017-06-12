'use strict';

angular.module('bns.workshop.restangular', [
  'restangular',
])

  .factory('WorkshopRestangular', function (Restangular) {
    // build a workshop-specific restangular config, based on the global one
    return Restangular.withConfig(function (RestangularConfigurer) {
      // update the base url
      var baseUrl = RestangularConfigurer.baseUrl + '/workshop';
      RestangularConfigurer.setBaseUrl(baseUrl);
    });
  });
