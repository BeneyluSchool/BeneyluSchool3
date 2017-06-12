(function (angular) {
'use strict';

angular.module('bns.core.exposeComponentsRoot', [
  'bns.components.dialog',
  'bns.components.toast',
])

  .run(RootComponentsRun)

;

/**
 * @ngdoc run
 *
 * @description
 * Exposes core components to the root scope, for easy access from templates.
 *
 * @requires $rootScope
 * @requires dialog
 * @requires toast
 */
function RootComponentsRun ($rootScope, dialog, toast) {

  $rootScope.dialog = dialog;
  $rootScope.toast = toast;

}

})(angular);
