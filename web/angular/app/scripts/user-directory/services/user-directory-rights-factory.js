'use strict';

angular.module('bns.userDirectory.rights', [
  'bns.userDirectory.state',
])

  /**
   * @ngdoc service
   * @name bns.userDirectory.rights.userDirectoryRights
   * @kind function
   *
   * @description
   * Allows to perform various right checks.
   *
   * ** Methods **
   * - `canAddGroup()`: If current user can add a group in current group
   * - `canDeleteGroup()`: If current user can delete a group in current group
   *
   * @requires userDirectoryState
   *
   * @returns {Object} The userDirectoryRights service
   */
  .factory('userDirectoryRights', function (userDirectoryState) {
    var service = {
      canAddGroup: canAddGroup,
      canDeleteGroup: canDeleteGroup,
      canEditWorkspace: canEditWorkspace,
      canAddUsers: canAddUsers,
      canRemoveUsers: canRemoveUsers,
      canAddLists: canAddLists,
    };

    return service;

    function canAddGroup () {
      // TODO: remove school check
      return canManageContext() && userDirectoryState.isRootGroup() && 'SCHOOL' !== userDirectoryState.context.type && 'PARTNERSHIP' !== userDirectoryState.context.type;
    }

    function canDeleteGroup () {
      return canManageContext() && userDirectoryState.isSubGroup();
    }

    function canEditWorkspace() {
      return canManageContext();
    }

    function canAddUsers() {
      return canManageContext() && userDirectoryState.isSubGroup();
    }

    function canRemoveUsers() {
      return canManageContext() && userDirectoryState.isSubGroup();
    }

    function canAddLists () {
      return canManageContext() && userDirectoryState.context.distributable;
    }

    /**
     * Low-level check for context rights.
     *
     * @returns {Boolean} Whether the current context is manageable
     */
    function canManageContext () {
      return userDirectoryState.context && userDirectoryState.context.manageable;
    }
  })

;
