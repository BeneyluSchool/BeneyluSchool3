(function (angular) {
'use strict';

angular.module('bns.breakfastTour.unlockController', [
  'bns.components.toast',
  'bns.breakfastTour.ingredients',
  'bns.breakfastTour.breakfasts',
])

  .controller('BreakfastTourUnlockController', BreakfastTourUnlockController)

;

function BreakfastTourUnlockController ($state, toast, breakfast, Breakfasts, Ingredients) {

  var ctrl = this;
  ctrl.choices = [];
  ctrl.ordered = {};
  ctrl.breakfast = breakfast;
  ctrl.unlock = unlock;

  init();

  function init () {
    Ingredients.getList({type: 'game'})
      .then(function success (ingredients) {
        ctrl.ingredients = ingredients;
      })
    ;
  }

  function unlock () {
    Breakfasts.unlock(breakfast.code, ctrl.choices)
      .then(function success () {
        breakfast.unlocked = true;
        $state.go('^.success');
      })
      .catch(function error (response) {
        if (404 === response.status) {
          toast.error('BREAKFAST_TOUR.FLASH_COMBINE_WRONG');
        } else {
          toast.error('BREAKFAST_TOUR.FLASH_COMBINE_ERROR');
        }
      })
    ;
  }

}

})(angular);
