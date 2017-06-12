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
  'ct.ui.router.extras.sticky', // app-wide dependency, else it doesn't work
  'ct.ui.router.extras.previous', // app-wide dependency, else it doesn't work
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
  'bns.components',

  // core components
  'bns.core.appMeta',
  'bns.core.arrayUtils',
  'bns.core.cookies',
  'bns.core.dateUtils',
  'bns.core.delay',
  'bns.core.eatClickIf',
  'bns.core.exposeComponentsRoot',
  'bns.core.formSubmit',
  'bns.core.ga',
  'bns.core.imageSrc',
  'bns.core.input',
  'bns.core.legacy',
  'bns.core.libraries',
  'bns.core.trackHeight',
  'bns.core.views',

  // filters
  'bns.core.assetize',
  'bns.core.nl2br',
  'bns.core.tokenize',
  'bns.core.trustHtml',
  'bns.core.unaccent',

  // app-wide stuff
  'bns.main.appController',
  'bns.main.apps',
  'bns.main.attachments',
  'bns.main.beta',
  'bns.main.choiceCreate',
  'bns.main.docLink',
  'bns.main.entityList',
  'bns.main.highchart',
  'bns.main.navbar',
  'bns.main.sparkle',
  'bns.main.statistic',
  'bns.main.tinymce',
  'bns.main.userPicker',
  'bns.mediaLibrary.bindMedias',
  'bns.mediaLibrary.mediaPreview',
  'bns.starterKit',
  'bns.user',

  // apps
  'bns.account',
  'bns.blog',
  'bns.breakfastTour',
  'bns.builders',
  'bns.calendar',
  'bns.circusBirthday',
  'bns.classroom',
  'bns.embed',
  'bns.homework',
  'bns.liaisonbook',
  'bns.lsu',
  'bns.lunch',
  'bns.messaging',
  'bns.olympics',
  'bns.profile',
  'bns.search',
  'bns.spaceOps',
  'bns.statistic',
  'bns.twoDegrees',
  'bns.minisite',
  'bns.campaign',
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
