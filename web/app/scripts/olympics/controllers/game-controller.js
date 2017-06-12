(function (angular) {
'use strict';

angular.module('bns.olympics.gameController', [
  'bns.olympics.state',
])

  .controller('OlympicsGame', OlympicsGameController)

;

function OlympicsGameController ($stateParams, dialog, olympicsState, canManage) {

  var ctrl = this;
  ctrl.game = $stateParams.game;
  ctrl.canManage = canManage;

  init();

  function init () {
    olympicsState.setPlayed(ctrl.game, true);
    olympicsState.setLastPlayed(ctrl.game);

    return dialog.show({
      templateUrl: 'views/olympics/dialog.html',
      locals: {
        game: ctrl.game,
      },
    });
  }

}

})(angular);
