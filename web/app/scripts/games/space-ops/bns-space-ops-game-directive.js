(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.spaceOps.gameDirective
 */
angular.module('bns.spaceOps.gameDirective', [])

  .directive('bnsSpaceOpsGame', BNSSpaceOpsGameDirective)
  .controller('BNSSpaceOpsGame', BNSSpaceOpsGameController)

;

/**
 * @ngdoc directive
 * @name bnsSpaceOpsGame
 * @module bns.spaceOps.gameDirective
 *
 * @description
 * Interface for a Space Ops game
 *
 * ** Attributes **
 * - `game`: a SpaceOpsGame instance
 */
function BNSSpaceOpsGameDirective () {

  return {
    restrict: 'E',
    scope: {
      game: '=',
    },
    transclude: true,
    templateUrl: 'views/games/space-ops/bns-space-ops-game.html',
    controller: 'BNSSpaceOpsGame',
    controllerAs: 'ctrl',
    bindToController: true,
  };

}

function BNSSpaceOpsGameController ($rootScope, $timeout, $translate, SPACE_OPS) {

  var ctrl = this;
  ctrl.solve = solve;
  ctrl.minValue = 0;
  ctrl.maxValue = SPACE_OPS.max * SPACE_OPS.max;
  ctrl.status = '';

  init();

  function init () {
    if (!ctrl.game && ctrl.game.operation) {
      return console.warn('Space Ops Game not initialized');
    }

    newLevel();
  }

  function newLevel () {
    ctrl.level = ctrl.game.generateNewLevel();
    ctrl.status = '';
    ctrl.answer = null;
    ctrl.message = null;
  }

  function solve () {
    if (!(ctrl.level && angular.isDefined(ctrl.answer)) || ctrl.status) {
      return;
    }

    ctrl.status = '';

    return ctrl.level.solve(ctrl.answer)
      .then(success)
      .catch(error)
      .finally(end)
    ;
    function success () {
      ctrl.game.winLevel(ctrl.level);
      ctrl.status = 'win';
    }
    function error (response) {
      ctrl.game.loseLevel();
      ctrl.status = 'lose';
      ctrl.message = $translate.instant('SPACE_OPS.DESCRIPTION_CORRECT_ANSWER', {value: response});
    }
    function end () {
      if (ctrl.game.isWon() || ctrl.game.isLost()) {
        newLevel();
      } else {
        $timeout(function () {
          newLevel();
        }, 2500);
      }
    }
  }

}

})(angular);
