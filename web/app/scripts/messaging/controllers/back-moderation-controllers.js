(function (angular) {
'use strict';

angular.module('bns.messaging.back.moderationControllers', [
  'bns.user.groups',
  'bns.messaging.messages',
  'bns.messaging.rules',
  'bns.messaging.selectionHandler',
])

  .controller('MessagingBackModerationActionbar', MessagingBackModerationActionbarController)
  .controller('MessagingBackModerationContent', MessagingBackModerationContentController)
  .controller('MessagingBackModerationSidebar', MessagingBackModerationSidebarController)
  .factory('messagingModerationState', MessagingModerationStateFactory)

;

function MessagingBackModerationActionbarController (_, $rootScope, $translate, toast, messagingConstant, MessagingMessages, MessagingSelectionHandler, messagingModerationState) {

  var ctrl = this;
  ctrl.shared = messagingModerationState;
  ctrl.hasInModeration = hasInModeration;
  ctrl.hasAccepted = hasAccepted;
  ctrl.hasRejected = hasRejected;
  ctrl.canDelete = canDelete;
  ctrl.moderateSelection = moderateSelection;
  ctrl.acceptSelection = acceptSelection;
  ctrl.rejectSelection = rejectSelection;
  ctrl.deleteSelection = deleteSelection;
  ctrl.acceptAll = acceptAll;
  ctrl.deleteAll = deleteAll;

  ctrl.shared.handler = new MessagingSelectionHandler(
    MessagingMessages.one('selection', ''),
    ctrl.shared.selection
  );

  function hasAccepted () {
    return _.some(ctrl.shared.selection, { status: messagingConstant.MESSAGE.STATUS.ACCEPTED });
  }

  function hasRejected () {
    return _.some(ctrl.shared.selection, { status: messagingConstant.MESSAGE.STATUS.REJECTED });
  }

  function hasInModeration () {
    return _.some(ctrl.shared.selection, { status: messagingConstant.MESSAGE.STATUS.IN_MODERATION });
  }

  function canDelete () {
    return ctrl.shared.selection.length && _.all(ctrl.shared.selection, { status: messagingConstant.MESSAGE.STATUS.REJECTED });
  }

  function moderateSelection () {
    return ctrl.shared.handler.action('moderate', {
      unselect: true,
      success: 'MESSAGING.FLASH_MODERATE_MESSAGES_SUCCESS',
      error: 'MESSAGING.FLASH_MODERATE_MESSAGES_ERROR',
    })
      .then(refreshBox)
    ;
  }

  function acceptSelection () {
    return ctrl.shared.handler.action('accept', {
      unselect: true,
      success: 'MESSAGING.FLASH_ACCEPT_MESSAGES_SUCCESS',
      error: 'MESSAGING.FLASH_ACCEPT_MESSAGES_ERROR',
    })
      .then(refreshBox)
    ;
  }

  function rejectSelection () {
    return ctrl.shared.handler.action('reject', {
      unselect: true,
      success: 'MESSAGING.FLASH_REJECT_MESSAGES_SUCCESS',
      error: 'MESSAGING.FLASH_REJECT_MESSAGES_ERROR',
    })
      .then(refreshBox)
    ;
  }

  function deleteSelection () {
    return ctrl.shared.handler.action('delete', {
      unselect: true,
      success: 'MESSAGING.FLASH_DELETE_MESSAGES_SUCCESS',
      error: 'MESSAGING.FLASH_DELETE_MESSAGES_ERROR',
    })
      .then(refreshBox)
    ;
  }

  function refreshBox () {
    $rootScope.$emit('messaging.box.refresh');
  }

  function acceptAll () {
    return all('accept');
  }

  function deleteAll () {
    return all('delete');
  }

  function all (action) {
    return MessagingMessages.one('all').one(action).get()
      .then(function success (response) {
        var message = 'MESSAGING.FLASH_'+action.toUpperCase()+'_MESSAGES_SUCCESS';
        toast.success($translate.instant(message, { COUNT: response }, 'messageformat'));
        refreshBox();
      })
    ;
  }

}

function MessagingBackModerationContentController ($scope, $mdUtil, Groups, MessagingBox, MessagingFolders, messagingModerationState) {

  var ctrl = this;
  ctrl.shared = messagingModerationState;
  ctrl.search = '';               // search query

  ctrl.doSearch = $mdUtil.debounce(doSearch, 500);

  init();

  function init () {
    ctrl.shared.box = ctrl.box = new MessagingBox('moderation');

    $scope.$watch('ctrl.shared.status', function (status) {
      applyBoxParam('status', status);
    });

    $scope.$watch('ctrl.shared.group', function (group) {
      applyBoxParam('group', group);
    });

    // search box after 500ms
    $scope.$watch('ctrl.search', ctrl.doSearch);
  }

  function applyBoxParam (name, value) {
    if (!value) {
      return;
    }

    // empty selection, update box filter and refresh it
    ctrl.shared.selection.splice(0, ctrl.shared.selection.length);
    ctrl.box.params[name] = value;
    ctrl.box.init();
  }

  function doSearch (newSearch, oldSearch) {
    if (newSearch === oldSearch || !ctrl.box) {
      return;
    }

    if (ctrl.search) {
      ctrl.box.params.search = ctrl.search;
    } else {
      delete ctrl.box.params.search;
    }
    ctrl.box.init();
  }

}

function MessagingBackModerationSidebarController (navbar, Groups, MessagingRules, messagingModerationState) {

  var ctrl = this;
  ctrl.shared = messagingModerationState;
  ctrl.groups = [];               // available groups

  init();

  function init () {
    Groups.getList({right: 'MESSAGING_ACCESS_BACK'}).then(function success (groups) {
      ctrl.groups = groups;
    });

    navbar.getOrRefreshGroup()
      .then(buildModerationSwitchManagers)
    ;
  }

  /**
   * Builds simple interfaces for the bnsSwitch directives
   */
  function buildModerationSwitchManagers (group) {
    var groupId = '' + group.id;
    var groupStore = MessagingRules.one('GROUP').one(groupId);
    var externalStore = MessagingRules.one('EXTERNAL').one(groupId);

    ctrl.groupModerationSwitchManager = buildInterface(groupStore);

    ctrl.externalModerationSwitchManager = buildInterface(externalStore);

    // values are negated, because moderation active <=> no permission, so a
    // truey value in front means a falsey value in back
    function buildInterface (store) {
      return {
        getStatus: function () {
          return store.get();
        },
        toggle: function (status) {
          return store.patch({
            status: status,
          });
        },
      };
    }
  }

}

function MessagingModerationStateFactory () {

  return {
    status: 'IN_MODERATION',  // status filter
    group: null,              // group filter
    handler: null,
    box: null,
    selection: [],
  };

}

})(angular);
