(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.messaging.selectionHandler
 */
angular.module('bns.messaging.selectionHandler', [
  'bns.components.toast',
])

  .factory('MessagingSelectionHandler', MessagingSelectionHandlerFactory)

;

/**
 * @ngdoc service
 * @name MessagingSelectionHandler
 * @module bns.messaging.selectionHandler
 *
 * @description
 * Handles selection actions for a collection of MessagingConversation or
 * MessagingMessage.
 *
 * @requires _
 * @requires $translate
 * @requires toast
 */
function MessagingSelectionHandlerFactory (_, $translate, toast) {

  var MessagingSelectionHandler = function (store, source) {
    this.store = store;
    this.source = source;
    this.busy = false;
  };

  MessagingSelectionHandler.prototype.canRemove = function () {
    return this.source && this.source.length;
  };

  MessagingSelectionHandler.prototype.remove = function (conf) {
    return this.action('trash', conf);
  };

  MessagingSelectionHandler.prototype.canRestore = function () {
    return this.source && this.source.length;
  };

  MessagingSelectionHandler.prototype.restore = function (conf) {
    return this.action('restore', conf);
  };

  MessagingSelectionHandler.prototype.read = function (conf) {
    return this.action('read', conf);
  };

  MessagingSelectionHandler.prototype.unread = function (conf) {
    return this.action('unread', conf);
  };

  /**
   * Actual handler, does all the work
   *
   * @param {String} action The action to execute on the selection
   * @param {Object} conf   A map of options. Possible properties are:
   *        - success: The message to display upon success. Can have 'COUNT' as
   *                   a placeholder for the number of handled items. Defaults
   *                   to no message.
   *        - error: The message to display upon error. Defaults to no message.
   *        - unselect: Whether to unselect items after having handled them.
   *                    Defaults to false.
   * @return {[type]}        [description]
   */
  MessagingSelectionHandler.prototype.action = function (action, conf) {
    if (!action) {
      return console.warn('An action is required');
    }
    if (this.busy) {
      return console.warn('Trying to '+action+' selection without being ready');
    }

    if (!(this.store && this.source && this.source.length)) {
      return;
    }

    this.busy = true;
    conf = angular.merge({
      unselect: false,
    }, conf);

    var ids = _.map(this.source, 'id');

    return this.store.patch({
      action: action,
      ids: ids
    })
      .then(angular.bind(this, function success (response) {
        // inject count of handled items in success message
        if (conf.success) {
          toast.success($translate.instant(conf.success, { COUNT: response.length }, 'messageformat'));
        }

        // search in the selection the items corresponding to the handled ids
        var items = [];
        for (var i = 0; i < response.length; i++) {
          var id = response[i];
          for (var j = 0; j < this.source.length; j++) {
            var item = this.source[j];
            if (item.id === id) {
              // unselect it
              if (conf.unselect) {
                this.source.splice(j, 1);
              }
              // collect it
              items.push(item);
              break;
            }
          }
        }

        return {
          response: response,
          items: items,
        };
      }))
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

  return MessagingSelectionHandler;

}

})(angular);
