(function (angular) {
'use strict';

angular.module('bns.main.docLink', [])

  .constant('BNS_DOC_ROOT', {
    fr: 'https://beneylu.com/school/doc/ajouter-ses-eleves/',
    en: 'https://beneylu.com/school/en/documentation/add-your-pupils/',
  })
  .directive('bnsDocLink', BNSDocLinkDirective)

;

function BNSDocLinkDirective (global, BNS_DOC_ROOT) {

  return {
    link: postLink,
  };

  function postLink (scope, element) {
    var locale = global('locale');
    var rootLocale = locale.substring(0, 2);
    var defaultLocale = 'en';

    var href = BNS_DOC_ROOT[locale] || BNS_DOC_ROOT[rootLocale] || BNS_DOC_ROOT[defaultLocale];

    element.attr('target', '_blank');
    element.attr('href', href);
  }

}

})(angular);
