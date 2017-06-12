(function (angular) {
'use strict';

angular.module('bns.twoDegrees.mapController', [
  'bns.components.dialog',
  'bns.components.toast',
  'bns.twoDegrees.state',
  'bns.twoDegrees.twoDegrees',
  'bns.twoDegrees.menuController',
])

  .controller('TwoDegreesMap', TwoDegreesMapController)

;

function TwoDegreesMapController (_, $state, $mdBottomSheet, dialog, toast, TwoDegrees, twoDegreesState) {

  var ctrl = this;
  ctrl.state = twoDegreesState;
  ctrl.challenges = ctrl.state.challenges; // use cached value if available
  ctrl.canNavigate = canNavigate;
  ctrl.navigate = navigate;
  ctrl.toggleMenu = toggleMenu;
  ctrl.getCompleted = getCompleted;
  ctrl.getPlayerPosition = getPlayerPosition;
  ctrl.getThermometerValue = getThermometerValue;
  ctrl.menuShown = false;

  init();

  function init () {
    ctrl.busy = true;

    // refresh stored challenges
    return TwoDegrees.getChallenges()
      .then(function success (challenges) {
        _.merge(ctrl.challenges, challenges);
      })
      .catch(function error (response) {
        toast.error(response && (response.statusText || response.message));
      })
      .finally(function end () {
        ctrl.busy = false;
      })
    ;
  }

  function canNavigate (challenge) {
    if (challenge.completed) {
      return true;
    } else {
      if (1 === challenge.position) {
        return true;
      } else {
        var previous = _.find(ctrl.challenges, {position: challenge.position - 1});
        if (previous) {
          // can navigate to next challenge if previous one is complete
          return previous.completed;
        }
      }
    }

    return false; // something is wrong
  }

  function navigate (challenge) {
    if (!ctrl.canNavigate(challenge)) {
      return;
    }

    if (challenge.completed) {
      if (challenge.activity) {
        dialog.custom({
          templateUrl: 'views/two-degrees/activities/dialog.html',
          locals: {
            activity: challenge.activity,
          },
          controller: ['$scope', '$mdDialog', function dialogCtrl ($scope, $mdDialog) {
            $scope.$mdDialog = $mdDialog;
          }],
          controllerAs: 'dialog',
          bindToController: true,
          clickOutsideToClose: true,
        });
      }
    } else {
      $state.go('^.challenge.solve', { code: challenge.code });
    }
  }

  function toggleMenu () {
    if (ctrl.menuShown) {
      $mdBottomSheet.hide();
    } else {
      ctrl.menuShown = true;
      $mdBottomSheet.show({
        templateUrl: 'views/two-degrees/menu.html',
        controller: 'TwoDegreesMenu',
        controllerAs: 'menu',
        parent: angular.element('#two-degrees-map-container'),
      })
      .then(function resolve (action) {
        switch (action) {
          case 'reset':
            reset();
        }
      })
      .finally(function menuClosed () {
        ctrl.menuShown = false;
      });
    }
  }

  function getCompleted () {
    var completed = 0;

    ctrl.challenges.forEach(function (challenge) {
      if (challenge.completed) {
        completed++;
      }
    });

    return completed;
  }

  function getPlayerPosition () {
    if (ctrl.challenges.length) {
      return Math.max(Math.min(ctrl.getCompleted() + 1, 6), 1);
    }

    return 0;
  }

  function getThermometerValue () {
    if (ctrl.challenges.length) {
      return 8 - ctrl.getCompleted();
    }

    return 0;
  }

  function reset () {
    return dialog.confirm({
      title: 'TWO_DEGREES.TITLE_RESET_GAME',
      content: 'TWO_DEGREES.DESCRIPTION_RESET_GAME',
      ok: 'TWO_DEGREES.BUTTON_OK',
      cancel: 'TWO_DEGREES.BUTTON_CANCEL',
    })
      .then(doReset)
    ;
  }

  function doReset () {
    ctrl.busy = true;

    return TwoDegrees.resetGame()
      .then(function success () {
        toast.success('TWO_DEGREES.FLASH_RESET_GAME_SUCCESS');
        init();
      })
      .finally(function end () {
        ctrl.busy = false;
      })
    ;
  }

}

})(angular);
