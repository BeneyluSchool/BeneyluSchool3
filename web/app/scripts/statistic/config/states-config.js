'use strict';

angular.module('bns.statistic.config.states', [
  'ui.router',
  'bns.core.appStateProvider',
  'bns.main.navbar',
  'bns.statistic.lazyloadUiGrid',

  // controllers
  'bns.statistic.rootController',
  'bns.statistic.front.sidebarController',
  'bns.statistic.front.actionbarController',
  'bns.statistic.front.contentController',
])

  .config(function ($stateProvider, $urlRouterProvider, appStateProvider) {

    /* ------------------------------------------------------------------------ *\
     *    Defaults and redirects
     \* ------------------------------------------------------------------------ */

    $urlRouterProvider.when('/statistic', '/statistic/base');

    /* ------------------------------------------------------------------------ *\
     *    States
    \* ------------------------------------------------------------------------ */

    var rootState = appStateProvider.createRootState('statistic');
    var rootOnEnter = rootState.onEnter;
    var rootOnExit = rootState.onExit;

    $stateProvider
      .state('app.statistic', angular.extend(rootState, {
        resolve: {
          'uiGrid': ['uiGridLoader', function(uiGridLoader) {
            return uiGridLoader.load();
          }]
        },
        onEnter: ['$injector', function ($injector) {
          $injector.invoke(rootOnEnter);
          angular.element('body').addClass('back');
        }],
        onExit: ['$injector', function ($injector) {
          $injector.invoke(rootOnExit);
          angular.element('body').removeClass('back');
        }],
      }))

      .state('app.statistic.front', {
        url: '', // default child state
        templateUrl: 'views/statistic/front.html',
        controller: 'StatisticRootController',
      })

      .state('app.statistic.front.base', {
        url: '/base',
        onEnter: function () {
          angular.element('body').attr('data-mode', 'front');
        },
        onExit: function () {
          angular.element('body').removeAttr('data-mode');
        },
        views: {
          'actionbar': {
            templateUrl:  'views/statistic/front/actionbar.html',
            controller: 'StatisticFrontActionbarController',
            controllerAs: 'ctrl',
          },
          'sidebar': {
            templateUrl:  'views/statistic/front/sidebar.html',
            controller: 'StatisticFrontSidebarController',
            controllerAs: 'ctrl',
            resolve: {
              'statistics': ['statisticState', function(statisticState) {
                return statisticState.getStatistics();
              }],
              'groups': ['statisticState', function (statisticState) {
                return statisticState.getGroups();
              }]
            }
          },
        },
      })

      .state('app.statistic.front.base.page', {
        url: '/:statistic',
        views: {
          'content@app.statistic.front': {
            templateUrl:  'views/statistic/front/content.html',
            controller: 'StatisticFrontContentController',
            controllerAs: 'ctrl',
          },
        },
      })
    ;
  })

;
