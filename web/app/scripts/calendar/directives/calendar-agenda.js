(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.calendar.agenda
 */
angular.module('bns.calendar.agenda', [
  'angularLocalStorage',
  'ui.calendar',
  'bns.core.global',
])

  .constant('BNS_CALENDAR_AGENDA_STORAGE_KEY', 'bns/calendar/date')
  .constant('BNS_CALENDAR_AGENDA_CONFIG', {
    defaultView: 'agendaWeek',
    minTime: '08:00:00',
    maxTime: '22:00:00',
    eventConstraint: {
      start: '08:00:00',
      end: '22:00:00',
    },
    timezone: 'local',
    header: false,
    contentHeight: 'auto', // disable inner scroll
  })
  .directive('bnsCalendarAgenda', BNSCalendarAgendaDirective)
  .controller('BNSCalendarAgenda', BNSCalendarAgendaController)

;

/**
 * @ngdoc directive
 * @name bnsCalendarAgenda
 * @module bns.calendar.agenda
 *
 * @description
 * Displays a weekly agenda. Uses ui-calendar under the hood, which itself uses
 * the fullCalendar and Moment js libraries.
 *
 * ** Attributes **
 *  - `calendarName` {String}: name of the calendar, used by uiCalendar to
 *                             expose the underlying fullCalendar object. Should
 *                             be unique in the app.
 *  - `source` {String}: URL from which the events are retrieved.
 *  - `startDate` {Moment}: Start date of the current calendar view.
 *  - `endDate` {Moment}: End date of the current calendar view.
 *  - `config` {Object}: A map of fullCalendar configuration options.
 *  - `listen` {*}: If this attribute is present, the agenda listens to
 *                  rootScope events modifying the current date. @see the
 *                  `bnsCalendar` directive.
 *  - `hiddenAgendas` {Array}: Collection of agenda IDs whose events should be
 *                             hidden
 */
function BNSCalendarAgendaDirective () {

  return {
    scope: {
      calendarName: '@',
      source: '@',
      startDate: '=',
      endDate: '=?',
      config: '=',
      listen: '@',
      hiddenAgendas: '=',
      app: '='
    },
    templateUrl: 'views/calendar/directives/bns-calendar-agenda.html',
    controller: 'BNSCalendarAgenda',
    controllerAs: 'agenda',
    bindToController: true,
  };

}

function BNSCalendarAgendaController ($scope, $rootScope, storage, $window, uiCalendarConfig, global, BNS_CALENDAR_AGENDA_STORAGE_KEY, BNS_CALENDAR_AGENDA_CONFIG) {

  var agenda = this;
  agenda.busy = false;
  agenda.events = [];   // required by ng-model on the ui-calendar
  agenda.prev = prev;
  agenda.next = next;

  $scope.app = agenda.app;

  init();

  function init () {
    setupStorage();
    var defaultDate = getDateIfNotExpired();

    agenda.calendarConfig = angular.extend({}, BNS_CALENDAR_AGENDA_CONFIG, {
      lang: global('locale') || 'fr',
      columnFormat:  'dddd D',
      defaultDate: defaultDate,
      events: {
        url: agenda.source,
      },
      loading: function (isLoading) {
        agenda.busy = !!isLoading;
      },
      viewRender: function (view) {
        agenda.startDate = view.start.startOf('day');
        agenda.endDate = view.end.subtract(1, 'day').endOf('day');
      },
      eventRender: function (event) {
        if (angular.isArray(agenda.hiddenAgendas) && agenda.hiddenAgendas.indexOf(event.agenda_id) > -1) {
          return false;
        }
      },
      eventDataTransform: function(event) {
        if ($window.jQuery && true === event.is_anniversary) {
          // fix aniversary timezone issue
          var start = $window.jQuery.fullCalendar.moment.parseZone(event.start);
          start.stripTime();
          event.start = start;

          var end = $window.jQuery.fullCalendar.moment.parseZone(event.end);
          end.stripTime();
          event.end = end;
        }

        return event;
      }

    }, agenda.config);

    // listen for outside events changing the date
    if (angular.isDefined(agenda.listen)) {
      var unwatch = $rootScope.$on('calendar.selection', function (event, date) {
        getCalendar().fullCalendar('gotoDate', date);
      });

      $scope.$on('$destroy', unwatch);

      // listen to a simple refresh request
      $scope.$on('$destroy', $rootScope.$on('calendar.refresh', function () {
        var calendar = getCalendar();
        if (calendar) {
          calendar.fullCalendar('refetchEvents');
        }
      }));
    }

    if (angular.isDefined(agenda.hiddenAgendas)) {
      var calendar;
      $scope.$watchCollection('agenda.hiddenAgendas', function () {
        calendar = getCalendar();
        if (calendar) {
          calendar.fullCalendar('rerenderEvents');
        }
      });
    }
  }

  function prev () {
    getCalendar().fullCalendar('prev');
  }

  function next () {
    getCalendar().fullCalendar('next');
  }

  /**
   * Gets the underlying fullCalendar object
   *
   * @returns {Object}
   */
  function getCalendar () {
    return uiCalendarConfig.calendars[agenda.calendarName];
  }

  /**
   * Sets up the storage mechanism for the calendar date: sync between
   * localstorage and scope, along with a timestampt to handle date expiration.
   */
  function setupStorage () {
    // store the current calendar date, along with a timestamp
    storage.bind($scope, 'agenda.storedDate', {
      defaultValue: { value: null, timestamp: Date.now() },
      storeName: BNS_CALENDAR_AGENDA_STORAGE_KEY,
    });

    // keep in sync the watched/stored construct and the actual date
    $scope.$watch('agenda.startDate', function (date) {
      if (!date) {
        return;
      }
      agenda.storedDate = {
        value: date,
        timestamp: Date.now(),
      };
    });
  }

  /**
   * Gets the stored calendar date if it has not expired, null otherwise.
   *
   * @returns {String} The string representation of the date
   */
  function getDateIfNotExpired () {
    var stored = storage.get(BNS_CALENDAR_AGENDA_STORAGE_KEY);

    // date was stored less than a day ago: still valid
    if (stored && stored.timestamp > Date.now() - 24 * 3600 * 1000) {
      return stored.value;
    }

    return null;
  }

}

})(angular);
