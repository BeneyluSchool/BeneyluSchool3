(function (angular) {
'use strict';

angular.module('bns.core.legacy', [
  'oc.lazyLoad',
  'bns.core.parameters',
])

  .constant('LEGACY_APP_NAME', 'beneyluschoolApp')
  .config(LazyLoadConfig)
  .factory('legacyApp', LegacyAppFactory)
  .directive('bnsLegacy', BNSLegacyDirective)

;

function LazyLoadConfig ($ocLazyLoadProvider, parametersProvider, LEGACY_APP_NAME) {

  var basePath = parametersProvider.get('app_base_path');
  var assetsPath = basePath + '/angular/assets';
  var version = parametersProvider.get('version');

  var files = [
    // 'css!'+basePath+'/css/base.css',
    assetsPath+'/styles/main.css',
    assetsPath+'/styles/viewer.css',
    assetsPath+'/styles/media-library.css',
    assetsPath+'/styles/workshop.css',
    // basePath+'/js/scripts.js',
    assetsPath+'/scripts/vendors.js',
    assetsPath+'/scripts/scripts.js',
  ];

  // these calls are not intercepted by $http: manually add cache buster param
  for (var i = 0; i < files.length; i++) {
    files[i] += '?v=' + version;
  }

  $ocLazyLoadProvider.config({
    modules: [
      {
        debug: true,
        name: LEGACY_APP_NAME,
        files: files,
        serie: true,
      }
    ],
  });

}

function LegacyAppFactory ($ocLazyLoad, LEGACY_APP_NAME) {

  return {
    load: function () {
      return $ocLazyLoad.load(LEGACY_APP_NAME)
        .catch(function error (response) {
          console.error(response);
          throw response;
        })
      ;
    }
  };

}

function BNSLegacyDirective ($compile, legacyApp) {

  return {
    priority: 1050,
    terminal: true,
    compile: compile,
  };

  function compile () {
    return function (scope, element) {
      legacyApp.load().then(function () {
        element.removeAttr('bns-legacy');
        $compile(element)(scope);
      });
    };
  }

}

}) (angular);
