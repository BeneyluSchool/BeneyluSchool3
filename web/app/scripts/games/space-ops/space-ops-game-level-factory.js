(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.spaceOps.gameLevel
 */
angular.module('bns.spaceOps.gameLevel', [])

  .factory('SpaceOpsGameLevel', SpaceOpsGameLevelFactory)

;

/**
 * @ngdoc service
 * @name SpaceOpsGameLevel
 * @module bns.spaceOps.gameLevel
 *
 * @description
 * A level of the Space Ops game
 *
 * @requires $parse
 * @requires $q
 * @requires SPACE_OPS
 */
function SpaceOpsGameLevelFactory ($parse, $q, SPACE_OPS) {

  function SpaceOpsGameLevel (operation) {
    this.left = this.getOperand();
    this.right = this.getOperand();
    this.operator = this.getOperator(operation);
    this.finishSetup();
  }

  /**
   * Gets a random operand between the configured min and max (inclusive);
   *
   * @returns {Integer}
   */
  SpaceOpsGameLevel.prototype.getOperand = function () {
    return Math.floor(Math.random() * (SPACE_OPS.max - SPACE_OPS.min + 1)) + SPACE_OPS.min;
  };

  /**
   * Gets the operator corresponding to the given operation (may be random).
   *
   * @param {String} operation The wanted operation
   * @returns {String}
   */
  SpaceOpsGameLevel.prototype.getOperator = function (operation) {
    if ('all' === operation) {
      return SPACE_OPS.operations[Math.floor(Math.random() * SPACE_OPS.operations.length)];
    } else if (SPACE_OPS.operations.indexOf(operation) > -1) {
      return operation;
    } else {
      throw 'Invalid operation: ' + operation;
    }
  };

  /**
   * Performs last setup steps, once operands and operator have been set
   */
  SpaceOpsGameLevel.prototype.finishSetup = function () {
    // ensure that substractions always yield positive results
    if ('-' === this.operator && this.left < this.right) {
      var tmp = this.left;
      this.left = this.right;
      this.right = tmp;
    }
  };

  /**
   * Evaluates the current operation and gives the result
   *
   * @returns {Integer}
   */
  SpaceOpsGameLevel.prototype.getAnswer = function () {
    return $parse('' + this.left + this.operator + this.right)();
  };

  /**
   * Proposes the given answer as solution of the operation. Returns a promise
   * that will be resolved if given answer is correct, and rejected otherwise.
   * Both wil receive the correct answer as parameter
   *
   * @param  {Integer} proposedAnswer
   * @returns {Object} A promise
   */
  SpaceOpsGameLevel.prototype.solve = function (proposedAnswer) {
    var lvl = this;

    return $q(function resolver (resolve, reject) {
      var answer = parseInt(lvl.getAnswer(), 10);
      if (answer === parseInt(proposedAnswer, 10)) {
        resolve(answer);
      } else {
        reject(answer);
      }
    });
  };

  return SpaceOpsGameLevel;

}

})(angular);
