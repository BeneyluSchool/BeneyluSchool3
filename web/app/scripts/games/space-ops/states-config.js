(function (angular) {
'use strict';

angular.module('bns.spaceOps.config.states', [
  'ui.router',
  'bns.core.appStateProvider',
  'bns.user.users',

  'bns.spaceOps.controllers',
])

  .config(SpaceOpsConfig)

;

function SpaceOpsConfig ($stateProvider, appStateProvider) {

  var rootState = appStateProvider.createRootState('space-ops');

  $stateProvider
    .state('app.spaceOps', angular.merge(rootState, {
      resolve: {
        me: ['Users', function (Users) {
          return Users.me();
        }],
        hasRight: ['Users', function (Users) {
          return Users.hasCurrentRight('SPACE_OPS_ACCESS').then(function success (result) {
            if (!result) {
              throw 'No access';
            }
          });
        }],
      },
    }))

    .state('app.spaceOps.base', {
      url: '',
      templateUrl: 'views/games/space-ops/base.html',
      controller: 'SpaceOpsBase',
      controllerAs: 'ctrl',
      onEnter: ['navbar', function (navbar) {
        navbar.hide();
      }],
      onExit: ['navbar', function (navbar) {
        navbar.show();
      }],
    })
  ;

}

})(angular);
