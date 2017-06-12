(function (angular) {
'use strict';

angular.module('bns.messaging.counters', [
  'restangular',
])

  .factory('MessagingCounters', MessagingCountersFactory)

;

function MessagingCountersFactory (Restangular) {

  return Restangular.service('counters', Restangular.one('messaging', ''));

}

})(angular);
