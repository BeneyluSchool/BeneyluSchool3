(function (angular) {
'use strict'  ;

angular.module('bns.lunch', [
  'bns.main.weekToolbar',

  'bns.lunch.config.states',
  'bns.lunch.lunchWeek',
  'bns.lunch.lunchWeekEditor',
]);

})(angular);
