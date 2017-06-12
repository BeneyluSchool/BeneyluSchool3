(function (angular) {
'use strict';

angular.module('bns.olympics.fencing', [])

  .directive('bnsOlympicsFencing', BnsOlympicsFencingDirective)
  .controller('BnsOlympicsFencing', BnsOlympicsFencingController)

;

function BnsOlympicsFencingDirective () {

  return {
    templateUrl: 'views/olympics/directives/bns-olympics-fencing.html',
    controller: 'BnsOlympicsFencing',
    controllerAs: 'ctrl',
    bindToController: true,
  };

}

function BnsOlympicsFencingController () {

  var ctrl = this;
  ctrl.weapons = [{
    code: 'foil',
    name: 'Le fleuret',
    description: 'Le fleuret est une arme de pointe. Il te permet de toucher le tronc de ton adversaire : du col à l’entrejambe et dans le dos. Sa pointe est protégée par un bouton que l’on appelait autrefois “fleur de laine”, d’où le nom “fleuret”. Le fleuret est une arme légère inventée pour ne pas se blesser lors des entraînements.',
  }, {
    code: 'saber',
    name: 'Le sabre',
    description: 'Le sabre te permet de toucher ton adversaire au dessus de la ceinture avec le tranchant de l’arme. C’était <strong>l’arme du cavalier</strong>. C’est pour cela que la zone de touche est le haut du corps, car à cheval, c’est difficile de toucher un adversaire plus bas sans être gêné !',
  }, {
    code: 'sword',
    name: 'L’épée',
    description: 'L’épée est une arme de pointe, comme le fleuret. Elle te permet de toucher ton adversaire sur toute la surface du corps, de la tête aux mains jusqu’aux pieds ! Avec l’épée, <strong>le premier qui touche marque le point</strong>.',
  }, ];
  ctrl.active = {}; // active flags

}

})(angular);
