(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.messaging.front.messageControllers
 *
 * @description
 * Contains controllers and shared state for the message view
 */
angular.module('bns.messaging.front.messageControllers', [
  'bns.messaging.messages',
])

  .controller('MessagingFrontMessageContent', MessagingFrontMessageContentController)

;

function MessagingFrontMessageContentController ($stateParams, toast, MessagingMessages) {

  var ctrl = this;
  ctrl.message = null;

  init();

  function init () {
    ctrl.busy = true;

    return MessagingMessages.one($stateParams.id).get()
      .then(function success (message) {
        ctrl.message = message;
      })
      .catch(function error (response) {
        toast.error('MESSAGING.FLASH_GET_MESSAGE_ERROR');
        console.error(response);
      })
      .finally(function end () {
        ctrl.busy = false;
      })
    ;
  }

}

})(angular);
