(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.starterKit.store
 */
angular.module('bns.starterKit.store', [])

  .factory('StarterKitStore', StarterKitStoreFactory)

;

/**
 * @ngdoc service
 * @name StarterKitStore
 * @module bns.starterKit.store
 *
 * @description
 * Stores starter kit steps for different apps, for the current locale.
 *
 * @requires $http
 * @requires global
 */
function StarterKitStoreFactory ($http, global, parameters) {

  var BASE = '/ent/js/starter-kit/';
  var LOCALE = global('locale');
  var VERSION = parameters.version;
  var StarterKitStore = {
    _steps: {},
    getSteps: getSteps,
  };

  return StarterKitStore;

  /**
   * Gets the starter kit steps for the given app. Results are cached
   *
   * @param {String} app
   * @returns {Promise} A promise resolved with the starter kit steps
   */
  function getSteps (app) {
    if (!StarterKitStore._steps[app]) {
      StarterKitStore._steps[app] = $http.get(BASE + app + '-' + LOCALE + '.json', {
        params: { v: VERSION },
      });
    }

    return StarterKitStore._steps[app].then(function (response) {
      return response.data;
    });
  }

}

})(angular);
