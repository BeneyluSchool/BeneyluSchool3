(function (angular) {
'use strict';

angular.module('bns.spaceOps.gameLives', [])

  .directive('bnsSpaceOpsGameLives', BNSSpaceOpsGameLivesDirective)

;

function BNSSpaceOpsGameLivesDirective (SPACE_OPS) {

  return {
    scope: {
      lives: '=',
    },
    templateUrl: 'views/games/space-ops/bns-space-ops-game-lives.html',
    link: postLink,
  };

  function postLink (scope) {
    scope.lifeRange = [];
    for (var i = 1; i <= SPACE_OPS.lives; i++) {
      scope.lifeRange.push(i);
    }
  }

}

})(angular);
