(function (angular) {
'use strict'  ;

angular.module('bns.homework.config.states', [
  'ui.router',
  'bns.core.appStateProvider',
  'bns.starterKit.service',
  'bns.homework.back.createControllers',
  'bns.homework.back.editControllers',
  'bns.homework.back.occurrenceControllers',
  'bns.homework.back.preferencesControllers',
  'bns.homework.back.subjectsControllers',
  'bns.homework.back.weekControllers',
  'bns.homework.front.dayControllers',
])

  .config(HomeworkStatesConfig)

;

function HomeworkStatesConfig ($stateProvider, $urlRouterProvider, appStateProvider) {

  // redirect from the root state to the empty front state
  $urlRouterProvider.when('/homework', '/homework/');
  $urlRouterProvider.when('/homework/manage', '/homework/manage/week');
  $urlRouterProvider.when('/homework/manage/week', '/homework/manage/week/');

  var requirePreviousState = ['$state', function ($state) {
    return {
      name: $state.current.name,
      params: $state.params,
    };
  }];

  var rootState = appStateProvider.createRootState('homework');
  var backState = appStateProvider.createBackState();

  $stateProvider
    .state('app.homework', angular.merge(rootState, {
      resolve: {
        preferences: ['Homeworks', function (Homeworks) {
          return Homeworks.one('preferences').get();
        }],
        starterKit: ['starterKit', function (starterKit) {
          return starterKit.boot('HOMEWORK');
        }],
      },
      template: '<bns-starter-kit-progress></bns-starter-kit-progress>' + rootState.template,
    }))

    .state('app.homework.back', angular.extend(backState, {
      templateUrl: 'views/homework/back.html',
      controller: ['$scope', 'starterKit', function ($scope, starterKit) {
        $scope.hasStarterKit = starterKit !== false;
      }],
    }))

    // Back week: split in two states to avoid reload of sidebar at each week
    //            change
    // ---------------------

    .state('app.homework.back.week', {
      url: '/week',
      views: {
        'sidebar_week': {
          templateUrl: 'views/homework/back/week-sidebar.html',
          controller: 'HomeworkBackWeekSidebar',
          controllerAs: 'ctrl',
        },
      }
    })
    .state('app.homework.back.week.content', {
      url: '/{week:|[0-9]{4}-[0-9]{2}-[0-9]{2}}',
      views: {
        'actionbar@app.homework.back': {
          templateUrl: 'views/homework/back/week-actionbar.html',
          controller: 'HomeworkBackWeekActionbar',
          controllerAs: 'ctrl',
        },
        'content@app.homework.back': {
          templateUrl: 'views/homework/back/week-content.html',
          controller: 'HomeworkBackWeekContent',
          controllerAs: 'ctrl',
        },
      }
    })

    // Back create
    // ---------------------

    .state('app.homework.back.create', {
      url: '/create?day:date',
      resolve: {
        previousState: requirePreviousState,
      },
      views: {
        'actionbar': {
          templateUrl: 'views/homework/back/homework-actionbar.html',
          controller: 'HomeworkBackCreateActionbar',
          controllerAs: 'ctrl',
        },
        'content': {
          templateUrl: 'views/homework/back/create-content.html',
          controller: 'HomeworkBackCreateContent',
          controllerAs: 'ctrl',
        },
        'sidebar': {
          templateUrl: 'views/homework/back/homework-sidebar.html',
          controller: 'HomeworkBackCreateSidebar',
          controllerAs: 'ctrl',
        },
      }
    })

    .state('app.homework.back.occurrence', {
      url: '/occurrence/{id}',
      resolve: {
        occurrence: ['$stateParams', 'Restangular', function ($stateParams, Restangular) {
          // use base restangular since its .service() messes up nested calls...
          return Restangular.one('homeworks').one('occurrences', $stateParams.id).get();
        }],
        previousState: requirePreviousState,
      },
      views: {
        'actionbar': {
          templateUrl: 'views/homework/back/occurrence-actionbar.html',
          controller: 'HomeworkBackOccurrenceActionbar',
          controllerAs: 'ctrl',
        },
        'content': {
          templateUrl: 'views/homework/back/occurrence-content.html',
          controller: 'HomeworkBackOccurrenceContent',
          controllerAs: 'ctrl',
        },
      },
    })

    .state('app.homework.back.edit', {
      url: '/{id:int}',
      resolve: {
        homework: ['$stateParams', 'Homeworks', function ($stateParams, Homeworks) {
          return Homeworks.one($stateParams.id).get();
        }],
        previousState: requirePreviousState,
      },
      views: {
        'actionbar': {
          templateUrl: 'views/homework/back/homework-actionbar.html',
          controller: 'HomeworkBackEditActionbar',
          controllerAs: 'ctrl',
        },
        'content': {
          templateUrl: 'views/homework/back/edit-content.html',
          controller: 'HomeworkBackEditContent',
          controllerAs: 'ctrl',
        },
        'sidebar': {
          templateUrl: 'views/homework/back/homework-sidebar.html',
          controller: 'HomeworkBackEditSidebar',
          controllerAs: 'ctrl',
        },
      },
    })

    .state('app.homework.back.custom', {
      abstract: true,
      views: {
        'sidebar_custom': {
          templateUrl: 'views/homework/back/custom-sidebar.html',
        },
      },
    })

    .state('app.homework.back.custom.preferences', {
      url: '/preferences',
      views: {
        'actionbar@app.homework.back': {
          templateUrl: 'views/homework/back/preferences-actionbar.html',
          controller: 'HomeworkBackPreferencesActionbar',
          controllerAs: 'ctrl',
        },
        'content@app.homework.back': {
          templateUrl: 'views/homework/back/preferences-content.html',
          controller: 'HomeworkBackPreferencesContent',
          controllerAs: 'ctrl',
        },
      }
    })

    .state('app.homework.back.custom.subjects', {
      url: '/subjects',
      views: {
        'actionbar@app.homework.back': {
          templateUrl: 'views/homework/back/subjects-actionbar.html',
          controller: 'HomeworkBackSubjectsActionbar',
          controllerAs: 'ctrl',
        },
        'content@app.homework.back': {
          templateUrl: 'views/homework/back/subjects-content.html',
          controller: 'HomeworkBackSubjectsContent',
          controllerAs: 'ctrl',
        },
      }
    })

    .state('app.homework.back.help', {
      url: '/help',
      views: {
        'content': {
          templateUrl: 'views/homework/back/help-content.html',
        },
      }
    })

    // Front
    // ---------------------

    .state('app.homework.front', {
      url: '',
      templateUrl: 'views/homework/front.html',
      onEnter: ['statistic', 'navbar', function (statistic, navbar) {
        angular.element('body').attr('data-mode', 'front');
        navbar.mode = 'front';
        statistic.visit('HOMEWORK');
      }],
      onExit: function () {
        angular.element('body').removeAttr('data-mode');
      },
      resolve: {
        // need media upload
        legacy: ['legacyApp', function (legacyApp) {
          return legacyApp.load();
        }],
      }
    })

    .state('app.homework.front.day', {
      url: '/{day:|[0-9]{4}-[0-9]{2}-[0-9]{2}}',
      views: {
        'content': {
          templateUrl: 'views/homework/front/day-content.html',
          controller: 'HomeworkFrontDayContent',
          controllerAs: 'ctrl',
        },
        'sidebar': {
          templateUrl: 'views/homework/front/day-sidebar.html',
          controller: 'HomeworkFrontDaySidebar',
          controllerAs: 'ctrl',
        },
      },
    })
  ;

}

})(angular);
