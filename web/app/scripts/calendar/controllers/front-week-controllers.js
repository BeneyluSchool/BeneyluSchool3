(function (angular) {
'use strict';

angular.module('bns.calendar.frontWeekControllers', [
  'bns.calendar.calendars',
])

  .controller('CalendarFrontWeekContent', CalendarFrontWeekContentController)
  .controller('CalendarFrontWeekSidebar', CalendarFrontWeekSidebarController)
  .controller('CalendarEventDialog', CalendarEventDialogController)
  .service('calendarFrontWeekState', CalendarFrontWeekStateFactory)

;

function CalendarFrontWeekContentController ($scope, $rootScope, $stateParams, $mdDialog, Calendars, calendarFrontWeekState) {

  // calendar date is bound to the shared state by the view
  $scope.shared = calendarFrontWeekState;

  var ctrl = this;
  ctrl.eventsSource = Calendars.one('events').getRestangularUrl();
  ctrl.config = {
    eventClick: showEventDialog,
  };

  init();

  function init () {
    // if event id was given, focus it
    if ($stateParams.id) {
      return Calendars.one('events', $stateParams.id).get()
        .then(function success (model) {
          $rootScope.$emit('calendar.selection', model.start);

          return showEventDialog(model, null, null, true);
        })
      ;
    }
  }

  function showEventDialog (model, event, fullCalendar, isLocal) {
    var eventDialog = {
      clickOutsideToClose: true,
      targetEvent: event,
      templateUrl: 'views/calendar/front/event-dialog.html',
      controller: 'CalendarEventDialog',
      controllerAs: 'dialog',
      bindToController: true,
    };

    if (model.type === 'birthday') {
      var elem = angular.copy(model);
      // Hack to prevent elem.end disappear
      elem.end = elem.end || elem.start;
      eventDialog.locals = {
        event: elem
      };
    } else if (isLocal) {
      // use given object as local event
      eventDialog.locals = {
        event: model,
      };
    } else {
      // fetch event object from API
      eventDialog.resolve = {
        event: function () {
          return Calendars.one('events', model.id).get();
        }
      };
    }

    return $mdDialog.show(eventDialog);
  }

}

function CalendarFrontWeekSidebarController ($scope, agendas, calendarFrontWeekState) {

  var shared = $scope.shared = calendarFrontWeekState;
  shared.hiddenAgendas = [];

  var ctrl = this;
  ctrl.agendas = agendas;
  ctrl.toggleVisibility = toggleVisibility;
  ctrl.isHidden = isHidden;

  function toggleVisibility (agenda) {
    var idx = shared.hiddenAgendas.indexOf(agenda.id);

    if (idx > -1) {
      shared.hiddenAgendas.splice(idx, 1);
    } else {
      shared.hiddenAgendas.push(agenda.id);
    }
  }

  function isHidden (agenda) {
    return shared.hiddenAgendas.indexOf(agenda.id) > -1;
  }

}

function CalendarEventDialogController ($mdDialog, event, moment) {
  var dialog = this;
  dialog.event = event;

  init();

  function init () {
    dialog.event.start =  moment(dialog.event.start);
    dialog.event.end =  moment(dialog.event.end);
    dialog.title = {};
    if (dialog.event.start.isSame(dialog.event.end, 'day')) {
      if (dialog.event.is_all_day) {
        dialog.title.token = 'SINGLE_DATE';
        dialog.title.values = {
          date: dialog.event.start.format('D MMMM YYYY')
        };
      } else {
        dialog.title.token = 'SINGLE_DATE_TIME';
        dialog.title.values = {
          date: dialog.event.start.format('D MMMM YYYY'),
          timeStart: dialog.event.start.format('HH:mm'),
          timeEnd: dialog.event.end.format('HH:mm'),
        };
      }
    } else {
      if (dialog.event.is_all_day) {
        dialog.title.token = 'MULTI_DATE';
        dialog.title.values = {
          dateStart: dialog.event.start.format('D MMMM YYYY'),
          dateEnd: dialog.event.end.format('D MMMM YYYY'),
        };
      } else {
        dialog.title.token = 'MULTI_DATE_TIME';
        dialog.title.values = {
          dateStart: dialog.event.start.format('D MMMM YYYY'),
          dateEnd: dialog.event.end.format('D MMMM YYYY'),
          timeStart: dialog.event.start.format('HH:mm'),
          timeEnd: dialog.event.end.format('HH:mm'),
        };
      }
    }
  }

  dialog.close = function () {
    $mdDialog.hide();
  };

}

function CalendarFrontWeekStateFactory () {

  return {};

}

})(angular);
