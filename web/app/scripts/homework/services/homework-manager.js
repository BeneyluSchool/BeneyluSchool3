(function (angular) {
'use strict';

angular.module('bns.homework.manager', [
  'bns.homework.homeworks',
])

  .factory('homeworkManager', HomeworkManagerFactory)

;

function HomeworkManagerFactory (moment) {

  return {
    getNextCreationDate: getNextCreationDate,
    getNextVisibleDay: getNextVisibleDay,
  };

  /**
   * Gets the next valid creation date, according to current preferences.
   *
   * @param {Object} date The date to get the next one from
   * @returns {Moment} a date, as moment object
   */
  function getNextCreationDate (date, preferences) {
    // make sure we have a correct object
    if (!(date && moment.isMoment(date))) {
      date = moment(date);
    }

    date = date.clone().locale('en');  // force  locale, for day comparisons
    var today = moment().locale('en');

    if (date.isSame(today, 'week')) {
      date = today.clone().add(1, 'day'); // if current week, set date to tomorrow
    } else {
      date.isoWeekday(1); // set date to first day of week
    }

    return _getValidOrNext(date, preferences);

    //if (!(preferences && preferences.days && preferences.days.length)) {
    //  return console.warn('Cannot guess creation date', preferences);
    //}
    //
    //// while date is not a valid day, add 1 day
    //var i = 0;
    //while (i < 7 && preferences.days.indexOf(date.format('dd').toUpperCase()) === -1) {
    //  date.add(1, 'day');
    //  i++;
    //}
    //
    //date.locale(false); // revert local to previous value
    //
    //return date;
  }

  function getNextVisibleDay (preferences) {
    var date = moment();
    var endOfDay = date.clone().hours(16).minutes(0).seconds(0);

    if (date.isAfter(endOfDay)) {
      date.add(1, 'days');
    }

    return _getValidOrNext(date, preferences);
  }

  function _getValidOrNext (date, preferences) {
    if (!(preferences && preferences.days && preferences.days.length)) {
      console.error('Cannot guess visible date', preferences);
      throw 'No preferences given';
    }

    date.locale('en');

    // while date is not a valid day, add 1 day
    var i = 0;
    while (i < 7 && preferences.days.indexOf(date.format('dd').toUpperCase()) === -1) {
      date.add(1, 'day');
      i++;
    }

    date.locale(false); // revert local to previous value

    return date;
  }

}

})(angular);
