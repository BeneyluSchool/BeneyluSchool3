(function (angular) {
'use strict';

angular.module('bns.breakfastTour.ingredients', [
  'restangular',
])


  .factory('Ingredients', IngredientsFactory)

;

function IngredientsFactory (_, Restangular) {

  var Ingredients = Restangular.service('ingredients', Restangular.one('breakfast-tour', ''));

  return Ingredients;

}

})(angular);
