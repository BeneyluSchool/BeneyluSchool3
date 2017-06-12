(function (angular) {
'use strict';

angular.module('bns.messaging.front.conversationsControllers', [
  'bns.components.toast',
  'bns.messaging.conversations',
  'bns.messaging.selectionHandler',
])

  .controller('MessagingFrontConversationsActionbar', MessagingFrontConversationsActionbarController)
  .controller('MessagingFrontConversationsContent', MessagingFrontConversationsContentController)
  .factory('messagingConversationsState', MessagingConversationsStateFactory)

;

function MessagingFrontConversationsActionbarController (_, $rootScope, messagingConstant, MessagingConversations, MessagingSelectionHandler, messagingConversationsState) {

  var ctrl = this;
  ctrl.shared = messagingConversationsState;
  ctrl.removeSelection = removeSelection;
  ctrl.restoreSelection = restoreSelection;
  ctrl.hasRead = hasRead;
  ctrl.readSelection = readSelection;
  ctrl.hasUnread = hasUnread;
  ctrl.unreadSelection = unreadSelection;

  ctrl.shared.handler = new MessagingSelectionHandler(
    MessagingConversations.one('selection', ''),
    ctrl.shared.selection
  );

  function removeSelection () {
    return ctrl.shared.handler.remove({
      unselect: true,
      success: 'MESSAGING.FLASH_DELETE_CONVERSATIONS_SUCCESS',
      error: 'MESSAGING.FLASH_DELETE_CONVERSATIONS_ERROR'
    })
      .then(function success () {
        $rootScope.$emit('messaging.box.refresh');
        $rootScope.$emit('messaging.counters.refresh');
      })
    ;
  }

  function restoreSelection () {
    return ctrl.shared.handler.restore({
      unselect: true,
      success: 'MESSAGING.FLASH_RESTORE_CONVERSATIONS_SUCCESS',
      error: 'MESSAGING.FLASH_RESTORE_CONVERSATIONS_ERROR'
    })
    .then(function success () {
        $rootScope.$emit('messaging.box.refresh');
        $rootScope.$emit('messaging.counters.refresh');
      })
    ;
  }

  function readSelection () {
    return ctrl.shared.handler.read({
      error: 'MESSAGING.FLASH_READ_CONVERSATIONS_ERROR',
    })
      .then(function success (data) {
        angular.forEach(data.items, function (conversation) {
          conversation.status = messagingConstant.CONVERSATION.STATUS.READ;
        });
        $rootScope.$emit('messaging.counters.refresh');
      })
    ;
  }

  function unreadSelection () {
    return ctrl.shared.handler.unread({
      error: 'MESSAGING.FLASH_UNREAD_CONVERSATIONS_ERROR',
    })
      .then(function success (data) {
        angular.forEach(data.items, function (conversation) {
          conversation.status = messagingConstant.CONVERSATION.STATUS.NONE_READ;
        });
        $rootScope.$emit('messaging.counters.refresh');
      })
    ;
  }

  function hasRead () {
    return _.some(ctrl.shared.selection, { status: messagingConstant.CONVERSATION.STATUS.READ });
  }

  function hasUnread () {
    return _.some(ctrl.shared.selection, { status: messagingConstant.CONVERSATION.STATUS.NONE_READ });
  }

}

function MessagingFrontConversationsContentController ($rootScope, $scope, $mdUtil, messagingConversationsState) {

  var ctrl = this;
  ctrl.shared = messagingConversationsState;
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

function MessagingConversationsStateFactory () {

  return {
    handler: null,
    selection: [],
  };

}

})(angular);
