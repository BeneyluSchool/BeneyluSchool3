(function (angular) {
'use strict'  ;

angular.module('bns.search.config.states', [
  'ui.router',
  'bns.core.appStateProvider',

  'bns.search.frontController',
  'bns.search.backLogController',
  'bns.search.backWhitelistController',
  'bns.search.backGeneralWhitelistController',
])

  .config(SearchStatesConfig)

;

function SearchStatesConfig ($stateProvider, appStateProvider) {
  /* ------------------------------------------------------------------------ *\
   *    States
  \* ------------------------------------------------------------------------ */

  var rootState = appStateProvider.createRootState('search');
  var backState = appStateProvider.createBackState();

  $stateProvider
    .state('app.search', rootState)

    // Back
    // ---------------------

    .state('app.search.back', angular.merge(backState, {
      templateUrl: 'views/search/back.html',
    }))

    .state('app.search.back.log', {
      url: '', //default back state
      views: {
        content: {
          templateUrl: 'views/search/back/log-content.html',
          controller: 'SearchBackLog',
          controllerAs: 'ctrl',
        },
      }
    })

    .state('app.search.back.whitelist', {
      abstract: true,
      views: {
        sidebar_whitelist: {
          templateUrl: 'views/search/back/whitelist-sidebar.html',
        },
      },
    })

    // .state('app.search.back.whitelist.personal', {
    //   url: '/white-list',
    //   views: {
    //     'actionbar@app.search.back': {
    //       templateUrl: 'views/search/back/whitelist-actionbar.html',
    //       controller: 'SearchBackWhitelist',
    //       controllerAs: 'ctrl',
    //     },
    //     'content@app.search.back': {
    //       templateUrl: 'views/search/back/whitelist-content.html',
    //       controller: 'SearchBackWhitelist',
    //       controllerAs: 'ctrl',
    //     },
    //   }
    // })

    .state('app.search.back.whitelist.general', {
      url: '/general-white-list',
      resolve: {
        'generalWhiteList' : function ( Search ) {
          return Search.one('general-white-list').get();
        }
      },
      views: {
        sidebar_whitelist_general: {
          templateUrl: 'views/search/back/general-whitelist-sidebar.html',
          controller: 'SearchBackGeneralWhitelistSidebar',
          controllerAs: 'ctrl',
        },
        'content@app.search.back': {
          templateUrl: 'views/search/back/general-whitelist-content.html',
          controller: 'SearchBackGeneralWhitelistContent',
          controllerAs: 'ctrl',
        },
      }
    })


    // Front
    // ---------------------

    .state('app.search.front', {
      url: '',
      templateUrl: 'views/search/front.html',
      onEnter: ['statistic', function (statistic) {
        angular.element('body').attr('data-mode', 'front');
        statistic.visit('SEARCH');
      }],
      onExit: function () {
        angular.element('body').removeAttr('data-mode');
      },
      controller: 'SearchFront',
      controllerAs: 'ctrl',
    })
  ;

}

})(angular);
