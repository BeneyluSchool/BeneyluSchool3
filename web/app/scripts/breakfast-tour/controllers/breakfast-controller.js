(function (angular) {
'use strict';

angular.module('bns.breakfastTour.breakfastController', [
  'bns.components.dialog',
])

  .controller('BreakfastTourBreakfastController', BreakfastTourBreakfastController)

;

function BreakfastTourBreakfastController ($scope, dialog, breakfast) {

  var ctrl = this;
  ctrl.breakfast = breakfast;
  ctrl.showRecipe = showRecipe;
  ctrl.hideRecipe = hideRecipe;

  function showRecipe ($event) {
    dialog.custom({
      clickOutsideToClose: true,
      templateUrl: 'views/breakfast-tour/recipe-dialog.html',
      scope: $scope,
      preserveScope: true,
      targetEvent: $event,
      parent: '#app-breakfast-tour',
    });
  }

  function hideRecipe () {
    dialog.cancel();
  }

}

})(angular);
