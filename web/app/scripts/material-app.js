(function (angular) {
'use strict';

var APP_NAME = 'beneyluSchoolMaterialApp';

angular.module(APP_NAME, [
  // vendor modules
  'ngCookies',
  'ngMessages',
  'ngSanitize',
  'duScroll',
  'oc.lazyLoad',
  'ui.router',
  'ui.router.state.events', // polyfill for deprecated state change events
  'angularMoment',
  'angularLocalStorage',
  'infinite-scroll',
  'ng-sortable',

  // configs
  'bns.core.baseStates',
  'bns.core.futureStates',
  'bns.core.httpAuthExceptionInterceptor',
  'bns.core.httpCacheBuster',
  'bns.core.restangularInit',
  'bns.core.translationInit',
  'bns.core.sceInit',

  // standalone UI components
  'bns.material',
  'bns.components',

  // core components
  'bns.core.appMeta',
  'bns.core.appStateProvider',
  'bns.core.arrayUtils',
  'bns.core.bindCompile',
  'bns.core.cookies',
  'bns.core.dateUtils',
  'bns.core.delay',
  'bns.core.eatClickIf',
  'bns.core.exposeComponentsRoot',
  'bns.core.formSubmit',
  'bns.core.ga',
  'bns.core.imageSrc',
  'bns.core.input',
  'bns.core.lazyload',
  'bns.core.legacy',
  'bns.core.libraries',
  'bns.core.nofTheme',
  'bns.core.trackHeight',
  'bns.core.views',

  // filters
  'bns.core.assetize',
  'bns.core.characters',
  'bns.core.nl2br',
  'bns.core.plainText',
  'bns.core.tokenize',
  'bns.core.trustHtml',
  'bns.core.unaccent',

  // app-wide stuff
  'bns.main.appController',
  'bns.main.apps',
  'bns.main.attachments',
  'bns.main.autoLoginBox',
  'bns.main.beta',
  'bns.main.choiceCreate',
  'bns.main.correction',
  'bns.main.docLink',
  'bns.main.dummyController',
  'bns.main.entityList',
  'bns.main.featureFlags',
  'bns.main.highchart',
  'bns.main.navbar',
  'bns.main.sparkle',
  'bns.main.statistic',
  'bns.main.tinymce',
  'bns.main.userPicker',
  'bns.mediaLibrary.bindMedias',
  'bns.mediaLibrary.mediaPreview',
  'bns.mediaLibrary.viewerInvoker',
  'bns.starterKit',
  'bns.user',

  // apps
  'bns.blog',
  'bns.classroom',
  'bns.liaisonbook',
  'bns.portal',
  'bns.profile',
  'bns.minisite',
])

  .config(AppDecoratorConfig)
  .run(AppDecoratorRun)

;

function AppDecoratorConfig ($controllerProvider) {
  var app = angular.module(APP_NAME);

  app.controllerProvider = $controllerProvider;
}

function AppDecoratorRun (legacyApp) {
  var app = angular.module(APP_NAME);

  app.loadLegacyApp = function () {
    return legacyApp.load();
  };
}

}) (angular);
