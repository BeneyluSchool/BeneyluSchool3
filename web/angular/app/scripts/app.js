'use strict';

var beneyluSchoolApp = angular.module('beneyluSchoolApp', [
  'ui.router',
  'bns.user',
  'bns.viewer',
  'bns.uploader',
]);

  beneyluSchoolApp.config(function ($controllerProvider, $stateProvider, $urlRouterProvider) {
    beneyluSchoolApp.controllerProvider = $controllerProvider;

    // redirect unmatched urls
    $urlRouterProvider.otherwise('/');

    $stateProvider.state('root', {
      url: '/',
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
