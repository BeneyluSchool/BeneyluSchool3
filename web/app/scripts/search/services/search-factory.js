(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.search.search
 */
angular.module('bns.search.search', [
  'restangular',
])

  .factory('Search', SearchFactory)

;

/**
 * @ngdoc service
 * @name Search
 * @module bns.search.search
 *
 * @description
 * Restangular wrapper for Search.
 *
 * @requires Restangular
 */
function SearchFactory (Restangular) {

  return Restangular.service('search');

}

})(angular);
