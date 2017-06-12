(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.core.delay
 */
angular.module('bns.core.delay', [])

  .directive('bnsDelay', BnsDelayDirective)

;

/**
 * @ngdoc directive
 * @name bnsDelay
 * @module bns.core.delay
 *
 * @description
 * Delays compilation of the element it is attached to.
 *
 * ** Attributes **
 *  - `bnsDelay` {=Integer}: Number of milliseconds to wait before compilation.
 *
 * @example
 * <!-- Compiled after 1 second -->
 * <my-directive bns-delay="1000"></my-directive>
 *
 * @requires $timeout
 * @requires $compile
 */
function BnsDelayDirective ($timeout, $compile) {

  return {
    restrict: 'A',
    terminal: true,
    priority: 1050,
    link: postLink,
  };

  function postLink (scope, element, attrs) {
    var delay = parseInt(attrs.bnsDelay, 10) || 0;
    $timeout(function () {
      element.removeAttr('bns-delay');
      $compile(element)(scope);
    }, delay);
  }

}

})(angular);
