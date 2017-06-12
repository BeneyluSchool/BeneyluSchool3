'use strict';

angular.module('bns.statistic.restangular', [
  'restangular'
])

  .factory('statisticRestangular', function (Restangular) {
    // build a statistic specific Restangular config, based on the global one
    return Restangular.withConfig(function (RestangularConfigurer) {
      // update the base url
      var baseUrl = RestangularConfigurer.baseUrl + '/statistics';
      RestangularConfigurer.setBaseUrl(baseUrl);
    });
  });
