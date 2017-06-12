(function (angular) {
'use strict';

angular.module('bns.core.translationInitLight', [
  'pascalprecht.translate',
  'bns.core.parameters',
  'bns.core.global',
])

  .config(TranslationInitLightConfig)

;

function TranslationInitLightConfig ($translateProvider, parametersProvider, globalProvider) {
  var base = parametersProvider.get('app_base_path');
  var locale = globalProvider.get('locale');
  var version = parametersProvider.get('version') || 'version';

  if (base && locale) {
    $translateProvider
      .useSanitizeValueStrategy('sanitizeParameters')
      .useStaticFilesLoader({
        prefix:  base + '/js/translations/',
        suffix: '.json?v=' + version
      })
      .preferredLanguage(locale)
    ;
  }
}

}) (angular);
