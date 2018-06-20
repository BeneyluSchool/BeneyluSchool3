'use strict';

angular.module('bns.viewer', [
  'bns.viewer.aspectRatio',
  'bns.viewer.bnsViewerMedia',
  'bns.viewer.workshop',
  'bns.viewer.directive',
  'bns.viewer.audioPlayer',
  'bns.core',
  'bns.resource'
])

  .config(function ($stateProvider, $compileProvider) {
    $stateProvider
      .state('viewer', {
        url: '/viewer/{resourceId:[0-9]+}',
        controller: 'ViewerStandaloneCtrl',
        template: '<div ui-view></div>',
      })
    ;

    // authorise viascolaprimaire
    // TODO made this configurable use parametersProvider
    var regex = /^\s*(https?|ftp|mailto|tel|file|viascolaprimaire):/;
    $compileProvider.aHrefSanitizationWhitelist(regex);
  });
