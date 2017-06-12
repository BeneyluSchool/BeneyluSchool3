(function (angular) {
'use strict';

angular.module('bns.main.apps')

  .directive('bnsAppMenuToggle', BNSAppMenuToggleDirective)

;

/**
 * @ngdoc directive
 * @name bnsAppMenuToggle
 * @module bns.main.apps
 *
 * @description
 * Shortcut directive to display the app toggle menu item, in back theme.
 */
function BNSAppMenuToggleDirective () {

  return {
    scope: true,
    templateUrl: 'views/main/apps/bns-app-menu-toggle.html',
    link: postLink,
  };

  function postLink (scope, element, attrs) {
    scope.type = attrs.type;
  }

}

})(angular);
