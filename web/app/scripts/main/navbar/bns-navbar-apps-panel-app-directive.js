(function (angular) {
'use strict';

angular.module('bns.main.navbar')

  .directive('bnsNavbarAppsPanelApp', BNSNavbarAppsPanelAppDirective)

;

/**
 * @ngdoc directive
 * @name bnsNavbarAppsPanelApp
 * @module bns.main.navbar
 *
 * @description
 * A simple template directive to display an app in the navbar panel grid.
 * Must be used inside a `bns-navbar-apps-panel` directive.
 */
function BNSNavbarAppsPanelAppDirective () {

  return {
    require: '^bnsNavbarAppsPanel',
    templateUrl: 'views/main/navbar/bns-navbar-apps-panel-app.html',
  };

}

})(angular);
