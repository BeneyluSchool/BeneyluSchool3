(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.core.eatClickIf
 */
angular.module('bns.core.eatClickIf', [])

  .directive('bnsEatClickIf', BNSEatClickIfDirective)

;

/**
 * @ngdoc directive
 * @name bnsEatClickIf
 * @module bns.core.eatClickIf
 *
 * @description
 * Eats click events if the given condition is met.
 *
 * @example
 * <!-- disable state navigation based on some scope method -->
 * <any bns-eat-click-if="shouldDisableState()" ui-sref="my.state"></any>
 *
 * <!-- always disable clicks -->
 * <any bns-eat-click-if="true"></any>
 *
 * @requires $rootScope
 * @requires $parse
 */
function BNSEatClickIfDirective ($rootScope, $parse) {

  return {
    priority: 100, // before ng-click
    compile: compile,
  };

  function compile (element, attrs) {
    var fn = $parse(attrs.bnsEatClickIf);

    return {
      pre: function preLink (scope, element) {
        element.on('click', function (event) {
          if ($rootScope.$$phase) {
            scope.$evalAsync(callback);
          } else {
            scope.$apply(callback);
          }

          function callback () {
            if (fn(scope, {$event: event})) {
              event.stopImmediatePropagation();
              event.preventDefault();

              return false;
            }
          }
        });
      },
    };
  }

}

})(angular);
