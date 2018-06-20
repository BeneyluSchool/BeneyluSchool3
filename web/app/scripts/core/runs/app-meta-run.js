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
      $translate('MAIN.DESCRIPTION_APP_MANAGEMENT').then(function (translation) {
        $rootScope.appTitle += ' - ' + translation;
      });
    }
  }

  // manually update metas, since angularjs has no hold on the head DOM anymore
  $rootScope.$watch('title', function updateMetaTitle (newTitle, oldTitle) {
    if (newTitle !== oldTitle && newTitle) {
      angular.element('head title').text(newTitle);
    }
  });
  $rootScope.$watch('appTitle', function updateMetaAppTitle (newTitle, oldTitle) {
    if (newTitle !== oldTitle && newTitle && !$rootScope.title) {
      angular.element('head title').text(newTitle);
    }
  });
  $rootScope.$watch('description', function updateMetaDescription (newDescription, oldDescription) {
    if (newDescription !== oldDescription) {
      angular.element('meta[name="description"]').attr('content', newDescription);
    }
  });

}

})(angular);
