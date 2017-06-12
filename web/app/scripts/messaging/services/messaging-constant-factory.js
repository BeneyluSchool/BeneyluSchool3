(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.messaging.constant
 */
angular.module('bns.messaging.constant', [])

  .factory('messagingConstant', MessagingConstantFactory)

;

/**
 * @ngdoc service
 * @name messagingConstant
 * @module bns.messaging.constant
 *
 * @description
 * Contains various constants related to the messaging app.
 */
function MessagingConstantFactory () {

  return {
    MESSAGE: {
      STATUS: {
        DRAFT:          3,
        IN_MODERATION:  2,
        ACCEPTED:       1,
        REJECTED:       0,
        DELETED:       -1,
      },
      STATUS_READABLE: {
        3: 'draft',
        2: 'moderated',
        1: 'accepted',
        0: 'rejected',
        '-1': 'deleted',
      },
    },
    CONVERSATION: {
      STATUS: {
        SENT: 4,
        IN_MODERATION: 3,
        NONE_READ: 2,
        READ: 1,
        DELETED: 0,
      },
      STATUS_READABLE: {
        4: 'sent',
        3: 'moderated',
        2: 'unread',
        1: 'read',
        0: 'deleted',
      },
    },
  };

}

})(angular);
