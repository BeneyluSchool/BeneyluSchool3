(function (angular) {
'use strict'  ;

angular.module('bns.breakfastTour.config.states', [
  'ui.router',
  'bns.core.appStateProvider',
  'bns.main.navbar',

  'bns.breakfastTour.splashController',
  'bns.breakfastTour.mapController',
  'bns.breakfastTour.unlockController',
  'bns.breakfastTour.breakfastController',
  'bns.breakfastTour.challengeController',
])

  .config(BreakfastTourStatesConfig)

;

function BreakfastTourStatesConfig ($stateProvider, appStateProvider) {

  var requireBreakfastLocked = ['breakfast', function (breakfast) {
    if (!breakfast.unlocked) {
      return breakfast;
    }

    throw 'Breakfast already unlocked';
  }];

  var requireBreakfastUnlocked = ['breakfast', function (breakfast) {
    if (breakfast.unlocked) {
      return breakfast;
    }

    throw 'Breakfast not unlocked';
  }];

  var rootState = appStateProvider.createRootState('breakfast-tour');
  var rootOnEnter = rootState.onEnter;  // keep reference to original callback,
  var rootOnExit = rootState.onExit;    // since rootState object is overriden

  $stateProvider
    .state('app.breakfastTour', angular.extend(rootState, {
      onEnter: ['$injector', 'navbar', function ($injector, navbar) {
        $injector.invoke(rootOnEnter);
        navbar.hide();
      }],
      onExit: ['$injector', 'navbar', function ($injector, navbar) {
        $injector.invoke(rootOnExit);
        navbar.show();
      }],
      resolve: {
        hasRight: ['Restangular', 'navbar', function (Restangular, navbar) {
          return navbar.getOrRefreshGroup()
            .then(function (group) {
              return Restangular.one('users/rights/BREAKFAST_TOUR_USE/groups/' + group.id)
                .get()
                .then(function (result) {
                  if (!result.has_right) {
                    throw 'Nope';
                  }
                })
              ;
            })
          ;
        }],
      }
    }))

    .state('app.breakfastTour.splash', {
      url: '',
      templateUrl: 'views/breakfast-tour/splash.html',
      controller: 'BreakfastTourSplashController',
      controllerAs: 'ctrl',
    })

    .state('app.breakfastTour.map', {
      url: '/map',
      templateUrl: 'views/breakfast-tour/map.html',
      controller: 'BreakfastTourMapController',
      controllerAs: 'ctrl',
    })

    .state('app.breakfastTour.breakfast', {
      url: '/:code',
      abstract: true,
      resolve: {
        breakfast: ['$stateParams', 'Breakfasts', function ($stateParams, Breakfasts) {
          return Breakfasts.findOne($stateParams.code);
        }],
      },
      onEnter: ['breakfast', function (breakfast) {
        angular.element('body').attr('data-breakfast', breakfast.code);
      }],
      onExit: function () {
        angular.element('body').removeAttr('data-breakfast');
      },
    })

    .state('app.breakfastTour.breakfast.unlock', {
      url: '/unlock',
      resolve: {
        breakfast: requireBreakfastLocked,
      },
      views: {
        '@app.breakfastTour': {
          templateUrl: 'views/breakfast-tour/breakfast/unlock.html',
          controller: 'BreakfastTourUnlockController',
          controllerAs: 'ctrl',
        },
      },
    })

    .state('app.breakfastTour.breakfast.success', {
      url: '/success',
      resolve: {
        breakfast: requireBreakfastUnlocked,
      },
      views: {
        '@app.breakfastTour': {
          templateUrl: 'views/breakfast-tour/breakfast/success.html',
          controller: 'BreakfastTourBreakfastController',
          controllerAs: 'ctrl',
        },
      },
    })

    .state('app.breakfastTour.breakfast.details', {
      url: '/details',
      resolve: {
        breakfast: requireBreakfastUnlocked,
      },
      views: {
        '@app.breakfastTour': {
          templateUrl: 'views/breakfast-tour/breakfast/details.html',
          controller: 'BreakfastTourBreakfastController',
          controllerAs: 'ctrl',
        },
      },
    })

    .state('app.breakfastTour.breakfast.ingredients', {
      url: '/ingredients',
      resolve: {
        breakfast: requireBreakfastUnlocked,
      },
      views: {
        '@app.breakfastTour': {
          templateUrl: 'views/breakfast-tour/breakfast/ingredients.html',
          controller: 'BreakfastTourBreakfastController',
          controllerAs: 'ctrl',
        },
      },
    })

    .state('app.breakfastTour.breakfast.flavors', {
      url: '/flavors',
      resolve: {
        breakfast: requireBreakfastUnlocked,
      },
      views: {
        '@app.breakfastTour': {
          templateUrl: 'views/breakfast-tour/breakfast/flavors.html',
          controller: 'BreakfastTourBreakfastController',
          controllerAs: 'ctrl',
        },
      },
    })

    .state('app.breakfastTour.challenge', {
      url: '/challenge',
      resolve: {
        breakfasts: ['Breakfasts', function (Breakfasts) {
          return Breakfasts.getList().then(function success (breakfasts) {
            for (var i = 0; i < breakfasts.length; i++) {
              if (!breakfasts[i].unlocked) {
                throw 'Breakfast not unlocked';
              }
            }

            return breakfasts;
          });
        }],
      },
      templateUrl: 'views/breakfast-tour/challenge.html',
      controller: 'BreakfastTourChallengeController',
      controllerAs: 'ctrl',
      onEnter: function () {
        angular.element('body').attr('data-breakfast', 'challenge');
      },
      onExit: function () {
        angular.element('body').removeAttr('data-breakfast');
      },
    })
  ;

}

})(angular);
