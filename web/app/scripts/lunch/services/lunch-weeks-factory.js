(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.lunch.lunchWeek
 */
angular.module('bns.lunch.lunchWeek', [
  'restangular',
])

  .factory('LunchWeek', LunchWeekFactory)

;

/**
 * @ngdoc service
 * @name LunchWeek
 * @module bns.lunch.lunchWeek
 *
 * @description
 * A Restangular wrapper for the 'lunch' API.
 *
 * @requires Restangular
 *
 * @returns {Object} The LunchWeek service
 */
function LunchWeekFactory (Restangular) {

  return Restangular.service('weeks', Restangular.one('lunch', ''));

}

})(angular);
