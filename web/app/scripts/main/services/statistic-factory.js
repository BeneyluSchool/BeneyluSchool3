'use strict';

angular.module('bns.main.statistic', [
  'restangular',
])
  /**
   * @ngdoc service
   * @name bns.core.url.statistic
   * @kind function
   *
   * @description
   * Statistic factory
   *
   * ** Methods **
   * - `visit(module)`: push a visit hit
   *
   * @require Restangular
   *
   * @returns {Object} The statistic factory
   */
  .factory('statistic', function url (Restangular) {
    return {
      visit: visit,
    };

    function visit (module) {
      Restangular.all('statistics').one('visits', module).post();
    }
  })

;
