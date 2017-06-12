(function (angular) {
'use strict'  ;

/**
 * @ngdoc module
 * @name bns.components.calendar
 *
 * @description
 * The bns calendar component
 */
angular.module('bns.components.calendar', [
  'angularMoment',
])

  .service('Calendar', CalendarService)
  .directive('bnsCalendar', BNSCalendarDirective)
  .controller('BNSCalendarController', BNSCalendarController)

;

/**
 * @ngdoc service
 * @name Calendar
 * @module bns.components.calendar
 *
 * @description
 * A monthly calendar navigator, used by the bnsCalendar directive to display
 * dates
 *
 * @requires moment
 */
function CalendarService (moment) {

  function Calendar (start, display) {
    if (!start) {
      start = undefined; // normalize default constructor
    }

    moment.locale('fr');
    this.display = display;
    this.start = moment.utc(start);
    this.start.startOf(display);
    this.dates = [];
    this.refresh();
  }

  Calendar.prototype.prev = function () {
    this.start.subtract(1, this.display + 's');
    this.refresh();
  };

  Calendar.prototype.next = function () {
    this.start.add(1, this.display + 's');
    this.refresh();
  };

  /**
   * Targets the given date, ie goes prev/next until the target is in view.
   *
   * @param  {Moment} target
   */
  Calendar.prototype.target = function (target) {
    if (!moment.isMoment(target)) {
      return console.warn('Invalid target', target);
    }

    var change = false;

    var steps = ['year', 'month'];
    if ('week' === this.display) {
      steps.push('week');
    }

    // compare dates with more and more granularity at each step: first years,
    // then month, etc. Adjust the calendar date if necessary
    angular.forEach(steps, function (step) {
      var targetValue = target[step]();
      while (this.start[step]() < targetValue) {
        this.start.add(1, this.display);
        change = true;
      }
      while (this.start[step]() > targetValue) {
        this.start.subtract(1, this.display);
        change = true;
      }
    }, this);

    if (change) {
      this.refresh();
    }
  };

  Calendar.prototype.refresh = function () {
    this.dates = [];
    this.weeks = [];

    var current = this.start.clone().startOf('week');
    var weeksDone = {};
    var weekNb = current.week();
    var startMonth = this.start.month();
    var startYear = this.start.year();
    var weekIdx = -1;

    /*
      The 3 conditions:
      - Walking through the current year, and current month (or previous month,
        for the first week of month that spans). Used for every week except
        first and last of year.
      - Walking through next year, until reaching a new week (weekday 0 <=>
        first day of the week, locale). Used for last week of december.
      - Walking throug previous year. Used for first week of january.
     */
    while ((current.year() === startYear && current.month() <= startMonth) ||
      (current.year() > startYear && current.weekday()) ||
      (current.year() < startYear)) {
      weekNb = current.week();
      if (!weeksDone[weekNb]) {
        this.weeks.push({ number: weekNb, days: [] });
        weeksDone[weekNb] = true;
        weekIdx++;
      }

      this.weeks[weekIdx].days.push(current.clone());
      current.add(1, 'days');
    }

    // fill the remaining days of the last week
    if (this.weeks[weekIdx]) {
      while (this.weeks[weekIdx].days.length < 7) {
        this.weeks[weekIdx].days.push(current.clone());
        current.add(1, 'days');
      }
    }
  };

  return Calendar;

}

function BNSCalendarDirective () {

  return {
    restrict: 'E',
    templateUrl: function (tElement, tAttrs) {
      if (tAttrs.view) {
        if (tAttrs.view === 'week') {
          return 'views/components/calendar/bns-calendar-week.html';
        }
      } else {
        return 'views/components/calendar/bns-calendar.html';
      }
    },
    scope: {
      start: '@',
      selection: '@',
      mode: '@',
      view: '@',
      syncView: '@',
      dayText: '=',
      preferences: '='
    },
    controller: 'BNSCalendarController',
    controllerAs: 'calendar',
    bindToController: true,
  };

}

function BNSCalendarController ($scope, $attrs, $timeout, moment, Calendar) {

  var calendar = this;
  if (!calendar.mode) {
    calendar.mode = 'day';
  }

  if(!calendar.view) {
    calendar.view = 'month';
  }

  var display = calendar.view;

  if (['day', 'week'].indexOf(calendar.mode) === -1) {
    return console.error('Invalid calendar mode');
  }

  calendar.prev = prev;
  calendar.next = next;
  calendar.handleClick = handleClick;

  init();

  function init () {
    calendar.cal = new Calendar(calendar.start || calendar.selection, display);
    calendar.today = moment.utc().startOf('day');

    $scope.$watch('calendar.selection', function () {
      if (calendar.selection && !(moment.isMoment(calendar.selection))) {
        $timeout(function () {
          calendar.selection = moment.utc(calendar.selection).startOf(calendar.mode);

          if (angular.isDefined(calendar.syncView)) {
            calendar.cal.target(calendar.selection);
          }
        });
      }
    });
  }

  function prev () {
    calendar.cal.prev();
  }

  function next () {
    calendar.cal.next();
  }

  function handleClick (event) {
    var chosen;
    if (event.target && event.target.getAttribute('data-date')) {
      chosen = moment.utc(event.target.getAttribute('data-date'));
    } else if (event.currentTarget && event.currentTarget.getAttribute('data-date')) {
    // handle weird delegate in md-button
      chosen = moment.utc(event.currentTarget.getAttribute('data-date'));
    }

    if (chosen && 'week' === calendar.mode) {
      chosen.startOf('week').day(1); // move to start of week then monday
    }

    if (chosen) {
      calendar.selection = chosen;
      $scope.$emit('calendar.selection', chosen);
    }
  }

}

})(angular);
