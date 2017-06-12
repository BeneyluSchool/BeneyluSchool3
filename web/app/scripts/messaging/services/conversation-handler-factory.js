(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.messaging.conversationHandler
 */
angular.module('bns.messaging.conversationHandler', [
  'bns.components.toast',
  'bns.messaging.constant',
  'bns.messaging.conversations',
])

  .factory('ConversationHandler', ConversationHandlerFactory)

;

/**
 * @ngdoc service
 * @name ConversationHandler
 * @module bns.messaging.conversationHandler
 *
 * @description
 * Handles conversation actions for a single MessagingConversation object.
 *
 * @requires toast
 * @requires messagingConstant
 * @requires MessagingConversations
 */
function ConversationHandlerFactory (toast, messagingConstant, MessagingConversations) {

  var ConversationHandler = function (id) {
    this.id = id;
    this.conversation = null;
    this.busy = false;
  };

  ConversationHandler.prototype.load = function () {
    this.busy = true;

    return MessagingConversations.one(this.id).get()
      .then(angular.bind(this, function success (conversation) {
        this.conversation = conversation;

        return this.conversation;
      }))
      .catch(function error (response) {
        toast.error('MESSAGING.FLASH_GET_CONVERSATION_ERROR');
        throw response;
      })
      .finally(angular.bind(this, function end () {
        this.busy = false;
      }))
    ;
  };

  ConversationHandler.prototype.canAnswer = function () {
    return this.conversation && (
      this.conversation.status === messagingConstant.CONVERSATION.STATUS.NONE_READ ||
      this.conversation.status === messagingConstant.CONVERSATION.STATUS.READ
    );
  };

  ConversationHandler.prototype.answer = function(data) {
    if (this.busy || !this.conversation) {
      return console.warn('Trying to send answer without being ready');
    }

    this.busy = true;

    return this.conversation.post('', data)
      .then(angular.bind(this, function success (message) {
        switch (message.status) {
          case messagingConstant.MESSAGE.STATUS.IN_MODERATION:
            toast.success('MESSAGING.FLASH_MESSAGE_SENT_IN_MODERATION');
            break;
          case messagingConstant.MESSAGE.STATUS.ACCEPTED:
            toast.success('MESSAGING.FLASH_MESSAGE_SENT');
            break;
          default:
            return;
        }

        this.conversation._embedded.children.push(message);

        return message;
      }))
      .catch(function error (response) {
        toast.error('MESSAGING.FLASH_MESSAGE_SEND_ERROR');
        throw response;
      })
      .finally(angular.bind(this, function end () {
        this.busy = false;
      }))
    ;
  };

  ConversationHandler.prototype.canRemove = function () {
    return this.conversation && (
      this.conversation.status === messagingConstant.CONVERSATION.STATUS.NONE_READ ||
      this.conversation.status === messagingConstant.CONVERSATION.STATUS.READ
    );
  };

  ConversationHandler.prototype.remove = function () {
    this.busy = true;

    return this.conversation.remove()
      .then(function success () {
        toast.success('MESSAGING.FLASH_CONVERSATION_DELETE_SUCCESS');
      })
      .catch(function error (response) {
        toast.error('MESSAGING.FLASH_CONVERSATION_DELETE_ERROR');
        throw response;
      })
      .finally(angular.bind(this, function end () {
        this.busy = false;
      }))
    ;
  };

  ConversationHandler.prototype.canRestore = function () {
    return this.conversation &&
      this.conversation.status === messagingConstant.CONVERSATION.STATUS.DELETED;
  };

  ConversationHandler.prototype.restore = function () {
    return this.patch({
      status: messagingConstant.CONVERSATION.STATUS.READ,
    }, {
      success: 'MESSAGING.FLASH_CONVERSATION_RESTORE_SUCCESS',
      error: 'MESSAGING.FLASH_CONVERSATION_RESTORE_ERROR',
    });
  };

  ConversationHandler.prototype.canUnread = function () {
    return this.conversation &&
      this.conversation.status === messagingConstant.CONVERSATION.STATUS.READ;
  };

  ConversationHandler.prototype.unread = function () {
    return this.patch({
      status: messagingConstant.CONVERSATION.STATUS.NONE_READ,
    }, {
      success: 'MESSAGING.FLASH_CONVERSATION_UNREAD_SUCCESS',
      error: 'MESSAGING.FLASH_CONVERSATION_UNREAD_ERROR',
    });
  };

  ConversationHandler.prototype.patch = function (data, conf) {
    conf = angular.merge({}, conf);
    this.busy = true;

    return this.conversation.patch(data)
      .then(function success (response) {
        if (conf.success) {
          toast.success(conf.success);
        }
        return response;
      })
      .catch(function error (response) {
        if (conf.error) {
          toast.error(conf.error);
        }
        throw response;
      })
      .finally(angular.bind(this, function end () {
        this.busy = false;
      }))
    ;
  };

  return ConversationHandler;

}

})(angular);
