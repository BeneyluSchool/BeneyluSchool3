'use strict';

/**
 * @ngdoc module
 * @name bns.components.libphone
 */
angular.module('bns.components.libphone', [])

  .directive('bnsLibphone', BNSLibphoneDirective)
  .constant('COUNTRY_CODES', [
    'AD', 'AE', 'AF', 'AG', 'AI', 'AL', 'AM', 'AO', 'AQ', 'AR', 'AS', 'AT', 'AU',
    'AW', 'AX', 'AZ', 'BA', 'BB', 'BD', 'BE', 'BF', 'BG', 'BH', 'BI', 'BJ', 'BL',
    'BM', 'BN', 'BO', 'BQ', 'BR', 'BS', 'BT', 'BV', 'BW', 'BY', 'BZ', 'CA', 'CC',
    'CD', 'CF', 'CG', 'CH', 'CI', 'CK', 'CL', 'CM', 'CN', 'CO', 'CR', 'CU', 'CV',
    'CW', 'CX', 'CY', 'CZ', 'DE', 'DJ', 'DK', 'DM', 'DO', 'DZ', 'EC', 'EE', 'EG',
    'EH', 'ER', 'ES', 'ET', 'FI', 'FJ', 'FK', 'FM', 'FO', 'FR', 'GA', 'GB', 'GD',
    'GE', 'GF', 'GG', 'GH', 'GI', 'GL', 'GM', 'GN', 'GP', 'GQ', 'GR', 'GS', 'GT',
    'GU', 'GW', 'GY', 'HK', 'HM', 'HN', 'HR', 'HT', 'HU', 'ID', 'IE', 'IL', 'IM',
    'IN', 'IO', 'IQ', 'IR', 'IS', 'IT', 'JE', 'JM', 'JO', 'JP', 'KE', 'KG', 'KH',
    'KI', 'KM', 'KN', 'KP', 'KR', 'KW', 'KY', 'KZ', 'LA', 'LB', 'LC', 'LI', 'LK',
    'LR', 'LS', 'LT', 'LU', 'LV', 'LY', 'MA', 'MC', 'MD', 'ME', 'MF', 'MG', 'MH',
    'MK', 'ML', 'MM', 'MN', 'MO', 'MP', 'MQ', 'MR', 'MS', 'MT', 'MU', 'MV', 'MW',
    'MX', 'MY', 'MZ', 'NA', 'NC', 'NE', 'NF', 'NG', 'NI', 'NL', 'NO', 'NP', 'NR',
    'NU', 'NZ', 'OM', 'PA', 'PE', 'PF', 'PG', 'PH', 'PK', 'PL', 'PM', 'PN', 'PR',
    'PS', 'PT', 'PW', 'PY', 'QA', 'RE', 'RO', 'RS', 'RU', 'RW', 'SA', 'SB', 'SC',
    'SD', 'SE', 'SG', 'SH', 'SI', 'SJ', 'SK', 'SL', 'SM', 'SN', 'SO', 'SR', 'SS',
    'ST', 'SV', 'SX', 'SY', 'SZ', 'TC', 'TD', 'TF', 'TG', 'TH', 'TJ', 'TK', 'TL',
    'TM', 'TN', 'TO', 'TR', 'TT', 'TV', 'TW', 'TZ', 'UA', 'UG', 'UM', 'US', 'UY',
    'UZ', 'VA', 'VC', 'VE', 'VG', 'VI', 'VN', 'VU', 'WF', 'WS', 'YE', 'YT', 'ZA',
    'ZM', 'ZW'
  ])

;

/**
 * @ngdoc directive
 * @name bnsLibphone
 * @module bns.components.libphone
 */
function BNSLibphoneDirective(_, libphonenumber, $compile, COUNTRY_CODES) {

  return {
    require: 'ngModel',
    scope: {
      countryCode: '@'
    },
    restrict: 'A',
    link: postLink
  };

  function postLink(scope, element, attrs, ngModelCtrl) {
    if (!ngModelCtrl) {
      return;
    }

    setup();
    addCountrySelector();

    function setup () {
      if (!scope.countryCode) {
        scope.countryCode = 'FR';
      }

      // Change the displayed value after every keypress
      element.bind('keyup', function() {
        scope.$apply(function () {
          var value = element.val();
          var asYouType = new libphonenumber.asYouType(scope.countryCode);
          var formatted = asYouType.input(value);
          if (asYouType.country) {
            scope.countryCode = asYouType.country;
          }
          element.val(formatted);
          ngModelCtrl.$setViewValue(formatted);
        });
      });

      // view -> model
      ngModelCtrl.$parsers.push(function formatIntlNumber (viewValue) {
        var parsed = libphonenumber.parse(viewValue, {
          country: { default: scope.countryCode },
        });
        if (parsed.country) {
          scope.countryCode = parsed.country;
        }
        if (parsed.phone) {
          return libphonenumber.format(parsed, 'International');
        }

        return viewValue;
      });

      ngModelCtrl.$validators.validNumber = function (ngModelValue) {
        if (!ngModelValue) {
          return true;
        }
        var parsed = libphonenumber.parse(ngModelValue, {
          country: { default: scope.countryCode },
        });

        return libphonenumber.isValidNumber(parsed);
      };

      // refresh view (and therefore model) value on country change
      scope.$watch('countryCode', function () {
        if (!ngModelCtrl.$viewValue) {
          return;
        }
        var currentValue = ngModelCtrl.$viewValue;
        ngModelCtrl.$setViewValue('');
        ngModelCtrl.$setViewValue(currentValue);
      });
    }

    function addCountrySelector () {
      var toggler = '<md-select ng-model="countryCode" placeholder="Country" class="md-no-underline">' +
        _.map(COUNTRY_CODES, function (code) {
          return '<md-option value="'+code+'">'+code+'</md-option>';
        }).join('') +
      '</md-select>';
      toggler = angular.element(toggler);
      $compile(toggler)(scope);
      element.parent().addClass('bns-country-select-container').append(toggler);
    }
  }
}
