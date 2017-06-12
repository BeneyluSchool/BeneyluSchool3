(function (angular) {
'use strict';

angular.module('bns.messaging.front.messagesControllers', [
  'bns.components.toast',
  'bns.messaging.messages',
  'bns.messaging.selectionHandler',
])

  .controller('MessagingFrontMessagesActionbar', MessagingFrontMessagesActionbarController)
  .controller('MessagingFrontMessagesContent', MessagingFrontMessagesContentController)
  .factory('messagingMessagesState', MessagingMessagesStateFactory)

;

function MessagingFrontMessagesActionbarController ($rootScope, MessagingMessages, MessagingSelectionHandler, messagingMessagesState) {

  var ctrl = this;
  ctrl.shared = messagingMessagesState;
  ctrl.removeSelection = removeSelection;

  ctrl.shared.handler = new MessagingSelectionHandler(
    MessagingMessages.one('selection', ''),
    ctrl.shared.selection
  );

  function removeSelection () {
    return ctrl.shared.handler.remove({
      unselect: true,
      success: 'MESSAGING.FLASH_DELETE_MESSAGES_SUCCESS',
      error: 'MESSAGING.FLASH_DELETE_MESSAGES_ERROR'
    })
      .then(function success () {
        $rootScope.$emit('messaging.box.refresh');
        $rootScope.$emit('messaging.counters.refresh');
      })
    ;
  }

}

function MessagingFrontMessagesContentController ($scope, $rootScope, $mdUtil, messagingMessagesState) {

  var ctrl = this;
  ctrl.shared = messagingMessagesState;
  ctrl.search = '';

  // publish a reference to the messaging box, will be set up by the directive
  ctrl.box = null;

  init();

  function init () {
    // ask for a refresh of the message counters
    $rootScope.$emit('messaging.counters.refresh');

    // search box after 500ms
    $scope.$watch('ctrl.search', $mdUtil.debounce(function doSearch (newSearch, oldSearch) {
      if (newSearch === oldSearch || !ctrl.box) {
        return;
      }

      if (ctrl.search) {
        ctrl.box.params.search = ctrl.search;
      } else {
        delete ctrl.box.params.search;
      }
      ctrl.box.init();
    }, 500));
  }

}

function MessagingMessagesStateFactory () {

  return {
    selection: [],
    handler: null,
  };

}

})(angular);
