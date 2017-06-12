(function (angular) {
'use strict';

angular.module('bns.statistic.lazyloadUiGrid', [
  'oc.lazyLoad',
  'bns.core.parameters',
])

  .config(LazyLoadConfig)
  .factory('uiGridLoader', UiGridLoaderFactory)

;

  var uiGridName = 'ui.grid';

function LazyLoadConfig ($ocLazyLoadProvider, parametersProvider) {

  var basePath = parametersProvider.get('app_base_path');
  var version = parametersProvider.get('version');

  var files = [
    basePath + '/bower_components/angular-ui-grid/ui-grid.min.js',
  ];

  // these calls are not intercepted by $http: manually add cache buster param
  for (var i = 0; i < files.length; i++) {
    files[i] += '?v=' + version;
  }

  $ocLazyLoadProvider.config({
    debug: false,
    modules: [
      {
        name: uiGridName,
        files: files,
        serie: true,
      }
    ],
  });

}

function UiGridLoaderFactory ($ocLazyLoad) {

  return {
    load: function () {
      return $ocLazyLoad.load(uiGridName)
        .catch(function error (response) {
          console.error(response);
          throw response;
        })
      ;
    }
  };

}

}) (angular);
