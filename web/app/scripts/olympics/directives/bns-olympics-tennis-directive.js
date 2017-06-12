(function (angular) {
'use strict';

angular.module('bns.olympics.tennis', [])

  .directive('bnsOlympicsTennis', BnsOlympicsTennisDirective)
  .controller('BnsOlympicsTennis', BnsOlympicsTennisController)

;

function BnsOlympicsTennisDirective () {

  return {
    templateUrl: 'views/olympics/directives/bns-olympics-tennis.html',
    controller: 'BnsOlympicsTennis',
    controllerAs: 'ctrl',
    bindToController: true,
  };

}

function BnsOlympicsTennisController ($scope) {

  var COMMENTS_MAP = {
    ace: 'Ace !<br>C’est un service gagnant ! La balle de <strong>“%winner%”</strong> n’est pas touchée par <strong>“%loser%”</strong>. <strong>“%winner%”</strong> remporte donc le point.',
    advantage: 'Avantage !<br>On appelle avantage le point gagné par <strong>“%winner%”</strong> lorsque le score est à égalité.',
    matchpoint: 'Balle de match !<br>C’est le point à jouer offrant la victoire au joueur menant au score, donc à <strong>“%winner%”</strong>.', // TODO
    breakpoint: 'Balle de break !<br><strong>“%winner%”</strong> gagne le jeu alors que <strong>“%loser%”</strong> est au service. On dit alors qu’il a pris le service de son adversaire.', // TODO
    point: 'Point gagné !<br><strong>“%winner%”</strong> remporte le point !',
    game: 'Jeu !<br>Le premier joueur à gagner au moins 4 points (15, 30, 40, jeu) avec 2 points d’écart remporte le jeu. C’est donc <strong>“%winner%”</strong> qui gagne le jeu.',
    set: 'Set !<br>Un set est gagné par un joueur s’il remporte 6 jeux avec 2 jeux d’écart. C’est donc <strong>“%winner%”</strong> qui remporte le set.',
    tiebreak: 'Tie-break (ou jeu décisif) !<br>Lorsque les 2 adversaires ont remporté 6 jeux chacun, il y a tie-break. Le premier joueur qui atteint 7 points remporte le set, à condition d’avoir 2 points d’écart.', // TODO
    out: 'Faute !<br>Aïe ! La balle de <strong>“%loser%”</strong> rebondit en dehors des limites du court. Le point est perdu',
    match: 'Ici le match se joue en 2 sets gagnants. <strong>“%winner%”</strong> a donc gagné le match !',
  };

  var ctrl = this;
  ctrl.tennis = new TennisGame();
  ctrl.comment = null;

  $scope.$watch('ctrl.tennis.comment', updateComment);

  function updateComment (comment) {
    ctrl.comment = null;
    if (!COMMENTS_MAP[comment]) {
      return;
    }

    ctrl.comment = COMMENTS_MAP[comment]
      .split('%winner%').join(ctrl.tennis.winPoint.name)
      .split('%loser%').join(ctrl.tennis.losePoint.name)
    ;
  }

}

function Player (name) {
  this.name = name;
  this.reset();
}

Player.prototype.reset = function () {
  this.points = 0;
  this.advantage = false;
  this.games = [0, undefined, undefined];
  this.sets = 0;
  this.win = false;
};

function TennisGame () {
  this.p1 = new Player('Brabrabra');
  this.p2 = new Player('CARLA');
  this.players = [this.p1, this.p2];
  this.reset();
}

TennisGame.prototype.reset = function () {
  this.currentSet = 0;
  this.finished = false;
  this.winPoint = null;       // player that has won the last point
  this.losePoint = null;      // player that has lost the last point
  this.comment = null;        // comment for the last point (ace, advantage, etc...)
  this.p1.reset();
  this.p2.reset();
};

TennisGame.prototype.addPoints = function (playerNumber) {
  if (this.finished) {
    return;
  }

  var opponentNumber = playerNumber ? 0 : 1;
  var player = this.players[playerNumber];
  var opponent = this.players[opponentNumber];

  var random = Math.random();
  if (random > 0.8) {
    this.comment = 'ace';
  } else if (random > 0.7) {
    this.comment = 'doublefault';
  } else if (random > 0.6) {
    this.comment = 'out';
  } else if (random > 0.4) {
    this.comment = 'point';
  } else {
    this.comment = null;
  }

  this.winPoint = player;
  this.losePoint = opponent;

  if (this.currentSet < 2 && player.games[this.currentSet] === 6 && opponent.games[this.currentSet] === 6) { // tie break
    player.points += 1;
    if (player.points >= 7 && player.points > opponent.points + 1) {
      this.comment = 'tiebreakgame';
      player.games[this.currentSet]++;
      player.points = opponent.points = 0;
      player.advantage = opponent.advantage = false;
    }
  } else {
    if (player.points < 30) {
      player.points += 15;
    } else if (player.points < 40) {
      player.points += 10;
    } else {
      if (opponent.points < 40) {
        this.comment = 'game';
        player.games[this.currentSet]++;
        player.points = opponent.points = 0;
        player.advantage = opponent.advantage = false;
      } else {
        if (player.advantage) {
          this.comment = 'game';
          player.games[this.currentSet]++;
          player.points = opponent.points = 0;
          player.advantage = opponent.advantage = false;
        } else {
          if (opponent.advantage) {
            this.comment = 'equality';
            opponent.advantage = false;
          } else {
            this.comment = 'advantage';
            player.advantage = true;
          }
        }
      }
    }
  }

  // check if set is finished
  if ((player.games[this.currentSet] >= 6 && player.games[this.currentSet] > opponent.games[this.currentSet] + 1) || (player.games[this.currentSet] === 7 && this.currentSet < 2)) {
    this.comment = 'set';
    player.sets++;

    // two sets won, end game
    if (player.sets >= 2) {
      this.comment = 'match';
      player.win = true;
      this.finished = true;

      return;
    }

    // prepare next set
    this.currentSet++;
    player.games[this.currentSet] = 0;
    opponent.games[this.currentSet] = 0;
  }
};

})(angular);
