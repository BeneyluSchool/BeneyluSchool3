(function (angular) {
'use strict'  ;

angular.module('bns.calendar.config.states', [
  'ui.router',
  'bns.core.appStateProvider',
  'bns.calendar.backWeekControllers',
  'bns.calendar.backFormControllers',
  'bns.calendar.frontWeekControllers',
])

  .config(CalendarStatesConfig)

;

function CalendarStatesConfig ($stateProvider, appStateProvider) {

  /* ------------------------------------------------------------------------ *\
   *    States
  \* ------------------------------------------------------------------------ */

  var rootState = appStateProvider.createRootState('calendar');
  var backState = appStateProvider.createBackState();

  $stateProvider
    .state('app.calendar', angular.merge(rootState, {
      resolve: {
        agendas: ['Calendars', function (Calendars) {
          return Calendars.all('agendas').getList();
        }],
      },
    }))

    // Back
    // ---------------------

    .state('app.calendar.back', backState)

    .state('app.calendar.back.week', {
      url: '',
      views: {
        actionbar: {
          templateUrl: 'views/calendar/back/week-actionbar.html',
        },
        content: {
          templateUrl: 'views/calendar/back/week-content.html',
          controller: 'CalendarBackWeekContent',
          controllerAs: 'ctrl',
        },
        sidebar: {
          templateUrl: 'views/calendar/back/week-sidebar.html',
          controller: 'CalendarBackWeekSidebar',
          controllerAs: 'ctrl',
        },
      },
      params: { // custom data
        defaultDate: null, // start date used in redirects
      },
    })

    .state('app.calendar.back.create', {
      url: '/create?start&end&all_day',
      resolve: {
        event: ['moment', '$stateParams', function (moment, $stateParams) {
          // create event object on the fly
          var event = {};
          event.start = $stateParams.start ? moment($stateParams.start) : null;
          event.end = $stateParams.end ? moment($stateParams.end) : null;
          event.is_all_day = $stateParams.all_day && 'false' !== $stateParams.all_day;

          return event;
        }],
      },
      views: {
        actionbar: {
          templateUrl: 'views/calendar/back/form-actionbar.html',
          controller: 'CalendarBackFormActionbar',
          controllerAs: 'ctrl',
        },
        sidebar: {
          templateUrl: 'views/calendar/back/form-sidebar.html',
          controller: 'CalendarBackFormSidebar',
          controllerAs: 'ctrl',
        },
        content: {
          templateUrl: 'views/calendar/back/form-content.html',
          controller: 'CalendarBackFormContent',
          controllerAs: 'ctrl',
        },
      },
    })

    .state('app.calendar.back.edit', {
      url: '/edit/:id',
      resolve: {
        event: ['$stateParams', 'Calendars', function ($stateParams, Calendars) {
          return Calendars.one('events', $stateParams.id).get();
        }],
      },
      views: {
        actionbar: {
          templateUrl: 'views/calendar/back/form-actionbar.html',
          controller: 'CalendarBackFormActionbar',
          controllerAs: 'ctrl',
        },
        sidebar: {
          templateUrl: 'views/calendar/back/form-sidebar.html',
          controller: 'CalendarBackFormSidebar',
          controllerAs: 'ctrl',
        },
        content: {
          templateUrl: 'views/calendar/back/form-content.html',
          controller: 'CalendarBackFormContent',
          controllerAs: 'ctrl',
        },
      },
    })

    // Front
    // ---------------------

    .state('app.calendar.front', {
      abstract: true,
      templateUrl: 'views/calendar/front.html',
      onEnter: function () {
        angular.element('body').attr('data-mode', 'front');
      },
      onExit: function () {
        angular.element('body').removeAttr('data-mode');
      },
    })

    .state('app.calendar.front.week', {
      url: '?id',
      views: {
        content: {
          templateUrl: 'views/calendar/front/week-content.html',
          controller: 'CalendarFrontWeekContent',
          controllerAs: 'ctrl',
        },
        sidebar: {
          templateUrl: 'views/calendar/front/week-sidebar.html',
          controller: 'CalendarFrontWeekSidebar',
          controllerAs: 'ctrl',
        },
      },
    })
  ;

}

})(angular);
