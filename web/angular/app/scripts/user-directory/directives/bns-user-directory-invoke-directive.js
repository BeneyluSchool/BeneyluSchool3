'use strict';

angular

  .module('bns.userDirectory.bnsUserDirectoryInvoke', [
    'bns.userDirectory.users',
    'bns.userDirectory.state',
    'bns.main.navbar',
  ])

  /**
   * @ngdoc directive
   * @name bns.userDirectory.bnsUserDirectoryInvoke
   * @kind function
   *
   * @description
   * A directive used to invoke the user directory modal, in different modes
   *
   * ** Attributes **
   * - `selection`: {Boolean|Array} A truey value enables selection mode. If
   *                                array of IDs is given, the corresponding
   *                                users will be selected by default.
   * - `group`: {Object} The default group context.
   * - `type`: {String} The default viewed type. Can be one of 'user',
   *                    'distribution' or nothing (same as 'user').
   *
   * @example
   * <any bns-user-directory-invoke selection="[1, 2, 3]" group="myGroupId">
   *   Open the user directory!
   * </any>
   */
  .directive('bnsUserDirectoryInvoke', function () {
    return {
      scope: {
        selectionDistribution: '=',
        selectionRole: '=',
        selectionGroup: '=',
        selection: '=',
        lockedDistribution: '=',
        lockedGroup: '=',
        locked: '=',
        group: '=',
        onSelection: '=',
        view: '@bnsUserDirectoryInvoke',
        type: '@type',
      },
      controller: 'UserDirectoryInvokeController',
      controllerAs: 'ctrl',
      bindToController: true,
    };
  })

  .controller('UserDirectoryInvokeController', function ($element, _, navbar, userDirectory) {
    var ctrl = this;

    init();

    function init () {
      $element.on('click', invoke);
    }

    function invoke () {
      var conf = {
        type: ctrl.type,
        view: ctrl.view,
        group: ctrl.group || navbar.group,
        selectionGroup: ctrl.selectionGroup,
        selectionDistribution: ctrl.selectionDistribution,
        selectionRole: ctrl.selectionRole,
        selection: ctrl.selection,
        lockedGroup: ctrl.lockedGroup,
        locked: ctrl.locked,
        onSelection: ctrl.onSelection,
      };

      if (conf.selection && angular.isArray(conf.selection) && !conf.onSelection) {
        conf.onSelection = function (selectionGroup, selection, selectionDistribution, selectionRole) {
          var newDistributionIds = _.map(selectionDistribution, 'id');
          var newGroupIds = _.map(selectionGroup, 'id');
          var newIds = _.map(selection, 'id');
          var newRoles = angular.copy(selectionRole);

          // update selections with new ids
          if (ctrl.selectionRole && angular.isArray(conf.selectionRole)) {
            ctrl.selectionRole.splice(0, ctrl.selectionRole.length);
            Array.prototype.push.apply(ctrl.selectionRole, newRoles);
          }
          if (ctrl.selectionDistribution && angular.isArray(conf.selectionDistribution)) {
            ctrl.selectionDistribution.splice(0, ctrl.selectionDistribution.length);
            Array.prototype.push.apply(ctrl.selectionDistribution, newDistributionIds);
          }
          if (ctrl.selectionGroup && angular.isArray(conf.selectionGroup)) {
            ctrl.selectionGroup.splice(0, ctrl.selectionGroup.length);
            Array.prototype.push.apply(ctrl.selectionGroup, newGroupIds);
          }
          ctrl.selection.splice(0, ctrl.selection.length);
          Array.prototype.push.apply(ctrl.selection, newIds);
        };
      }
      userDirectory.activate(conf);
    }
  })

;
