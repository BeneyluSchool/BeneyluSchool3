(function (angular) {
'use strict';

angular.module('bns.core.futureStates', [
  'ui.router',
  'oc.lazyLoad',
  'bns.core.legacy',
  'bns.core.parameters',
  'bns.core.global',
  'bns.core.baseStates'
])

  .config(FutureStatesConfig)

;

function FutureStatesConfig ($stateProvider, $urlRouterProvider, $ocLazyLoadProvider, parametersProvider, globalProvider, LEGACY_APP_NAME, BASE_STATE_URL) {

  var basePath = parametersProvider.get('app_base_path');
  var version = parametersProvider.get('version');
  var locale = globalProvider.get('locale');


  // redirects do not work when declared in a future module lazyloaded by angularjs router lazy loaded by Angular Upgrade, so we have to move redirect configs here :(

  configureLazyLoadedModule('account');

  configureLazyLoadedModule('archeology');

  configureLazyLoadedModule('breakfastTour');

  configureLazyLoadedModule('builders');

  configureLazyLoadedModule('calendar', { dependencies : [LEGACY_APP_NAME, 'userDirectory'], vendors: true, vendorsLocale: true });

  configureLazyLoadedModule('campaign', { dependencies : [LEGACY_APP_NAME, 'userDirectory'] });

  configureLazyLoadedModule('circusBirthday');

  configureLazyLoadedModule('competition', { dependencies : [LEGACY_APP_NAME, 'userDirectory'] });

  configureLazyLoadedModule('embed');

  configureLazyLoadedModule('homework', { dependencies : [LEGACY_APP_NAME, 'userDirectory'] });
  $urlRouterProvider.when(BASE_STATE_URL + '/homework', BASE_STATE_URL + '/homework/');
  $urlRouterProvider.when(BASE_STATE_URL + '/homework/manage', BASE_STATE_URL + '/homework/manage/week');
  $urlRouterProvider.when(BASE_STATE_URL + '/homework/manage/week', BASE_STATE_URL + '/homework/manage/week/');

  configureLazyLoadedModule('lsu');

  configureLazyLoadedModule('lunch');
  $urlRouterProvider.when(BASE_STATE_URL + '/lunch', BASE_STATE_URL + '/lunch/');
  $urlRouterProvider.when(BASE_STATE_URL + '/lunch/manage', BASE_STATE_URL + '/lunch/manage/');

  configureLazyLoadedModule('messaging', { dependencies : [LEGACY_APP_NAME, 'userDirectory'] });
  $urlRouterProvider.when(BASE_STATE_URL + '/messaging', BASE_STATE_URL + '/messaging/inbox');
  $urlRouterProvider.when(BASE_STATE_URL + '/messaging/compose', BASE_STATE_URL + '/messaging/compose/');

  //configureLazyLoadedModule('minisite');

  configureLazyLoadedModule('olympics');

  configureLazyLoadedModule('olympicsTraining');

  configureLazyLoadedModule('search');

  configureLazyLoadedModule('spaceOps');

  configureLazyLoadedModule('statistic', { vendors: true });
  $urlRouterProvider.when(BASE_STATE_URL + '/statistics', BASE_STATE_URL + '/statistics/base/');
  $urlRouterProvider.when(BASE_STATE_URL + '/statistics/', BASE_STATE_URL + '/statistics/base/');
  $urlRouterProvider.when(BASE_STATE_URL + '/statistics/base', BASE_STATE_URL + '/statistics/base/');

  configureLazyLoadedModule('twoDegrees', { dependencies : ['breakfastTour'] });

  configureLazyLoadedModule('mediaLibrary', { dependencies : [LEGACY_APP_NAME, 'userDirectory'] });

  configureLazyLoadedModule('userDirectory', { dependencies : [LEGACY_APP_NAME] });

  // also configure dependencies in the bns.mediaLibrary.viewerInvoker module
  configureLazyLoadedModule('workshop', { dependencies : [LEGACY_APP_NAME,  'userDirectory', 'mediaLibrary'] });

  // camelCase to dash-case
  function camelToDash (str) {
    return str.replace(/([A-Z])/g, function (match) {
      return '-' + match.toLowerCase();
    });
  }

  function configureLazyLoadedModule (name, config) {
    var assetsPath = basePath + '/assets/modules';

    config = angular.extend({
        dependencies : [],
        vendors: false,
        vendorsLocale: false,
      }, config);

    var files = [
      assetsPath + '/' + camelToDash(name) + '.scripts.js',
      assetsPath + '/' + camelToDash(name) + '.views.js',
    ];

    if (config.vendorsLocale) {
      files.unshift(assetsPath + '/' + camelToDash(name) + '.vendors-' + locale + '.js');
    }

    if (config.vendors) {
      files.unshift(assetsPath + '/' + camelToDash(name) + '.vendors.js');
    }

    // these calls are not intercepted by $http: manually add cache buster param
    for (var i = 0; i < files.length; i++) {
      files[i] += '?v=' + version;
    }

    $ocLazyLoadProvider.config({
      modules: [
        {
          debug: true,
          name: name,
          files: files,
          serie: true,
        }
      ],
    });

    $stateProvider.state('app.' + name + '.**', {
      url: '/' + camelToDash(name),
      lazyLoad: function ($transition$) {
        var $lazyLoad = $transition$.injector().get('$ocLazyLoad');
        // TODO: make sure the material core app is loaded before loading the module
        var modules = angular.copy(config.dependencies);
        modules.push(name);
        var promise;
        angular.forEach(modules, function (module) {
          if (!promise) {
            promise = $lazyLoad.load(module);
          } else {
            promise = promise.then(function () {
              return $lazyLoad.load(module);
            });
          }
        });

        return promise.catch(function error (response) {
          console.error(response);
          throw response;
        });

      },
    });
  }

}

}) (angular);
