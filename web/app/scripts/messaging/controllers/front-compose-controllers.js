(function (angular) {
'use strict';

angular.module('bns.messaging.front.composeControllers', [
  'bns.messaging.messageHandler',
  'bns.messaging.messageType',
])

  .controller('MessagingFrontComposeActionbar', MessagingFrontComposeActionbarController)
  .controller('MessagingFrontComposeContent', MessagingFrontComposeContentController)
  .factory('messagingComposeState', MessagingComposeStateFactory)

;

var AUTOSAVE_DELAY = 2000;  // ms

function MessagingFrontComposeActionbarController ($rootScope, $scope, $timeout, $state, messagingComposeState) {

  var ctrl = this;
  ctrl.shared = messagingComposeState;
  ctrl.removeDraft = removeDraft;

  function removeDraft () {
    if (!ctrl.shared.handler) {
      return;
    }

    return ctrl.shared.handler.removeDraft()
      .then(function success () {
        $rootScope.$emit('messaging.counters.refresh');
        $state.go('app.messaging.front.drafts');
      })
    ;
  }

}

function MessagingFrontComposeContentController (_, $rootScope, $scope, $state, $mdUtil, MessageHandler, messagingComposeState) {

  var ctrl = this;
  ctrl.shared = messagingComposeState;
  ctrl.postMessage = postMessage;
  ctrl.doDraftMessage = $mdUtil.debounce(draftMessage, AUTOSAVE_DELAY);

  init();

  function init () {
    // keep the form list and the user directory list in sync
    $scope.$watchCollection('ctrl.shared.type.tos', function (ids, oldIds) {
      if (ids && ids.join && ids !== oldIds) {
        ctrl.shared.type.form.to.$setViewValue(ids.join(','));
      }
    });

    ctrl.shared.handler = new MessageHandler($state.params.id);
    ctrl.shared.handler.load()
      .then(function success (message) {
        // form is not present (ie state has changed before this resolve)
        if (!ctrl.shared.type.form) {
          return;
        }

        ctrl.shared.type.form.id = message.id;
        ctrl.shared.type.form.subject.value = message.subject;
        ctrl.shared.type.form.to.value = message.to;

        // update the local collection of ids
        ctrl.shared.type.setTos(message.to);

        ctrl.shared.type.setAttachments(message.attachments);

        // wait for full form initialization
        var unwatch = $scope.$watch('ctrl.shared.type.form.content', function (content) {
          if (!content) {
            return;
          }
          unwatch();

          ctrl.shared.type.form.content.value = message.content;

          // watch form data for changes, fire autosave
          watchFormData();
        });


        // actual textarea content is not initialized properly by ui-tinymce
        angular.element('textarea[name="content"]').val(message.content);
      })
    ;
  }

  function watchFormData () {
    ctrl.currentData = ctrl.shared.type.getData();
    $scope.$watch(function checkFormDataChanges () {
      var newData = ctrl.shared.type.getData();

      // deep comparison, but not using ===
      // different objects with same keys and values are considered equals
      if (!_.isEqual(ctrl.currentData, newData)) {
        ctrl.currentData = newData;
      }

      return ctrl.currentData;
    }, ctrl.doDraftMessage);
  }

  function draftMessage (data, oldData) {
    // false positive, mainly fired by the first $watch
    if (_.isEqual(data, oldData)) {
      return;
    }

    if (!ctrl.shared.handler) {
      return;
    }

    return ctrl.shared.handler.draft(data)
      .then(function success (draft) {
        // form is not present (ie state has changed before this resolve)
        if (!ctrl.shared.type.form) {
          return;
        }

        ctrl.shared.type.form.id = draft.id;

        if (data.draftId !== draft.id) {
          ctrl.currentData.draftId = draft.id; // ninja update to avoid autosave change detection
          $rootScope.$emit('messaging.counters.refresh');
          $state.go('app.messaging.front.compose.edit', {id: draft.id}, {
            notify: false,
            reload: false,
            location: 'replace',
            inherit: true,
          });
        }
      })
    ;
  }

  function postMessage () {
    var data = ctrl.shared.type.getData();

    return ctrl.shared.handler.save(data)
      .then(function success () {
        $rootScope.$emit('messaging.counters.refresh');
        $state.go('app.messaging.front.inbox');
      })
    ;
  }

}

function MessagingComposeStateFactory (_, MessageType) {

  return {
    busy: false,
    type: new MessageType(),
  };

}

})(angular);
