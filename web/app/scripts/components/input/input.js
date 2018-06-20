(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.components.input
 *
 * @description
 * All things related to inputs.
 */
angular.module('bns.components.input', [])

  .directive('capitalize', CapitalizeDirective)
  .directive('bnsAutofocus', BNSAutofocusDirective)
  .directive('bnsValidityIndicator', BNSValidityIndicatorDirective)
  .directive('bnsValidateRepeated', BNSValidateRepeatedDirective)
;

/**
 * @ngdoc directive
 * @name capitalize
 * @module bns.components.input
 *
 * @description
 * Works with a ngModel on an input field to auto-capitalize the inputed value.
 */
function CapitalizeDirective () {

  return {
    restrict: 'A',
    require: 'ngModel',
    link: postLink,
  };

  function postLink (scope, element, attrs, ngModelCtrl) {
    ngModelCtrl.$parsers.push(capitalize);

    // initial value
    capitalize(scope[attrs.ngModel]);

    function capitalize (value) {
      if (!(value && value.toUpperCase)) {
        value = '';
      }
      var capitalized = value.toUpperCase();
      if (capitalized !== value) {
        ngModelCtrl.$setViewValue(capitalized);
        ngModelCtrl.$render();
      }

      return capitalized;
    }
  }

}

/**
 * @ngdoc directive
 * @name bnsAutofocus
 * @module bns.components.input
 *
 * @description
 * Auto focus an element upon activation.
 *
 * @requires $timeout
 */
function BNSAutofocusDirective ($timeout) {

  return {
    link: postLink,
  };

  function postLink (scope, element) {
    autofocus(element);

    scope.$on('bns.autofocus', function(){
      autofocus(element);
    });
  }

  function autofocus(element) {
    $timeout(function () {
      element.focus();
    }, 0);
  }

}

/**
 * @ngdoc directive
 * @name bnsValidityIndicator
 * @module bns.components.input
 *
 * @description
 * Displays a validity indicator aside the input.
 *
 * @example
 * <md-input-container>
 *   <input ng-model="my.model" bns-validity-indicator>
 * </md-input-container>
 *
 * @requires $compile
 */
function BNSValidityIndicatorDirective ($compile) {

  return {
    restrict: 'A',
    require: 'ngModel',
    link: postLink,
    scope: true,
  };

  function postLink (scope, element, attrs, ngModelCtrl) {
    scope.$watch(function () {
      return ngModelCtrl.$valid;
    }, function checkValidity (isValid) {
      scope._valid = isValid;
    });

    var $indicator = angular.element('<md-icon>{{_valid ? \'check\' : \'clear\'}}</md-icon>');
    $indicator.attr({
      'ng-class': '\'text-\' + (_valid ? \'accent\' : \'warn\')',
    });
    element.after($compile($indicator)(scope));
    element.closest('md-input-container').addClass('md-icon-right');
  }

}

/**
 * @ngdoc directive
 * @name bnsValidateRepeated
 * @module bns.components.input
 *
 * @description
 * Validates that the model on the input is equal to the given one. Useful for
 * "repeat password" fields for example.
 *
 * @example
 * <input ng-model="my.repeated_model" bns-validate-repeated="my.original_model">
 */
function BNSValidateRepeatedDirective () {

  return {
    restrict: 'A',
    require: 'ngModel',
    link: postLink,
  };

  function postLink (scope, element, attrs, ngModelCtrl) {
    // validate against OTHER when THIS value changes
    ngModelCtrl.$validators.repeated = repeatedValidator;

    // watch OTHER value for changes and update THIS validity
    scope.$watch(attrs.bnsValidateRepeated, function (baseValue) {
      ngModelCtrl.$setValidity('repeated', ngModelCtrl.$modelValue === baseValue);
    });

    function repeatedValidator (modelValue, viewValue) {
      var value = modelValue || viewValue;

      return value === scope.$eval(attrs.bnsValidateRepeated);
    }
  }

}

})(angular);
