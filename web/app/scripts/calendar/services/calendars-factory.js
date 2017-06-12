(function (angular) {
'use strict';

angular.module('bns.calendar.calendars', [
  'restangular',
])

  .factory('Calendars', CalendarsFactory)

;

function CalendarsFactory (Restangular) {

  return Restangular.one('calendar', '');

}

})(angular);
