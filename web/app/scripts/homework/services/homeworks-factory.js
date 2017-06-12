(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.homework.homeworks
 */
angular.module('bns.homework.homeworks', [
  'restangular',
])

  .factory('Homeworks', HomeworksFactory)

;

/**
 * @ngdoc service
 * @name Homeworks
 * @module bns.homework.homeworks
 *
 * @description
 * Restangular wrapper for homeworks.
 *
 * @requires Restangular
 */
function HomeworksFactory (Restangular) {

  return Restangular.service('homeworks');

}

})(angular);
