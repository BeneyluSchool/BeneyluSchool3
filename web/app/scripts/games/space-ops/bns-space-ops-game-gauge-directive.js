(function (angular) {
'use strict';

angular.module('bns.spaceOps.gameGauge', [])

  .directive('bnsSpaceOpsGameGauge', BNSSpaceOpsGameGaugeDirective)
  .controller('BNSSpaceOpsGameGauge', BNSSpaceOpsGameGaugeController)

;

function BNSSpaceOpsGameGaugeDirective () {

  return {
    scope: {
      game: '=',
    },
    templateUrl: 'views/games/space-ops/bns-space-ops-game-gauge.html',
    controller: 'BNSSpaceOpsGameGauge',
    controllerAs: 'ctrl',
    bindToController: true,
  };

}

function BNSSpaceOpsGameGaugeController (SPACE_OPS) {

  var ctrl = this;

  ctrl.getStationNumber = getStationNumber;
  ctrl.getGaugePercent = getGaugePercent;

  function getStationNumber () {
    return ctrl.game.world % 5 || 5;
  }

  function getGaugePercent () {
    if (ctrl.game.won) {
      return 100;
    }

    return (ctrl.game.stage - 1) / SPACE_OPS.maxStage * 100;
  }

}

})(angular);
