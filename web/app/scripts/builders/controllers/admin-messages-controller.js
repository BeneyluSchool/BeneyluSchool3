(function (angular) {
'use strict';

angular.module('bns.builders.adminMessagesController', [])

  .controller('BuildersAdminMessages', BuildersAdminMessagesController)

;

function BuildersAdminMessagesController ($scope, Restangular, toast) {

  var ctrl = this;
  ctrl.filters = {
    is_approved_admin: null,
  };
  ctrl.toggleApprovalAdmin = toggleApprovalAdmin;

  init();

  function init () {
    $scope.$watch('ctrl.filters', refreshMessages, true);
  }

  function refreshMessages () {
    if (ctrl.busy) {
      return;
    }
    ctrl.busy = true;

    return Restangular.all('builders').all('messages').all('all').getList(ctrl.filters)
      .then(function success (messages) {
        ctrl.messages = messages;
      })
      .catch(function error (response) {
        toast.error('BUILDERS.FLASH_GET_MESSAGES_ERROR');

        throw response;
      })
      .finally(function end () {
        ctrl.busy = false;
      })
    ;
  }

  function toggleApprovalAdmin (message) {
    if (message._busy) {
      return;
    }
    message._busy = true;
    var data = {
      is_approved_admin: message.is_approved_admin ? 0 : 1,
    };

    return Restangular.all('builders').one('messages', message.id).patch(data)
      .then(function success (newMessage) {
        angular.merge(message, newMessage);

        if (ctrl.filters.is_approved_admin !== null) {
          var filterValue = ctrl.filters.is_approved_admin === '0' ? false : true;
          if (message.is_approved_admin !== filterValue) {
            var idx = ctrl.messages.indexOf(message);
            if (idx > -1) {
              ctrl.messages.splice(idx, 1);
            }
          }
        }
      })
      .catch(function error (response) {
        toast.error('BUILDERS.FLASH_'+(message.is_approved_admin?'REFUSE':'APPROVE')+'_MESSAGE_ERROR');
        throw response;
      })
      .finally(function end () {
        message._busy = false;
      })
    ;
  }

}

})(angular);
