(function (angular) {
'use strict';

angular.module('bns.spaceOps.controllers', [
  'bns.spaceOps.game',
])

  .controller('SpaceOpsBase', SpaceOpsBaseController)

;

function SpaceOpsBaseController ($scope, storage, me, SpaceOpsGame, Restangular, toast) {

  var ctrl = this;
  ctrl.start = start;
  ctrl.quit = quit;
  ctrl.share = share;
  ctrl.me = me;

  init();

  function init () {
    // Sync our model variable with local storage
    storage.bind($scope, 'gameData', {
      storeName: 'bns/space-ops/'+me.id,
      defaultValue: {},
    });

    $scope.$watch('ctrl.game.isWon()', function (isWon) {
      if (isWon) {
        ctrl.hasShared = false;
      }
    });

    // game session already present, kickstart with it
    if ($scope.gameData && $scope.gameData.operation) {
      initGame();
    }
  }

  function initGame () {
    // use the actual game object as state, serialized by the storage
    $scope.gameData = ctrl.game = new SpaceOpsGame($scope.gameData);
  }

  function start (operation) {
    initGame();
    ctrl.game.setOperation(operation);
  }

  function quit () {
    if (!ctrl.game) {
      return;
    }

    delete ctrl.game;
    $scope.gameData = {};
  }

  function share () {
    if (!(ctrl.game && ctrl.game.isWon()) || ctrl.hasShared) {
      return;
    }

    return Restangular.one('profile/status/space-ops').post('', {
      game: ctrl.game,
      user_id: me.id,
    })
      .then(function success () {
        toast.success('SPACE_OPS.FLASH_POST_STATUS_SUCCESS');
        ctrl.hasShared = true;
      })
      .catch(function error () {
        toast.error('SPACE_OPS.FLASH_POST_STATUS_ERROR');
      })
    ;
  }

}

})(angular);
