(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.components.passwordToggle
 */
angular.module('bns.components.passwordToggle', [])

  .directive('bnsPasswordToggle', BNSPasswordToggleDirective)

;

/**
 * @ngdoc directive
 * @name bnsPasswordToggle
 * @module bns.components.passwordToggle
 *
 * @description
 * Enhances an input[type=password] with visibility toggle. Requires a ng-model.
 *
 * @requires $compile
 */
function BNSPasswordToggleDirective ($compile) {

  return {
    restrict: 'A',
    scope: true,
    link: postLink,
    requires: 'ngModel',
  };

  function postLink (scope, element) {
    scope.toggle = toggle;

    refresh();
    addToggler();

    function toggle ($event) {
      $event.preventDefault();
      element.attr('type', scope.shown ? 'password' : 'text');
      refresh();
      element.focus();
    }

    function refresh () {
      scope.shown = 'password' !== element.attr('type');
    }

    function addToggler () {
      var toggler = angular.element('<md-button tabindex="-1" class="password-toggle md-icon-button" ng-click="toggle($event)" ng-href=""><md-icon>{{ shown ? "lock" : "visibility" }}</md-icon></md-button>');
      $compile(toggler)(scope);
      element.parent().addClass('bns-password-toggle-container').append(toggler);
    }
  }

}

})(angular);
