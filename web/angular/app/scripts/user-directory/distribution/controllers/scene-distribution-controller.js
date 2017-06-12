(function (angular) {
'use strict';

angular.module('bns.userDirectory.distribution.sceneController', [
  'bns.userDirectory.distribution.userDirectoryDistributions',
  'bns.userDirectory.distribution.deleteDialogController',
  'bns.userDirectory.distribution.formDialogControllers',
])

  .controller('UserDirectoryDistributionScene', UserDirectoryDistributionSceneController)

;

function UserDirectoryDistributionSceneController (_, $rootScope, $scope, $stateParams, $q, dialog, URL_BASE_VIEW, userDirectoryGroups, userDirectoryDistributions, userDirectoryState) {

  var ctrl = this;
  ctrl.selectionDistribution = userDirectoryState.selectionDistribution;
  ctrl.busy = false;
  ctrl.showCreateDialog = showCreateDialog;
  ctrl.showEditDialog = showEditDialog;

  init();

  function init () {
    ctrl.busy = true;
    ctrl.toggle = toggle;

    var unlistenCreate = $rootScope.$on('userDirectory.distribution.createRequest', function (event, clickEvent) {
      showCreateDialog(clickEvent);
    });
    var unlistenRemove = $rootScope.$on('userDirectory.distribution.deleteRequest', function (event, clickEvent) {
      showDeleteDialog(clickEvent);
    });
    $scope.$on('$destroy', function cleanup () {
      unlistenCreate();
      unlistenRemove();
    });

    userDirectoryGroups.get($stateParams.id)
      .then(function success (group) {
        ctrl.group = group;
      })
    ;

    return userDirectoryDistributions.getList($stateParams.id)
      .then(function success (lists) {
        ctrl.lists = lists;
      })
      .finally(function end () {
        ctrl.busy = false;
      })
    ;
  }

  function showCreateDialog (event) {
    if (!ctrl.group) {
      return $q.reject();
    }

    return dialog.custom({
      templateUrl: URL_BASE_VIEW + '/user-directory/distribution/form-dialog.html',
      controller: 'UserDirectoryDistributionCreateDialog',
      controllerAs: 'ctrl',
      bindToController: true,
      locals: {
        group: ctrl.group,
        model: false,
      },
      targetEvent: event,
    })
      .then(success)
    ;
    function success (list) {
      ctrl.lists.push(list);
    }
  }

  function showEditDialog (event, list) {
    return dialog.custom({
      templateUrl: URL_BASE_VIEW + '/user-directory/distribution/form-dialog.html',
      controller: 'UserDirectoryDistributionEditDialog',
      controllerAs: 'ctrl',
      bindToController: true,
      locals: {
        group: ctrl.group,
      },
      resolve: {
        model: ['userDirectoryDistributions', function (userDirectoryDistributions) {
          return userDirectoryDistributions.get(list.id);
        }],
      },
      targetEvent: event,
    })
      .then(success)
    ;
    function success (newList) {
      angular.merge(list, newList);
    }
  }

  function showDeleteDialog (event) {
    return dialog.custom({
      templateUrl: URL_BASE_VIEW + '/user-directory/distribution/delete-dialog.html',
      controller: 'UserDirectoryDistributionDeleteDialog',
      controllerAs: 'ctrl',
      bindToController: true,
      locals: {
        preselected: ctrl.selectionDistribution.list,
      },
      targetEvent: event,
    })
      .then(success)
    ;
    function success (lists) {
      angular.forEach(lists, function (removed) {
        _.remove(ctrl.lists, {id: removed.id});
      });
    }
  }

  function toggle (list) {
    if (list.selected) {
      ctrl.selectionDistribution.add(list);
    } else {
      ctrl.selectionDistribution.remove(list);
    }
  }

}

})(angular);
