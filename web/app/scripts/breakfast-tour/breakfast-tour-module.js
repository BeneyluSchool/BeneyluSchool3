(function (angular) {
'use strict'  ;

angular.module('bns.breakfastTour', [
  // config
  'bns.breakfastTour.config.states',

  // services
  'bns.breakfastTour.breakfasts',

  // directives
  'bns.breakfastTour.ingredientsSelector',
  'bns.breakfastTour.ingredientVisual',
  'bns.breakfastTour.navigation',
]);

})(angular);
