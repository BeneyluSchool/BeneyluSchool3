(function (angular) {
'use strict'  ;

angular.module('bns.twoDegrees.config.states', [
  'ui.router',
  'bns.core.appStateProvider',
  'bns.main.navbar',

  'bns.twoDegrees.activityController',
  'bns.twoDegrees.mapController',
  'bns.twoDegrees.innovationsControllers',
  'bns.twoDegrees.solveController',
  'bns.twoDegrees.splashController',
  'bns.twoDegrees.wordsControllers',
])

  .config(TwoDegreesStatesConfig)

;

function TwoDegreesStatesConfig ($stateProvider, appStateProvider) {

  var rootState = appStateProvider.createRootState('two-degrees');
  var rootOnEnter = rootState.onEnter;
  var rootOnExit = rootState.onExit;

  var onEnter = ['$injector', 'navbar', function ($injector, navbar) {
    $injector.invoke(rootOnEnter);
    navbar.hide();
  }];

  var onExit = ['$injector', 'navbar', function ($injector, navbar) {
    $injector.invoke(rootOnExit);
    navbar.show();
  }];

  var requireChallengeNotCompleted = ['challenge', function (challenge) {
    if (!challenge.completed) {
      return challenge;
    }

    throw 'Challenge already completed';
  }];

  var requirePreviousChallengeCompleted = ['_', 'TwoDegrees', 'challenge', function (_, TwoDegrees, challenge) {
    return TwoDegrees.getChallenges().then(function success (challenges) {
      if (1 === challenge.position) {
        return true;
      } else {
        var previous = _.find(challenges, {position: challenge.position - 1});
        if (previous && previous.completed) {
          return true;
        }
      }
      throw 'Previous challenge not completed';
    });
  }];

  $stateProvider
    .state('app.twoDegrees', angular.extend(rootState, {
      onEnter: onEnter,
      onExit: onExit,
      template: '<ui-view md-theme="two-degrees" id="app-two-degrees" class="flex layout-column app-two-degrees"></ui-view>',
      resolve: {
        hasRight: ['Users', function (Users) {
          Users.hasCurrentRight('TWO_DEGREES_ACCESS').then(function success (result) {
            if (!result) {
              throw 'No access';
            }
          });
        }],
      }
    }))

    .state('app.twoDegrees.splash', {
      url: '',
      templateUrl: 'views/two-degrees/splash.html',
      controller: 'TwoDegreesSplash',
      controllerAs: 'ctrl',
    })

    .state('app.twoDegrees.map', {
      url: '/map',
      templateUrl: 'views/two-degrees/map.html',
      controller: 'TwoDegreesMap',
      controllerAs: 'ctrl',
    })

    .state('app.twoDegrees.challenge', {
      url: '/:code',
      abstract: true,
      resolve: {
        challenge: ['$stateParams', 'TwoDegrees', function ($stateParams, TwoDegrees) {
          return TwoDegrees.getChallenge($stateParams.code);
        }],
      },
      onEnter: ['challenge', function (challenge) {
        angular.element('body').attr('data-challenge', challenge.code);
      }],
      onExit: function () {
        angular.element('body').removeAttr('data-challenge');
      },
    })

    .state('app.twoDegrees.challenge.solve', {
      url: '/solve',
      resolve: {
        challenge: requireChallengeNotCompleted,
        dummy: requirePreviousChallengeCompleted,
      },
      views: {
        '@app.twoDegrees': {
          templateUrl: 'views/two-degrees/challenge/solve.html',
          controller: 'TwoDegreesSolve',
          controllerAs: 'ctrl',
        }
      },
    })

    .state('app.twoDegrees.innovations', {
      url: '/innovations',
      templateUrl: 'views/two-degrees/innovations.html',
      resolve: {
        innovations: ['TwoDegrees', function (TwoDegrees) {
          return TwoDegrees.getInnovations();
        }],
      },
      controller: 'TwoDegreesInnovations',
      controllerAs: 'ctrl',
    })

    .state('app.twoDegrees.innovations.detail', {
      url: '/:code',
      resolve: {
        innovation: ['_', '$stateParams', 'innovations', function (_, $stateParams, innovations) {
          var innovation = _.find(innovations, {code: $stateParams.code});
          if (!innovation) {
            console.error('Unknown innovation', $stateParams.code);
            throw 'Innovation not found';
          }

          return innovation;
        }],
      },
      views: {
        'detail': {
          templateUrl: 'views/two-degrees/innovation.html',
          controller: 'TwoDegreesInnovation',
          controllerAs: 'ctrl',
        }
      }
    })

    .state('app.twoDegrees.words', {
      url: '/words',
      templateUrl: 'views/two-degrees/words.html',
      resolve: {
        words: ['TwoDegrees', function (TwoDegrees) {
          return TwoDegrees.getWords();
        }],
      },
      controller: 'TwoDegreesWords',
      controllerAs: 'ctrl',
    })

    .state('app.twoDegrees.words.detail', {
      url: '/:code',
      resolve: {
        word: ['_', '$stateParams', 'words', function (_, $stateParams, words) {
          var word = _.find(words, {code: $stateParams.code});
          if (!word) {
            console.error('Unknown word', $stateParams.code);
            throw 'Word not found';
          }

          return word;
        }],
      },
      views: {
        'detail': {
          templateUrl: 'views/two-degrees/word.html',
          controller: 'TwoDegreesWord',
          controllerAs: 'ctrl',
        }
      }
    })

    .state('app.twoDegrees.activity', {
      url: '/activities/:code',
      templateUrl: 'views/two-degrees/activities/activity.html',
      resolve: {
        activity: ['$stateParams', 'TwoDegrees', function ($stateParams, TwoDegrees) {
          return TwoDegrees.getActivity($stateParams.code);
        }],
      },
      controller: 'TwoDegreesActivity',
      controllerAs: 'ctrl',
    })

    .state('app.twoDegrees.heroes', {
      url: '/heores',
      templateUrl: 'views/two-degrees/heroes.html',
    })
  ;

}

})(angular);
