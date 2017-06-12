(function (angular) {
'use strict'  ;

angular.module('bns.main.apps')

  .directive('bnsAppUninstallFader', BNSAppUninstallFaderDirective)

;

/**
 * @ngdoc directive
 * @name bnsAppUninstallFader
 * @module bns.main.apps
 *
 * @description
 * Listens to uninstall event on child nodes, and removes the element when it
 * happens.
 */
function BNSAppUninstallFaderDirective () {

  return {
    scope: true,
    link: postLink,
  };

  function postLink (scope, element) {
    scope.$on('bns.app.uninstall', function () {
      element.css('display', 'none');
    });
  }

}

})(angular);
