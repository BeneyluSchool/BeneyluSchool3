(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.main.weekToolbar
 */
angular.module('bns.main.weekToolbar', [
  'angularMoment',
])

  .directive('bnsWeekToolbar', BNSWeekToolbarDirective)

;

/**
 * @ngdoc directive
 * @name bnsWeekToolbar
 * @module bns.main.weekToolbar
 *
 * @description
 * Displays a week navigation toolbar
 *
 * ** Attributes **
 *  - `start`: the starting date. MUST be a momentjs object
 */
function BNSWeekToolbarDirective (moment) {

  return {
    restrict: 'EA',
    scope: {
      start: '=',
      end: '=?',
      onPrev: '&',
      onNext: '&',
    },
    templateUrl: 'views/main/directives/bns-week-toolbar.html',
    link: postLink,
  };

  function postLink (scope) {
    scope.$watch('start', function () {
      if (!(scope.start && moment.isMoment(scope.start))) {
        return;
      }

      // default end date
      if (!scope.end) {
        scope.end = scope.start.clone().add(4, 'days');
      }

      scope.prev = scope.start.clone().subtract(7, 'days');
      scope.next = scope.start.clone().add(7, 'days');
    });
  }

}

})(angular);
