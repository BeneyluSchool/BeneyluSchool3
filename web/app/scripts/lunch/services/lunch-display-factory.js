(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.lunch.lunchDisplay
 */
angular.module('bns.lunch.lunchDisplay', [
  'restangular',
])

  .factory('LunchDisplay', LunchDisplayFactory)

;

/**
 * @ngdoc service
 * @name LunchDisplay
 * @module bns.lunch.lunchDisplay
 *
 * @description
 * A Restangular wrapper for the 'lunch' API.
 *
 * @requires Restangular
 *
 * @returns {Object} The LunchDisplay service
 */
function LunchDisplayFactory ($scope) {

    $scope.display = {
      data: 'day'
    };
    return $scope.display;

}

})(angular);
