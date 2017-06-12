(function (angular) {
'use strict';

angular.module('bns.calendar.backWeekControllers', [
  'bns.calendar.calendars',
])

  .controller('CalendarBackWeekSidebar', CalendarBackWeekSidebarController)
  .controller('CalendarBackWeekContent', CalendarBackWeekContentController)
  .service('calendarBackWeekState', CalendarBackWeekStateFactory)

;

function CalendarBackWeekSidebarController (_, $scope, agendas, calendarBackWeekState) {

  var shared = $scope.shared = calendarBackWeekState;
  shared.hiddenAgendas = [];

  var ctrl = this;
  ctrl.reload = reload;

  init();

  function init () {
    // create a bns-checkbox-group compatible collection
    ctrl.agendas = _.map(agendas, function (agenda) {
      return {
        value: agenda.id,
        label: agenda.title,
        agenda: agenda,
      };
    });

    // set all agendas as visible by default
    ctrl.visibleAgendas = _.map(ctrl.agendas, 'value');

    // "negate" the visible collection, to create collection of hidden agendas
    $scope.$watchCollection('ctrl.visibleAgendas', function () {
      shared.hiddenAgendas = _.map(_.filter(agendas, function (agenda) {
        return ctrl.visibleAgendas.indexOf(agenda.id) === -1;
      }), 'id');
    });
  }

  function reload () {
    $scope.$emit('calendar.refresh');
  }

}

function CalendarBackWeekContentController ($scope, $state, $stateParams, toast, Calendars, calendarBackWeekState) {

  $scope.shared = calendarBackWeekState;

  var ctrl = this;
  ctrl.eventsSource = Calendars.one('events').getRestangularUrl() + '?editing=1';
  ctrl.config = {
    selectable: true,
    selectHelper: true,
    editable: true,
    eventClick: onEventClick,
    select: onSelect,
    eventDrop: onDrop,
    eventResize: onResize
  };

  init();

  function init () {
    if ($stateParams.defaultDate) {
      ctrl.config.defaultDate = $stateParams.defaultDate;
    }
  }

  function onEventClick (model) {
    if (!model.id) {
      return;
    }

    $state.go('^.edit', {id: model.id});
  }

  function onSelect (start, end) {
    var isAllDay = !start.hasTime();
    if (isAllDay) {
      end.subtract(1, 'day'); // end day is exclusive in fullCalendar
    }

    $state.go('^.create', { start: start.format(), end: end.format(), 'all_day': isAllDay });
  }

  function onDrop (event) {
    submit(event);
  }

  function onResize (event) {
    submit(event);
  }

  function submit (event) {
    var promise;
    var data = {
      agendaId: event.agenda_id,
      start: event.start.format(),
      end: event.end.format()
    };
    promise = Calendars.one('events', event.id).patch(data);

    return promise
      .then(function success (response) {
        toast.success('CALENDAR.FLASH_EVENT_SAVE_SUCCESS');

        return response;
      })
      .catch(function error (response) {
        console.error('[POST] events', response);
        toast.error('CALENDAR.FLASH_EVENT_SAVE_ERROR');

        throw 'CALENDAR.EVENT_SAVE_ERROR';
      })
    ;
  }

}

function CalendarBackWeekStateFactory () {

  return {};

}

})(angular);
