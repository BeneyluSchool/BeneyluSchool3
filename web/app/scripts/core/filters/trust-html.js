(function (angular) {
'use strict';

angular.module('bns.core.trustHtml', [])

  .filter('trustHtml', TrustHtmlFilter)

;

function TrustHtmlFilter ($sce) {

  return function (value) {
    return $sce.trustAsHtml(value);
  };

}

})(angular);
