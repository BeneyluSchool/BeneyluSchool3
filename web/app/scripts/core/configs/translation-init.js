(function (angular) {
'use strict';

angular.module('bns.core.translationInit', [
  'angularMoment',
  'mdPickers',
  'pascalprecht.translate',
  'bns.core.parameters',
  'bns.core.global',
])

  .config(TranslationInitConfig)
  .run(TranslationInitRun)

;

function TranslationInitConfig ($translateProvider, parametersProvider, globalProvider, $mdpDatePickerProvider, $mdpTimePickerProvider) {

  var base = parametersProvider.get('app_base_path');
  var locale = globalProvider.get('locale') || 'fr';
  var version = parametersProvider.get('version') || 'version';

  $translateProvider

    .useSanitizeValueStrategy('sanitizeParameters')

    // add optional interpolation
    .addInterpolation('$translateMessageFormatInterpolation')

    .useStaticFilesLoader({
      prefix:  base + '/js/translations/',
      suffix: '.json?v=' + version
    })

    .preferredLanguage(locale)

  ;

  var datePickerFormats = {
    fr: 'ddd DD MMM',
  };
  $mdpDatePickerProvider.setDisplayFormat(datePickerFormats[locale] || 'ddd, MMM DD');
  $mdpDatePickerProvider.setOKButtonLabel('{{\'MAIN.BUTTON_SAVE\'|translate}}');
  $mdpDatePickerProvider.setCancelButtonLabel('{{\'MAIN.BUTTON_CANCEL\'|translate}}');
  $mdpTimePickerProvider.setOKButtonLabel('{{\'MAIN.BUTTON_SAVE\'|translate}}');
  $mdpTimePickerProvider.setCancelButtonLabel('{{\'MAIN.BUTTON_CANCEL\'|translate}}');
}

function TranslationInitRun (amMoment, global) {

  var locale = global('locale') || 'fr';
  amMoment.changeLocale(locale);

}

}) (angular);
