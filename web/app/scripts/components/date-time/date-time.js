(function (angular) {
'use strict';

angular.module('bns.components.dateTime', [])

  .config(DecorateMdpDatePickerDirectiveConfig)
  .config(DecorateMdpTimePickerDirectiveConfig)
  .directive('bnsDateTimeInputContainer', BNSDateTimeInputContainerDirective)

;

/**
 * Augment the mdp-date-picker directive in attribute mode (used directly on
 * inputs) to support custom formats, on non-touch devices.
 *
 * @requires $provide
 */
function DecorateMdpDatePickerDirectiveConfig ($provide) {

  $provide.decorator('mdpDatePickerDirective', function ($delegate, $compile, $mdMedia, moment) {
    // hijack the second declaration of the directive (restrict: A)
    var directive = $delegate[1];
    var link = directive.link;

    directive.compile = function (element) {
      // keep vanilla input on touch devices
      if (!$mdMedia.hasTouch) {
        element.attr('type', 'text');
      }

      return function postLink (scope, element, attrs, ngModelCtrl) {
        // do absolutely nothing on touch devices => vanilla datepicker and
        // display
        if ($mdMedia.hasTouch) {
          return;
        }

        // apply original link function
        link.apply(this, arguments);

        // element is in an actual form: create a dummy date element with same
        // name, bound to same value, to support html submission
        var form = element.closest('form');
        if (form.length && form.scope()) {
          var hidden = angular.element('<input type="date">').attr({
            name: attrs.name,
            'ng-model': attrs.ngModel,
          }).css({
            display: 'none',
          });
          form.append($compile(hidden)(form.scope()));
        }

        // TODO: intl format (displayed value)
        scope.format = attrs.mdpDateFormat || 'DD/MM/YYYY';

        ngModelCtrl.$formatters.unshift(formatter);
        ngModelCtrl.$parsers.unshift(parser);

        element.on('change', function (e) {
          e.target.value = formatter(ngModelCtrl.$modelValue);
        });

        function formatter (value) {
          var date = moment(value);
          if (value && date && date.isValid()) {
            return date.format(scope.format);
          } else {
            return value ? value : '';
          }
        }

        function parser (value) {
          var date = moment(value, scope.format);
          ngModelCtrl.$setValidity('date', date.isValid());
          if (date.isValid()) {
            // use intl date format: play nice with default ng formatters for input[type="date"]
            return date.format('YYYY-MM-DD');
          } else {
            return value;
          }
        }
      };
    };

    return $delegate;
  });

}

/**
 * Augment the mdp-time-picker directive in attribute mode (used directly on
 * inputs) on non-touch devices.
 *
 * @requires $provide
 */
function DecorateMdpTimePickerDirectiveConfig ($provide) {

  $provide.decorator('mdpTimePickerDirective', function ($delegate, $mdMedia) {
    // hijack the second declaration of the directive (restrict: A)
    var directive = $delegate[1];
    var link = directive.link;

    directive.compile = function () {
      return function postLink () {
        // do absolutely nothing on touch devices => vanilla datepicker
        if ($mdMedia.hasTouch) {
          return;
        }

        // apply original link function
        link.apply(this, arguments);
      };
    };

    return $delegate;
  });

}

/**
 * @ngdoc directive
 * @name bnsDateTimeInputContainer
 * @module bns.components.dateTime
 *
 * @restrict EA
 *
 * @description bns-date-time-input-container is a directive that wraps an html
 * date/time input. It adds the appropriate date/time picker. Use it with the
 * mdInputContainer directive to add all the material chrome.
 *
 * @deprecated Used only to add mdp-date-picker directive on legacy date input
 *             containers.
 *
 * @example
 * <md-input-container bns-date-time-input-container>
 *   <label>My date</label>
 *   <input type="date" name="my_date">
 * </md-input-container>
 */
function BNSDateTimeInputContainerDirective ($compile, $log) {

  return {
    restrict: 'EA',
    link: postLink,
    priority: 10000,
    terminal: true,
  };

  function postLink (scope, element) {
    var input = element.find('input');
    var type = input.attr('type');
    if ('text' === type) {
      type = input.attr('data-type'); // use fallback type
    }
    if (['date', 'datetime', 'datetime-local'].indexOf(type) > -1) {
      input.attr('mdp-date-picker', true);
    } else if (['time'].indexOf(type) > -1) {
      input.attr('mdp-time-picker', true);
      input.attr('mdp-auto-switch', 'true');
    } else {
      // firefox does not support html5 dates and reports input as type = 'text'
      $log.warn('Forcing date on input type:', type);
      input.attr('mdp-date-picker', true);
    }

    // remove the directive to avoid lock, and resume compilation
    element.removeAttr('bns-date-time-input-container');
    $compile(element)(scope);
  }

}

}) (angular);
