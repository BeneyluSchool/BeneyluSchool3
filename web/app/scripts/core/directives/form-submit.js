(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.core.formSubmit
 */
angular.module('bns.core.formSubmit', [])

  .directive('form', FormDirective)

;

/**
 * @ngdoc directive
 * @name formSubmit
 * @module bns.core.formSubmit
 *
 * @description
 * Enhances the behavior of the HTML5 input `form` attribute (that allows form
 * submission from outside), to work in any browser, from any element.
 *
 * **Important notice**
 * Manually triggering form submission from the outside almost always actually
 * submits the form. To avoid this, prevent the default event behaviour in the
 * ng-submit callback (see example below).
 *
 * @example
 * <!-- a cool form, somewhere -->
 * <form id="my-form" ng-submit="$event.preventDefault(); submitMyForm()">
 *   ...
 * </form>
 *
 * <!-- in another part of the document (can be in another controller, scope,
 * view, whatever). Element can be button, a, div, ... -->
 * <any form="my-form">Submit !</any>
 */
function FormDirective () {

  return {
    restrict: 'A',
    link: postLink,
  };

  function postLink (scope, element, attrs) {
    if (!attrs.form) {
      return;
    }

    element.on('click', handleClick);

    function handleClick (event) {
      // avoid double handler of actual <button[form]>
      event.preventDefault();

      // Find the target form, and submit it. Do not use vanilla form.submit(),
      // else ng-submit is not triggered.
      var $form = angular.element('form#'+attrs.form);
      if (!$form.length) {
        return console.warn('Invalid form target', $form);
      }
      $form.trigger('submit');
    }
  }

}

})(angular);
