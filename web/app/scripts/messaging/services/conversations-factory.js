(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.messaging.conversation
 */
angular.module('bns.messaging.conversations', [
  'restangular',
])

  .factory('MessagingConversations', MessagingConversationsFactory)

;

/**
 * @ngdoc service
 * @name MessagingConversations
 * @module bns.messaging.conversations
 *
 * @description
 * Restangular wrapper for messaging conversations.
 *
 * @requires Restangular
 */
function MessagingConversationsFactory (Restangular) {

  return Restangular.service('conversations', Restangular.one('messaging', ''));

}

})(angular);
