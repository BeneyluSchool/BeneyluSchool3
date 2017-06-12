(function (angular) {
'use strict';

angular.module('bns.builders.backMessagesControllers', [])

  .controller('BuildersBackMessagesSidebar', BuildersBackMessagesSidebarController)
  .controller('BuildersBackMessagesContent', BuildersBackMessagesContentController)
  .factory('buildersBackMessagesState', BuildersBackMessagesStateFactory)

;

function BuildersBackMessagesSidebarController ($scope, buildersBackMessagesState) {

  $scope.shared = buildersBackMessagesState;

  var ctrl = this;
  ctrl.statuses = [
    { value: 0, label: 'BUILDERS.LABEL_STATUS_NOT_APPROVED'  },
    { value: 1, label: 'BUILDERS.LABEL_STATUS_APPROVED' },
  ];

}

function BuildersBackMessagesContentController ($scope, Restangular, toast, buildersBackMessagesState) {

  var shared = $scope.shared = buildersBackMessagesState;

  var ctrl = this;
  ctrl.toggleApproval = toggleApproval;
  ctrl.busy = false;

  init();

  function init () {
    $scope.$watch('shared.filters', refreshMessages, true);

    return refreshMessages();
  }

  function refreshMessages () {
    if (ctrl.busy) {
      return; // avoid duplicate call (init + watch setup)
    }
    ctrl.busy = true;

    return Restangular.all('builders').all('messages').all('group').getList(shared.filters)
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

  function toggleApproval (message) {
    if (message._busy) {
      return;
    }
    message._busy = true;
    var data = {
      is_approved: message.is_approved ? 0 : 1,
    };

    return Restangular.all('builders').one('messages', message.id).patch(data)
      .then(function success (newMessage) {
        angular.merge(message, newMessage);

        if (shared.filters.is_approved !== null) {
          var filterValue = shared.filters.is_approved === '0' ? false : true;
          if (message.is_approved !== filterValue) {
            var idx = ctrl.messages.indexOf(message);
            if (idx > -1) {
              ctrl.messages.splice(idx, 1);
            }
          }
        }
      })
      .catch(function error (response) {
        toast.error('BUILDERS.FLASH_'+(message.is_approved?'REFUSE':'APPROVE')+'_MESSAGE_ERROR');
        throw response;
      })
      .finally(function end () {
        message._busy = false;
      })
    ;
  }

}

function BuildersBackMessagesStateFactory () {

  return {
    filters: {
      is_approved: null,
    },
  };

}

})(angular);
