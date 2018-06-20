(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.core.bindCompile
 */
angular.module('bns.core.bindCompile', [])

  .directive('bnsBindCompile', BnsBindCompileDirective)

;

/**
 * @ngdoc directive
 * @name bnsBindCompile
 * @module bns.core.bindCompile
 *
 * @description
 * Binds and compiles the given content.
 *
 * @example
 * <!-- bind custom content and compile it -->
 * <any bns-bind-compile="myCustomContent"></any>
 *
 * @requires $compile
 */
function BnsBindCompileDirective ($compile) {

  return {
    restrict: 'A',
    link: postLink,
  };

  function postLink (scope, element, attrs) {
    scope.$watch(attrs.bnsBindCompile, bindAndCompile);

    function bindAndCompile (html) {
      element[0].innerHTML = html;
      $compile(element.contents())(scope);
    }
  }

}

})(angular);
