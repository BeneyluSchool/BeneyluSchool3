(function (angular) {
'use strict';

angular.module('bns.main.correction')

  .directive('bnsCorrection', BnsCorrectionDirective)

;

/**
 * @ngdoc directive
 * @name bnsCorrection
 * @module bns.main.correction
 *
 * @description
 * Marco directive to setup correction in a tinymce-enhanced textarea.
 *
 * **Attributes**
 *  - `bnsCorrectionIf` {=Boolean}: Checks this value before setting up
 *                                  correction (optional).
 *  - `bnsCorrection` {=Object}: The Correction model to bind to.
 *  - `bnsEditable` {=Boolean}: Whether to allow edition, addition and removal
 *                              of correction data.
 *
 * @example
 * <textarea ng-model="my.model.text" bns-tinymce bns-correction="my.model.correction"></textarea>
 */
function BnsCorrectionDirective ($compile, $mdMedia) {

  return {
    restrict: 'A',
    terminal: true,
    priority: 1055, // run before bnsTinymce
    link: postLink,
  };

  function postLink (scope, element, attrs) {
    element.removeAttr('bns-correction');

    if (angular.isDefined(attrs.bnsCorrectionIf) && !scope.$eval(attrs.bnsCorrectionIf)) {
      return $compile(element)(scope);
    }

    // trigger ngInit manually here, since it has lower priority but we may need
    // it for correction setup
    if (attrs.ngInit) {
      scope.$eval(attrs.ngInit);
      element.removeAttr('ng-init');
    }

    // no correction data and no edit mode => nothing to do
    var correction = scope.$eval(attrs.bnsCorrection);
    if ((!scope.$eval(attrs.bnsEditable) && !(correction && correction.has_data)) || ($mdMedia.hasTouch && !$mdMedia('gt-sm'))) {
      return $compile(element)(scope);
    }

    return setupCorrection(scope, element, attrs);
  }

  function setupCorrection (scope, element, attrs) {
    element.attr('bns-correction-link', attrs.bnsCorrection);
    if (attrs.bnsTinymce) {
      element.attr('bns-correction-config', attrs.bnsTinymce);
    } else {
      scope._correctionConfig = {};
      element.attr('bns-correction-config', '_correctionConfig');
      element.attr('bns-tinymce', '_correctionConfig');
    }

    var wrapper = angular.element('<div class="layout-row bns-correction-wrapper"><div class="flex bns-correction-editor-container"></div></div>');
    var sidebar = angular.element('<md-sidenav md-component-id="annotations" md-disable-backdrop="true" class="md-sidenav-right bns-correction-sidenav"><bns-correction-annotations correction="'+attrs.bnsCorrection+'" bns-editable="'+attrs.bnsEditable+'"></bns-correction-annotations></md-sidenav>');
    element.wrap(wrapper);
    wrapper = element.parent().parent(); // update to the DOM-inserted reference
    wrapper.prepend(sidebar);

    $compile(wrapper)(scope);
  }

}

})(angular);
