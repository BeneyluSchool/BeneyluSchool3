(function (angular) {
'use strict';

angular.module('bns.olympics.config.states', [
  'ui.router',
  'bns.core.appStateProvider',

  'bns.olympics.mapController',
  'bns.olympics.gameController',
])

  .config(OlympicsStatesConfig)

;

function OlympicsStatesConfig ($stateProvider, appStateProvider) {

  var rootState = appStateProvider.createRootState('olympics');
  var rootOnEnter = rootState.onEnter;
  var rootOnExit = rootState.onExit;

  var onEnter = ['$rootScope', '$injector', 'navbar', function ($rootScope, $injector, navbar) {
    $rootScope.disableZoom = true;
    $injector.invoke(rootOnEnter);
    navbar.hide();
  }];

  var onExit = ['$rootScope', '$injector', 'navbar', function ($rootScope, $injector, navbar) {
    $rootScope.disableZoom = false;
    $injector.invoke(rootOnExit);
    navbar.show();
  }];

  $stateProvider
    .state('app.olympics', angular.extend(rootState, {
      onEnter: onEnter,
      onExit: onExit,
      template: '<ui-view md-theme="olympics" id="app-olympics" class="flex layout-column app-olympics">Coucou</ui-view>',
      resolve: {
        canManage: ['Users', function (Users) {
          return Users.hasCurrentRight('OLYMPICS_ACTIVATION')
            .then(function () { return true; })
            .catch(function () { return false; }) // silence failure to avoid canceling state navigation
          ;
        }],
      },
    }))

    .state('app.olympics.map', {
      url: '', // default child state
      templateUrl: 'views/olympics/map.html',
      controller: 'OlympicsMap',
      controllerAs: 'ctrl',
    })

    .state('app.olympics.game', {
      url: '/game/{game:tennis|rowing|fencing}',
      templateUrl: 'views/olympics/game.html',
      controller: 'OlympicsGame',
      controllerAs: 'ctrl',
    })

    .state('app.olympics.heroes', {
      url: '/heroes',
      templateUrl: 'views/olympics/heroes.html',
    })
  ;

}

})(angular);
