(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.messaging.messageHandler
 */
angular.module('bns.messaging.messageHandler', [
  'bns.components.toast',
  'bns.messaging.constant',
  'bns.messaging.messages',
])

  .factory('MessageHandler', MessageHandlerFactory)

;

/**
 * @ngdoc service
 * @name MessageHandler
 * @module bns.messaging.messageHandler
 *
 * @description
 * Handles message actions for a single MessagingMessage object.
 *
 * @requires $q
 * @requires toast
 * @requires MessagingMessages
 */
function MessageHandlerFactory ($q, toast, messagingConstant, MessagingMessages, Restangular) {

  var MessageHandler = function (id) {
    this.id = id;
    this.message = null;
    this.busy = false;
    this.locked = false;
  };

  MessageHandler.prototype.load = function () {
    if (!this.id) {
      return $q.when(this.message = {});
    }

    this.busy = true;
    this.locked = true;

    return MessagingMessages.one('draft').one(this.id).get()
      .then(angular.bind(this, function success (message) {
        return (this.message = message);
      }))
      .catch(function error (response) {
        toast.error('MESSAGING.FLASH_GET_DRAFT_ERROR');
        throw response;
      })
      .finally(angular.bind(this, function end () {
        this.busy = false;
        this.locked = false;
      }))
    ;
  };

  MessageHandler.prototype.canDraft = function () {
    return true;
  };

  MessageHandler.prototype.draft = function (data) {
    if (this.busy) {
      return console.warn('Trying to send draft without being ready');
    }

    this.busy = true;
    data = angular.merge({ draftId: this.id }, data);

    return MessagingMessages.one('draft').post('', data)
      .then(angular.bind(this, function success (draft) {
        toast.success('MESSAGING.DRAFT_MESSAGE_SUCCESS');
        this.id = draft.id;
        return (this.message = draft);
      }))
      .catch(function error (response) {
        toast.error('MESSAGING.DRAFT_MESSAGE_ERROR');
        throw response;
      })
      .finally(angular.bind(this, function end () {
        this.busy = false;
      }))
    ;
  };

  MessageHandler.prototype.canSave = function () {
    return true;
  };

  MessageHandler.prototype.save = function (data) {
    if (this.busy) {
      return console.warn('Trying to save message without being ready');
    }

    this.busy = true;
    this.locked = true;
    data = angular.merge({ draftId: this.id }, data);

    return MessagingMessages.post(data)
      .then(angular.bind(this, function success (message) {
        if ('IN_MODERATION' === message.status) {
          toast.simple({
            intent: 'warning',
            content: 'MESSAGING.FLASH_MESSAGE_SENT_IN_MODERATION'
          });
        } else {
          toast.success('MESSAGING.FLASH_MESSAGE_SENT');
        }
      }))
      .catch(function error (response) {
        toast.error('MESSAGING.FLASH_MESSAGE_SEND_ERROR');
        throw response;
      })
      .finally(angular.bind(this, function end () {
        this.busy = false;
        this.locked = false;
      }))
    ;
  };

  MessageHandler.prototype.canRemoveDraft = function () {
    return this.message &&
      this.message.status === messagingConstant.MESSAGE.STATUS.DRAFT
    ;
  };

  MessageHandler.prototype.removeDraft = function () {
    if (this.busy) {
      return console.warn('Trying to delete draft without being ready');
    }

    this.busy = true;
    this.locked = true;

    return Restangular.one('messaging').one('messages').one('draft', this.id).remove()
      .then(function success () {
        toast.success('MESSAGING.FLASH_DELETE_DRAFT_SUCCESS');
      })
      .catch(function error (response) {
        toast.error('MESSAGING.FLASH_DELETE_DRAFT_ERROR');
        throw response;
      })
      .finally(angular.bind(this, function end () {
        this.busy = false;
        this.locked = false;
      }))
    ;
  };

  return MessageHandler;

}

})(angular);
