(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.spaceOps.game
 */
angular.module('bns.spaceOps.game', [
  'bns.spaceOps.gameLevel',
])

  .factory('SpaceOpsGame', SpaceOpsGameFactory)

;

/**
 * @ngdoc service
 * @name SpaceOpsGame
 * @module bns.spaceOps.game
 *
 * @description
 * A Space Ops game constructor, holds current game session stats.
 *
 * @requires SPACE_OPS
 * @requires SpaceOpsGameLevel
 */
function SpaceOpsGameFactory (SPACE_OPS, SpaceOpsGameLevel) {

  function SpaceOpsGame (data) {
    this.init(data || {});
  }

  /**
   * Initializes a new game with the given data
   *
   * @param  {Object} data Preset game properties
   */
  SpaceOpsGame.prototype.init = function (data) {
    if (!data) {
      data = {};
    }

    this.world = data.world || 1;
    this.stage = data.stage || 1;
    this.lives = angular.isDefined(data.lives) ? data.lives : SPACE_OPS.lives;
    this.score = data.score || 0;
    this.won = data.won || false;

    if (data.operation) {
      this.setOperation(data.operation);
    }
  };

  /**
   * Selects the given operation, if valid
   *
   * @param {String} operation
   */
  SpaceOpsGame.prototype.setOperation = function (operation) {
    if ('all' === operation || SPACE_OPS.operations.indexOf(operation) > -1) {
      this.operation = operation;
    } else {
      throw 'Unknown operation: ' + operation;
    }
  };

  /**
   * Continues the current game, ie. goes to next world.
   */
  SpaceOpsGame.prototype.continue = function () {
    // keep score and lives, but reset the rest
    this.init(this);
    this.world++;
    this.stage = 1;
    this.won = false;
  };

  /**
   * Restarts the game, resetting everything
   */
  SpaceOpsGame.prototype.restart = function () {
    this.init();
  };

  /**
   * Checks if this game is won
   *
   * @returns {Boolean}
   */
  SpaceOpsGame.prototype.isWon = function () {
    return this.lives && this.won;
  };

  /**
   * Checks if this game is lost
   *
   * @returns {Boolean}
   */
  SpaceOpsGame.prototype.isLost = function () {
    return !this.lives;
  };

  /**
   * Generate a new level using this game configuration
   *
   * @returns {SpaceOpsGameLevel}
   */
  SpaceOpsGame.prototype.generateNewLevel = function () {
    return new SpaceOpsGameLevel(this.operation);
  };

  /**
   * Wins the given level
   *
   * @param  {SpaceOpsGameLevel} level
   */
  SpaceOpsGame.prototype.winLevel = function (level) {
    if (this.stage === SPACE_OPS.maxStage) {
      this.won = true;
    } else {
      this.stage++;
    }
    this.score += level.right; // add the value of the right operand as score
  };

  /**
   * Loses a level
   */
  SpaceOpsGame.prototype.loseLevel = function () {
    this.lives = Math.max(this.lives - 1, 0);
  };

  return SpaceOpsGame;

}

})(angular);
