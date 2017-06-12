(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.messaging.back.messageControllers
 *
 * @description
 * Contains controllers and shared state for the message view
 */
angular.module('bns.messaging.back.messageControllers', [
  'bns.messaging.messages',
  'bns.messaging.messageType',
])

  .controller('MessagingBackMessageContent', MessagingBackMessageContentController)

;

function MessagingBackMessageContentController ($scope, $stateParams, toast, MessagingMessages, MessageType) {

  var ctrl = this;
  ctrl.message = null;
  ctrl.type = new MessageType();
  ctrl.submit = submit;

  init();

  function init () {
    ctrl.busy = true;

    return MessagingMessages.one($stateParams.id).get()
      .then(function success (message) {
        ctrl.message = message;
        ctrl.type.form.subject.value = message.subject;

        // wait for full form initialization
        var unwatch = $scope.$watch('ctrl.type.form.content', function (content) {
          if (!content) {
            return;
          }
          unwatch();

          ctrl.type.form.content.value = message.content;
          // actual textarea content is not initialized properly by ui-tinymce
          angular.element('textarea[name="content"]').val(message.content);
        });
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

  function submit () {
    var data = {
      subject: ctrl.type.form.subject.value,
      content: ctrl.type.form.content.value,
    };
    ctrl.busy = true;

    MessagingMessages.one($stateParams.id).patch(data)
      .then(function success () {
        toast.success('MESSAGING.FLASH_SAVE_MESSAGE_SUCCESS');
      })
      .finally(function end () {
        ctrl.busy = false;
      })
    ;
  }

}

})(angular);
