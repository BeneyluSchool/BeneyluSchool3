'use strict';

angular.module('bns.userDirectory.group.config.states', [
  'ui.router',
  'bns.core.url',
  'bns.userDirectory.groups',
])

  .config(function ($stateProvider, URL_BASE_VIEW) {
    $stateProvider
      // TODO: check access
      .state('userDirectory.base.group.details', {
        url: '/details',
        resolve: {
          group: ['$stateParams', 'userDirectoryGroups', function ($stateParams, userDirectoryGroups) {
            return userDirectoryGroups.get($stateParams.id);
          }],
        },
        views: {
          'navigation@userDirectory': {
            templateUrl: URL_BASE_VIEW + '/user-directory/group/navigation.html',
            controller: 'UserDirectoryGroupNavigationController',
            controllerAs: 'ctrl',
          },
          'scene@userDirectory': {
            templateUrl: URL_BASE_VIEW + '/user-directory/group/scene.html',
            controller: 'UserDirectoryGroupSceneController',
            controllerAs: 'ctrl',
          },
        }
      })
    ;
  })

;
