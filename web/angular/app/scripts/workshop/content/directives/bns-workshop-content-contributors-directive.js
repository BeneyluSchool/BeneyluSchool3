'use strict';

angular.module('bns.workshop.content.contributors', [
  'bns.core.url',
  'bns.user.users',
])

  .directive('bnsWorkshopContentContributors', function (url) {
    return {
      templateUrl: url.view('workshop/content/directives/bns-workshop-content-contributors.html'),
      scope: {
        content: '=',
      },
      controller: 'WorkshopContentContributorsController',
      controllerAs: 'ctrl',
      bindToController: true,
    };
  })

  .controller('WorkshopContentContributorsController', function ($rootScope, $scope, Users) {
    var ctrl = this;
    ctrl.userState = {
      busy: false,
    };
    ctrl.groupState = {
      busy: false,
    };
    ctrl.groupConfigurator = groupConfigurator;
    ctrl.USER_DIRECTORY_VIEW = 'workshop-contributors';

    init();

    function init () {
      updateLocks();

      $scope.$watch('ctrl.groupState', updateStates, true);
      $scope.$watch('ctrl.userState', updateStates, true);

      var unregisterContentUpdated = $rootScope.$on('workshop.content.updated', function () {
        updateLocks();
      });

      $scope.$on('$destroy', function () {
        unregisterContentUpdated();
      });
    }

    function groupConfigurator (userDirectoryGroups) {
      userDirectoryGroups.view(ctrl.USER_DIRECTORY_VIEW);
    }

    function updateStates () {
      ctrl.ready = ctrl.userState.ready && ctrl.groupState.ready;
      ctrl.busy = ctrl.userState.busy || ctrl.groupState.busy;
    }

    function updateLocks () {
      Users.me().then(function (me) {
        if (!me.rights.workshop_document_manage_lock) {
          ctrl.userState.locked = angular.copy(ctrl.content._embedded.contributor_user_ids);
          ctrl.groupState.locked = angular.copy(ctrl.content._embedded.contributor_group_ids);
        }
      });
    }
  })

;
