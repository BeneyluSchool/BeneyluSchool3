(function (angular) {
'use strict';

angular.module('bns.main.correction')

  .directive('bnsCorrectionAnnotationsToggle', BnsCorrectionAnnotationsToggleDirective)

;

/**
 * @ngdoc directive
 * @name bnsCorrectionAnnotationsToggle
 * @module bns.main.correction
 *
 * @description
 * A macro directive for the annotation toggle button.
 *
 * @requires $compile
 * @requires annotationSidebar
 */
function BnsCorrectionAnnotationsToggleDirective ($compile, $window, $location, annotationSidebar) {

  return {
    restrict: 'AE',
    link: postLink,
    templateUrl: 'views/main/correction/bns-correction-annotation-toggle.html',
  };

  function postLink (scope) {
    scope._annotationSidebar = annotationSidebar;

    var showAnnotations = false;
    var queryString = $window.location.search.replace('?', '');
    var params = queryString.split('&');
    angular.forEach(params, function (param) {
      param = param.split('=');
      if ('annotations' === param[0]) {
        showAnnotations = scope.$eval(param[1]);
      }
    });

    if (!showAnnotations) {
      showAnnotations = scope.$eval($location.search().annotations);
    }

    if (showAnnotations) {
      annotationSidebar.open();
    }
  }

}

})(angular);
