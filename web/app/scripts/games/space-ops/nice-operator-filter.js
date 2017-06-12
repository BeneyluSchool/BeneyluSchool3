(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.spaceOps.niceOperator
 */
angular.module('bns.spaceOps.niceOperator', [])

  .filter('niceOperator', NiceOperatorFilter)

;

/**
 * @ngdoc filter
 * @name niceOperator
 * @module bns.spaceOps.niceOperator
 *
 * @description
 * Displays a user-friendly version of the given operator
 */
function NiceOperatorFilter () {

  return function (operator) {
    switch (operator) {
      case '*':
        return '×';
      default:
        return operator;
    }
  };

}

})(angular);
