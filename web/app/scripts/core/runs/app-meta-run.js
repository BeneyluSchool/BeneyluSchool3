(function (angular) {
'use strict';

angular.module('bns.core.appMeta', [])

  .run(AppMetaRun)

;

/**
 * @ngdoc run
 *
 * @description
 * Exposes current app metadata to the root scope:
 * - appTitle: based on app + additional meta title + mode
 *
 * @requires $rootScope
 * @requires $translate
 * @requires navbar
 */
function AppMetaRun ($rootScope, $translate, navbar) {

  $rootScope.$watch(function () {
    return navbar.mode + (navbar.app ? navbar.app.unique_name : '');
  }, updateAppMeta);

  function updateAppMeta () {
    if (!navbar.app) {
      return ($rootScope.appTitle = '');
    }

    $rootScope.appTitle = navbar.app.label;
    if (navbar.app.meta_title) {
      $rootScope.appTitle += ' - ' + navbar.app.meta_title;
    }
    if ('back' === navbar.mode) {
      $rootScope.appTitle += ' - ' + $translate.instant('MAIN.DESCRIPTION_APP_MANAGEMENT');
    }
  }

}

})(angular);
