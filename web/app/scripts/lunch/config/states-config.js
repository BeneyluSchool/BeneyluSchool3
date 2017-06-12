(function (angular) {
'use strict'  ;

angular.module('bns.lunch.config.states', [
  'ui.router',
  'bns.core.appStateProvider',
  'bns.main.navbar',

  'bns.lunch.back.showControllers',
  'bns.lunch.back.editControllers',
  'bns.lunch.front.showControllers',
])

  .config(LunchStatesConfig)

;

function LunchStatesConfig ($stateProvider, $urlRouterProvider, appStateProvider) {

  // redirect from the root state to the empty front state
  $urlRouterProvider.when('/lunch', '/lunch/');
  $urlRouterProvider.when('/lunch/manage', '/lunch/manage/');

  var onEnterFront = function () {
    angular.element('body').attr('data-mode', 'front');
  };

  var rootState = appStateProvider.createRootState('lunch');
  var backState = appStateProvider.createBackState();

  $stateProvider
    .state('app.lunch', rootState)

    .state('app.lunch.back', backState)

    .state('app.lunch.back.week', {
      url: '/{week:|[0-9]{4}-[0-9]{2}-[0-9]{2}}',
      views: {
        'content': {
          templateUrl: 'views/lunch/back/content.html',
          controller: 'LunchWeekBackShowContentController',
          controllerAs: 'ctrl',
        },
        'actionbar': {
          templateUrl: 'views/lunch/back/actionbar.html',
          controller: 'LunchWeekBackShowActionbarController',
          controllerAs: 'ctrl',
        },
        'sidebar': {
          templateUrl: 'views/lunch/back/sidebar.html',
          controller: 'LunchWeekBackShowSidebarController',
          controllerAs: 'ctrl',
        },
      }
    })

    .state('app.lunch.back.week.edit', {
      url: '/edit',
      views: {
        'content@app.lunch.back': {
          templateUrl: 'views/lunch/back/content-edit.html',
          controller: 'LunchBackEditContentController',
          controllerAs: 'ctrl',
        },
        'actionbar@app.lunch.back': {
          templateUrl: 'views/lunch/back/actionbar-edit.html',
          controller: 'LunchBackEditActionbarController',
          controllerAs: 'ctrl',
        },
        'sidebar@app.lunch.back': {
          templateUrl: 'views/lunch/back/sidebar-edit.html',
          controller: 'LunchBackEditSidebarController',
          controllerAs: 'ctrl',
        },
      }
    })

    .state('app.lunch.front', {
      url: '',
      abstract: true,
      onEnter: onEnterFront,
      templateUrl: 'views/lunch/front.html',
        controller: function ($scope, navbar) {
          $scope.navbar = navbar;
        },
        onExit: function () {
          angular.element('body').removeAttr('data-mode');
        },
    })

    .state('app.lunch.front.views', {
     url: '/{week:|[0-9]{4}-[0-9]{2}-[0-9]{2}}',
     views: {
       'content': {
         templateUrl: 'views/lunch/front/content.html',
         controller: 'LunchWeekFrontShowContentController',
         controllerAs: 'ctrl',
       },
       'actionbar': {
         templateUrl: 'views/lunch/front/actionbar.html',
         controller: 'LunchWeekFrontShowActionbarController',
         controllerAs: 'ctrl',
       },
       'sidebar': {
         templateUrl: 'views/lunch/front/sidebar.html',
         controller: 'LunchWeekFrontShowSidebarController',
         controllerAs: 'ctrl',
       },
     }
    })
  ;

}

})(angular);
