(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.messaging.message
 */
angular.module('bns.messaging.message', [])

  .directive('bnsMessagingMessage', BNSMessagingMessageDirective)

;

/**
 * @ngdoc directive
 * @name bnsMessagingMessage
 * @module bns.messaging.message
 *
 * @description
 * Displays a messaging message
 *
 * ** Attributes **
 *  - `message` {Object} the message to be displayed
 */
function BNSMessagingMessageDirective () {

  return {
    templateUrl: 'views/messaging/directives/bns-messaging-message.html',
    scope: {
      message: '=',
    },
  };

}

})(angular);
