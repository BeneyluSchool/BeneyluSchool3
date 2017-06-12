(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.messaging.front.conversationControllers
 *
 * @description
 * Contains controllers and shared state for the conversation view
 */
angular.module('bns.messaging.front.conversationControllers', [
  'bns.messaging.constant',
  'bns.messaging.conversationHandler',
])

  .controller('MessagingFrontConversationActionbar', MessagingFrontConversationActionbarController)
  .controller('MessagingFrontConversationContent', MessagingFrontConversationContentController)
  .factory('messagingConversationState', MessagingConversationStateFactory)

;

function MessagingFrontConversationActionbarController ($rootScope, $state, messagingConversationState) {

  var ctrl = this;
  ctrl.shared = messagingConversationState;
  ctrl.removeConversation = removeConversation;
  ctrl.restoreConversation = restoreConversation;
  ctrl.unreadConversation = unreadConversation;

  function removeConversation () {
    if (!ctrl.shared.handler) {
      return;
    }

    return ctrl.shared.handler.remove()
      .then(function success () {
        $state.go('app.messaging.front.inbox');
      })
    ;
  }

  function restoreConversation () {
    if (!ctrl.shared.handler) {
      return;
    }

    return ctrl.shared.handler.restore()
      .then(function success () {
        $state.go('app.messaging.front.inbox');
      })
    ;
  }

  function unreadConversation () {
    if (!ctrl.shared.handler) {
      return;
    }

    return ctrl.shared.handler.unread()
      .then(function success () {
        $rootScope.$emit('messaging.counters.refresh');
        $state.go('app.messaging.front.inbox');
      })
    ;
  }

}

function MessagingFrontConversationContentController (_, $rootScope, $scope, $stateParams, ConversationHandler, messagingConversationState) {

  var ctrl = this;
  ctrl.shared = messagingConversationState;
  ctrl.postAnswer = postAnswer;
  ctrl.hasExpander = hasExpander;
  ctrl.showExpander = showExpander;
  ctrl.countHiddenMessages = countHiddenMessages;
  ctrl.expand = expand;
  ctrl.expanded = false;

  init();

  function init () {
    ctrl.shared.handler = new ConversationHandler($stateParams.id);
    ctrl.shared.handler.load()
      .then(function success () {
        $rootScope.$emit('messaging.counters.refresh');
      })
    ;

    $scope.$on('$destroy', cleanup);

    function cleanup () {
      delete ctrl.shared.handler;
    }
  }

  function postAnswer () {
    if (!ctrl.shared.handler) {
      return;
    }

    var data = {
      answer: ctrl.shared.form.answer,
      'resource-joined': _.map(ctrl.shared.attachments, 'id'),
    };

    return ctrl.shared.handler.answer(data)
      .then(function success () {
        // clear form
        delete ctrl.shared.form.answer;
        ctrl.shared.attachments.splice(0, ctrl.shared.attachments.length);
        ctrl.shared.form.$setPristine();
      })
    ;
  }

  function expand () {
    ctrl.expanded = true;
  }

  /**
   * Checks if conversation has an expander: ie at least 3 child messages
   *
   * @return {Boolean}
   */
  function hasExpander () {
    return ctrl.shared.handler.conversation &&
      ctrl.shared.handler.conversation._embedded &&
      ctrl.shared.handler.conversation._embedded.children &&
      ctrl.shared.handler.conversation._embedded.children.length &&
      ctrl.shared.handler.conversation._embedded.children.length > 2;
  }

  /**
   * Tells whether to display an expander at the given index.
   * The expander is displayed before the last 2 messages
   *
   * @param {Integer} $index
   * @return {Boolean}
   */
  function showExpander ($index) {
    return $index === countHiddenMessages() - 1;
  }

  /**
   * Counts the number of hidden messages. The last 2 are already visible.
   *
   * @return {Integer}
   */
  function countHiddenMessages () {
    if (hasExpander()) {
      return ctrl.shared.handler.conversation._embedded.children.length - 2;
    }

    return -1;
  }

}

function MessagingConversationStateFactory () {

  return {
    form: {},
    handler: null,
    attachments: [],
  };

}

})(angular);
