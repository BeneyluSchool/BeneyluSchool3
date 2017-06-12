'use strict';

var beneyluSchoolApp = angular.module('beneyluSchoolApp', [
  'ui.router',
  'ct.ui.router.extras.sticky', // app-wide dependency, else it doesn't work
  'bns.userDirectory',
  'bns.mediaLibrary',
  'bns.user',
  'bns.workshop',
  'bns.viewer',
  'bns.uploader',
  'bns.statistic',
]);

  beneyluSchoolApp.config(function ($controllerProvider, $stateProvider, $urlRouterProvider) {
    beneyluSchoolApp.controllerProvider = $controllerProvider;

    // redirect unmatched urls
    $urlRouterProvider.otherwise('/');

    $stateProvider.state('root', {
      url: '/',
      template: '<ui-view />',
      views: {
        // add a css class on the root, only for this state
        '@': {
          controller: function ($scope) {
            $scope.rootClasses = [ 'empty' ];
          },
        },
      },
    });
  })

  .run(function () {
    angular.element('body').addClass('ng-app');
  })

;
