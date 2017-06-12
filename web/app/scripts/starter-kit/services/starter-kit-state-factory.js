(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.starterKit.state
 */
angular.module('bns.starterKit.state', [
  'restangular',
])

  .factory('StarterKitState', StarterKitStateFactory)

;

/**
 * @ngdoc service
 * @name StarterKitState
 * @module bns.starterKit.state
 *
 * @description
 * Restangular wrapper for starter kit states
 *
 * @requires Restangular
 */
function StarterKitStateFactory (Restangular) {

  return Restangular.service('starter-kits').one('states');

}

})(angular);
