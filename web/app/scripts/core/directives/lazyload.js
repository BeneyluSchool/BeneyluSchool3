(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.core.lazyload
 */
angular.module('bns.core.lazyload', [])

  .directive('bnsLazyload', BnsLazyloadDirective)

;

/**
 * @ngdoc directive
 * @name bnsLazyload
 * @module bns.core.lazyload
 *
 * @description
 * Delays compilation until the given module is lazy loaded.
 *
 * @requires $compile
 * @requires $ocLazyLoad
 */
function BnsLazyloadDirective ($compile, $ocLazyLoad) {

  return {
    priority: 1040,
    terminal: true,
    compile: compile,
  };

  function compile () {
    return function (scope, element, attrs) {
      $ocLazyLoad.load(attrs.bnsLazyload)
        .then(function () {
          element.removeAttr('bns-lazyload');
          $compile(element)(scope);
        })
        .catch(function error (response) {
          console.error(response);
          throw response;
        })
      ;
    };
  }

}

}) (angular);
