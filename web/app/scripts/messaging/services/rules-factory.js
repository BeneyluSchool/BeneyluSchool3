(function (angular) {
'use strict';

angular.module('bns.messaging.rules', [
  'restangular',
])

  .factory('MessagingRules', MessagingRulesFactory)

;

function MessagingRulesFactory (Restangular) {

  return Restangular.service('rules', Restangular.one('messaging', ''));

}

})(angular);
