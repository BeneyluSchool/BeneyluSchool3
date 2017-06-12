'use strict';

angular.module('bns.userDirectory.config.states', [
  'ui.router',
  'bns.core.url',
  'bns.userDirectory.restangular',
  'bns.userDirectory.state',
  'bns.userDirectory.groups',
])

  .config(function ($stateProvider, URL_BASE_VIEW) {
    var onEnter = ['$rootScope', '$translate', function ($rootScope, $translate) {
      angular.element('body').addClass('user-directory');
      $rootScope.title = $translate.instant('USER_DIRECTORY.USER_DIRECTORY');
    }];
    var onExit = ['$rootScope', function ($rootScope) {
      angular.element('body').removeClass('user-directory');
      $rootScope.title = '';
    }];
    $stateProvider
      .state('userDirectory', {
        url: '/user-directory',
        views: {
          'user-directory-root': {
            templateUrl: URL_BASE_VIEW + '/user-directory/root.html',
            controller: 'UserDirectoryRootController',
            controllerAs: 'ctrl',
          }
        },
        onEnter: onEnter,
        onExit: onExit,
      })

      .state('userDirectory.base', {
        url: '', // default child state
        views: {
          'topbar': {
            templateUrl: URL_BASE_VIEW + '/user-directory/topbar.html',
            controller: 'UserDirectoryTopbarController',
            controllerAs: 'ctrl',
          },
          'navigation': {
            templateUrl: URL_BASE_VIEW + '/user-directory/navigation.html',
            controller: 'UserDirectoryNavigationController',
            controllerAs: 'ctrl',
          },
          'selection': {
            templateUrl: URL_BASE_VIEW + '/user-directory/selection.html',
            controller: 'UserDirectorySelectionController',
            controllerAs: 'ctrl',
          },
        },
      })

      .state('userDirectory.base.group', {
        url: '/groups/:id?{type:user|distribution}',
        views: {
          'scene@userDirectory': {
            templateUrl: function ($stateParams) {
              var tpl = 'scene';
              switch (getViewedType($stateParams)) {
                case 'distribution':
                  tpl = 'distribution/scene';
                  break;
              }

              return URL_BASE_VIEW + '/user-directory/' + tpl + '.html';
            },
            controllerProvider: ['$stateParams', function ($stateParams) {
              switch (getViewedType($stateParams)) {
                case 'distribution':
                  return 'UserDirectoryDistributionScene';
                default:
                  return 'UserDirectorySceneController';
              }
            }],
            controllerAs: 'ctrl',
          },
        },
        onEnter: ['$stateParams', 'userDirectoryState',
         function ($stateParams,   userDirectoryState) {
          userDirectoryState.type = getViewedType($stateParams);
        }],
      })
    ;

    function getViewedType ($stateParams) {
      return ['user', 'distribution'].indexOf($stateParams.type) > -1 ?
        $stateParams.type :
        'user'
      ;
    }
  })

;
