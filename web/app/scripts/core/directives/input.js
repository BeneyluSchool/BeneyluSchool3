(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.core.input
 */
angular.module('bns.core.input', [])

  .directive('input', InputDirective)

;

/**
 * @ngdoc directive
 * @name input
 * @module bns.core.input
 *
 * @description
 * Extends the core ng input directive
 */
function InputDirective () {

  return {
    restrict: 'E',
    require: ['?ngModel'],
    priority: -1000,
    link: {
      pre: preLink,
    }
  };

  function preLink (scope, element, attrs, ctrls) {
    var ngModel = ctrls[0];
    if (!ngModel) {
      return;
    }

    // input[type=number] : allow parsing of localized numbers
    if ('number' === attrs.type) {
      ngModel.$parsers.push(function(value) {
        return value;
      });
      ngModel.$formatters.push(function(value) {
        if (value && value.replace) {
          return +(value.replace(',', '.'));
        }

        return value;
      });
    }

    if (attrs.type === 'time') {
      ngModel.$formatters.unshift(function(value) {
        return value.replace(/:00\.000$/, '');
      });
    }

    if (attrs.bnsMinlength) {
      // wait for model to be initialized
      var unwatch = scope.$watch(function () {
        return ngModel.$modelValue;
      }, function (model) {
        // if model is array, validate min length
        if (angular.isArray(model)) {
          var min = parseInt(attrs.bnsMinlength, 10);
          scope.$watchCollection(function () {
            return ngModel.$modelValue;
          }, function validate (model) {
            ngModel.$setValidity('bnsMinlength', model.length >= min);
          });
          unwatch();
        }
      });
    }
  }

}

}) (angular);
