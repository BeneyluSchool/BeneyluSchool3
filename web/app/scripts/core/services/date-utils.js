(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.core.dateUtils
 */
angular.module('bns.core.dateUtils', [])

  .factory('dateUtils', DateUtilsFactory)

;

/**
 * @ngdoc service
 * @name dateUtils
 *
 * @description
 * Various utility functions on dates.
 */
function DateUtilsFactory (moment) {

  return {
    getCurrentMonday: getCurrentMonday,
    isMonday: isMonday,
  };

  function getCurrentMonday () {
    // get the iso weekday 1 (monday) of current utc time
    return moment.utc().isoWeekday(1).format('YYYY-MM-DD');
  }

  function isMonday (date) {
    if (!date) {
      return false;
    }

    // check if given date is iso weekday 1 (monday)
    return moment.utc(date).isoWeekday() === 1;
  }

}

})(angular);
