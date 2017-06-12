(function (angular) {
'use strict';

angular.module('bns.breakfastTour.challengeController', [
  'bns.components.toast',
  'bns.breakfastTour.ingredients',
  'bns.breakfastTour.challenge',
])

  .controller('BreakfastTourChallengeController', BreakfastTourChallengeController)

;

function BreakfastTourChallengeController ($state, toast, Challenge, Ingredients) {

  var ctrl = this;
  ctrl.busy = true;
  ctrl.choices = [];
  ctrl.ordered = {};
  ctrl.doChallenge = doChallenge;

  init();

  function init () {
    ctrl.busy = true;
    Challenge.one('').get()
      .then(function success (challenge) {
        ctrl.challenge = challenge;
        ctrl.busy = false;
      })
      .catch(function error (response) {
        if (response.status !== 404) {
          throw 'Error while getting challenge';
        }
        initWithoutChallenge();
      })
    ;

    function initWithoutChallenge () {
      Ingredients.getList()
        .then(function success (ingredients) {
          ctrl.ingredients = ingredients;
        })
        .finally(function end () {
          ctrl.busy = false;
        })
      ;
    }
  }

  function doChallenge () {
    if (!ctrl.choices.length || ctrl.choices.length > 6) {
      return;
    }

    Challenge.post({choices: ctrl.choices })
      .then(function success () {
        ctrl.challenge = ctrl.choices;
      })
      .catch(function error () {
        toast.error('BREAKFAST_TOUR.FLASH_CHALLENGE_ERROR');
      })
    ;
  }

}

})(angular);
