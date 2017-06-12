(function (angular) {
'use strict';

angular.module('bns.core.legacy', [
  'oc.lazyLoad',
  'bns.core.parameters',
])

  .constant('LEGACY_APP_NAME', 'beneyluschoolApp')
  .config(LazyLoadConfig)
  .factory('legacyApp', LegacyAppFactory)

;

function LazyLoadConfig ($ocLazyLoadProvider, parametersProvider, LEGACY_APP_NAME) {

  var basePath = parametersProvider.get('app_base_path');
  var assetsPath = basePath + '/angular/assets';
  var version = parametersProvider.get('version');

  var files = [
    // 'css!'+basePath+'/css/base.css',
    'css!'+assetsPath+'/styles/main.css',
    'css!'+assetsPath+'/styles/viewer.css',
    'css!'+assetsPath+'/styles/media-library.css',
    'css!'+assetsPath+'/styles/workshop.css',
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

}) (angular);
