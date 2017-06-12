(function (angular) {
'use strict';

var SPACE_OPS = {
  maxStage: 20,
  lives: 5,                       // starting number of lives
  operations: ['+', '-', '*'],    // available operations
  min: 1,                         // min value of an operand
  max: 10,                        // max value of an operand
};

angular.module('bns.spaceOps', [
  'bns.spaceOps.config.states',
  'bns.spaceOps.gameDirective',
  'bns.spaceOps.gameGauge',
  'bns.spaceOps.gameLives',
  'bns.spaceOps.niceOperator',
])

  .constant('SPACE_OPS', SPACE_OPS)

;

})(angular);
