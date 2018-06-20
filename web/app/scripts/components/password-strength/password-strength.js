(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.components.passwordStrength
 */
angular.module('bns.components.passwordStrength', [])

  .directive('bnsPasswordStrength', BNSPasswordStrengthDirective)

;

/**
 * @ngdoc directive
 * @name bnsPasswordStrength
 * @module bns.components.passwordStrength
 *
 * @description
 * Adds validators and a password strength meter to a ngModel. The input
 * is invalid until all validators pass and password strength is 100%.
 *
 * @example
 * <md-input-container>
 *   <input type="password" ng-model="model.password" bns-password-strength>
 * </md-input-container>
 *
 * @requires $compile
 * @requires $log
 */
function BNSPasswordStrengthDirective ($compile, $log) {

  return {
    restrict: 'A',
    require: 'ngModel',
    link: postLink,
  };

  function postLink (scope, element, attrs, ngModelCtrl) {
    var constraints = scope.$eval(attrs.bnsPasswordStrength) || {};
    var validators = {}; // map of regexes that must pass for input to be valid

    var minLength = constraints.minlength || attrs.minLength;
    if (minLength) {
      validators.minlength = new RegExp('.{'+minLength+',}'); // /.{8,}/
    }
    if (constraints.lowercase) {
      validators.lowercase = /[a-z]+/;
    }
    if (constraints.uppercase) {
      validators.uppercase = /[A-Z]+/;
    }
    if (constraints.number) {
      validators.number = /[0-9]+/;
    }
    if (constraints.specialchar) {
      validators.specialchar = /[-$€&*/;:,\.?~!"'^²_`\[\]}{%@#]+/;
    }

    scope._strength = 0;

    // by hom much password strength increases with each validator (in %)
    var strengthIncrement = 100 / (Object.keys(validators).length || 1);

    // register our validators
    angular.forEach(validators, function (regex, name) {
      ngModelCtrl.$validators[name] = function (modelValue, viewValue) {
        var value = modelValue || viewValue;
        return regex.test(value);
      };
    });

    // add a dummy parser to assess password strength, as a percentage of
    // successful validators
    ngModelCtrl.$parsers.push(function measurePasswordStrength (value) {
      scope._strength = 0;
      angular.forEach(validators, function (regex) {
        if (regex.test(value)) {
          scope._strength += strengthIncrement;
        }
      });
      scope._strength = Math.round(scope._strength, 2);

      return value;
    });

    // add a password strength meter after the input
    var $container = element.closest('md-input-container');
    if (!$container.length) {
      return $log.$warn('Could not find password input container');
    }
    var $meter = angular.element('<md-progress-linear class="password-strength" md-mode="determinate"></md-progress-linear>');
    $meter.attr({
      'ng-value': '_strength',
      'ng-class': '{\'md-warn\': _strength < 100, \'md-accent\': _strength === 100}',
    });
    element.after($compile($meter)(scope));
  }

}

})(angular);
