(function (angular) {
'use strict';

angular.module('bns.olympics.rowing', [])

  .directive('bnsOlympicsRowing', BnsOlympicsRowingDirective)
  .controller('BnsOlympicsRowing', BnsOlympicsRowingController)

;

function BnsOlympicsRowingDirective () {

  return {
    templateUrl: 'views/olympics/directives/bns-olympics-rowing.html',
    controller: 'BnsOlympicsRowing',
    controllerAs: 'ctrl',
    bindToController: true,
  };

}

function BnsOlympicsRowingController ($injector) {

  var ctrl = this;
  ctrl.rowing = new RowingGame($injector);
  ctrl.begin = begin;
  ctrl.reset = reset;

  function begin () {
    ctrl.played = true;
    ctrl.rowing.start();
  }

  function reset () {
    ctrl.rowing.reset();
    ctrl.rowing.start();
  }

}

function RowingGame ($injector) {
  this._$timeout = $injector.get('$timeout');
  this._$interval = $injector.get('$interval');
  this.TIMER_MAX = 30; // s
  this.ROW_WINDOW_DURATION_MIN = 300;   // ms
  this.ROW_WINDOW_DURATION_MAX = 500;   // ms
  this.ROW_WINDOW_INTERVAL_MIN = 500;   // ms
  this.ROW_WINDOW_INTERVAL_MAX = 1500;  // ms
  this.window = false;
  this.canScore = false;
  this.reset();
}

RowingGame.prototype.reset = function () {
  this.timer = this.TIMER_MAX;
  this.score = 0;
};

RowingGame.prototype.row = function () {
  if (!this.timer) {
    return;
  }

  var points = this.TIMER_MAX - this.timer;

  if (this.window) {
    if (this.canScore) {
      this.score += points;
      this.canScore = false;
    }
  } else {
    this.score = Math.max(0, this.score - Math.floor(points / 2));
  }
};

RowingGame.prototype.start = function () {
  var game = this;

  // timer ticks
  game.timerInterval = game._$interval(function () {
    game.timer--;

    if (!game.timer) {
      game._$interval.cancel(game.timerInterval);
    }
  }, 1000);

  // row windows
  var rowWindowDuration = game.ROW_WINDOW_DURATION_MAX;
  var rowWindowInterval = game.ROW_WINDOW_INTERVAL_MAX;
  game._$timeout(updateRowWindow, rowWindowDuration);

  function updateRowWindow () {
    if (!game.timer) {
      return;
    }

    // open current window
    game.window = true;
    game.canScore = true;
    game._$timeout(function () {
      game.window = false;
    }, rowWindowDuration);

    // prepare next window
    rowWindowDuration = ((game.ROW_WINDOW_DURATION_MAX - game.ROW_WINDOW_DURATION_MIN) * game.timer / game.TIMER_MAX) + game.ROW_WINDOW_DURATION_MIN;
    rowWindowInterval = ((game.ROW_WINDOW_INTERVAL_MAX - game.ROW_WINDOW_INTERVAL_MIN) * game.timer / game.TIMER_MAX) + game.ROW_WINDOW_INTERVAL_MIN;
    game._$timeout(updateRowWindow, rowWindowInterval);
  }
};

})(angular);
