(function (angular) {
'use strict';

angular.module('bns.breakfastTour.ingredientVisual', [])

  .directive('bnsIngredientVisual', BNSIngredientVisualDirective)

;

// TODO: create child scope only if necessary (if attrs.ingredient is present)
function BNSIngredientVisualDirective () {

  return {
    restrict: 'E',
    replace: true,
    scope: true,
    template: function (element, attrs) {
      // 2-way data binding only if ingredient is dynamic (attrs.ingredient is provided)
      return '<span class="ingredient-dish">' +
        '<span class="ingredient-visual food-{{ '+(attrs.ingredient?'':'::')+'ingredient.code || ingredient }}"></span>' +
        (attrs.tooltip ? '<md-tooltip md-direction="'+attrs.tooltip+'">{{ingredient.description}}</md-tooltip>' : '') +
      '</span>';
    },
    link: postLink,
  };

  function postLink (scope, element, attrs) {
    // 2-way data binding only if necessary (defaults to 'ingredient' in parent scope)
    if (attrs.ingredient) {
      scope.$watch(attrs.ingredient, function () {
        scope.ingredient = scope.$eval(attrs.ingredient);
      });
    }
  }

}

})(angular);
