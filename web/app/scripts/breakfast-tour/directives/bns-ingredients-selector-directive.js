(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.breakfastTour.ingredientsSelector
 */
angular.module('bns.breakfastTour.ingredientsSelector', [])

  .directive('bnsIngredientsSelector', BNSIngredientsSelectorDirective)
  .controller('BNSIngredientsSelectorController', BNSIngredientsSelectorController)

;

var MAX_INGREDIENTS = 3;

/**
 * @ngdoc directive
 * @name bnsIngredientsSelector
 * @module bns.breakfastTour.ingredientsSelector
 *
 * @description
 * Holder for ingredients selection
 *
 * ** Attributes **
 * - `ingredients {Array}`: list of available ingredients
 * - `choices {Array}`: list of selected ingredients
 * - `orderded {Object}`: map of selected ingredients, whose order are preserved
 */
function BNSIngredientsSelectorDirective () {

  return {
    restrict: 'E',
    templateUrl: 'views/breakfast-tour/bns-ingredients-selector.html',
    scope: {
      ingredients: '=',
      choices: '=',
      ordered: '=',
      max: '@',
    },
    controller: 'BNSIngredientsSelectorController',
    controllerAs: 'selector',
    bindToController: true,
  };

}

function BNSIngredientsSelectorController ($scope, $element) {

  var selector = this;
  var max = parseInt(selector.max, 10) || MAX_INGREDIENTS;
  selector.toggle = toggle;
  selector.has = has;
  selector.isFull = isFull;

  $scope.$watchCollection('selector.choices', function () {
    if (isFull()) {
      $element.addClass('full');
    } else {
      $element.removeClass('full');
    }
  });

  function isFull () {
    return selector.choices && selector.choices.length === max;
  }

  function has (ingredient) {
    return selector.choices.indexOf(ingredient) > -1;
  }

  function toggle (ingredient) {
    if (has(ingredient)) {
      remove(ingredient);
    } else if (!isFull()) {
      add(ingredient);
    }
  }

  function add (ingredient) {
    selector.choices.push(ingredient);

    // insert the ingredient at the first available position
    for (var i = 1; i <= max; i++) {
      if (!selector.ordered[i]) {
        selector.ordered[i] = ingredient;
        break;
      }
    }
  }

  function remove (ingredient) {
    var idx = selector.choices.indexOf(ingredient);
    selector.choices.splice(idx, 1);

    // remove the positioned ingredient
    for (var pos in selector.ordered) {
      if (selector.ordered.hasOwnProperty(pos)) {
        if (selector.ordered[pos] === ingredient) {
          delete selector.ordered[pos];
          break;
        }
      }
    }
  }

}

})(angular);
