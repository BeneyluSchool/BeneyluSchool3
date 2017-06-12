'use strict';

angular.module('bns.userDirectory.state', [
  'bns.core.collectionMap',
])

  /**
   * @ngdoc service
   * @name bns.userDirectory.state.userDirectoryState
   * @kind function
   *
   * @description
   * Maintains states accross the user directory
   *
   * ** Attributes **
   * - `selection {CollectionMap}`: the current user selection
   * - `selectionGroup {CollectionMap}`: the current group selection
   * - `allowGroupSelection` {Boolean}: Whether group selection is allowed.
   *                                    Defaults to false.
   * - `selectionDistribution {CollectionMap}`: the current distribution list
   *                                            selection
   * - `selectionRole {CollectionMap}`: the current role selection
   * - `allowRoleSelection {Boolean}`: whether role selection is allowed.
   *                                   Defaults to false.
   * - `onSelection {Function}`: an optional callback, to be executed when
   *                             selection is validated
   * - `context {Object}`: the current scene context
   * - `intent {Object}`: an optional wanted context, to be loaded when app is
   *                      ready
   * - `view {Object}`: an optional global view mode, that may impact loaded
   *                    API data (which groups are visible, for example)
   *
   * @requires CollectionMap
   *
   * @returns {Object} The userDirectoryState service
   */
  .factory('userDirectoryState', function (CollectionMap) {
    var service = {
      selection: new CollectionMap([]),
      selectionGroup: new CollectionMap([]),
      allowGroupSelection: false,
      selectionDistribution: new CollectionMap([]),
      selectionRole: new CollectionMap([]),
      allowRoleSelection: false,
      context: null,
      isRootGroup: isRootGroup,
      isSubGroup: isSubGroup,
      reset: reset,
    };

    return service;

    function isRootGroup () {
      return service.context && ['SCHOOL', 'CLASSROOM', 'PARTNERSHIP'].indexOf(service.context.type) >= 0;
    }

    function isSubGroup() {
      return service.context && ['TEAM'].indexOf(service.context.type) >= 0;
    }

    function reset () {
      service.selection.reset();
      service.selectionGroup.reset();
      service.selectionDistribution.reset();
      service.selectionRole.reset();
      service.allowRoleSelection = false;
      service.onSelection = null;
      service.context = null;
      service.intent = null;
      service.view = null;
    }
  })

;
