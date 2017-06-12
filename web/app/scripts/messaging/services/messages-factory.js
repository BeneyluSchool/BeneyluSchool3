(function (angular) {
'use strict';

angular.module('bns.messaging.messages', [
  'restangular',
])

  .factory('MessagingMessages', MessagingMessagesFactory)

;

function MessagingMessagesFactory (Restangular) {

  return Restangular.service('messages', Restangular.one('messaging', ''));

}

})(angular);
