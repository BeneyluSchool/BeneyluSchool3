(function (angular) {
'use strict';

angular.module('bns.main.correction')

  .directive('bnsCorrectionAnnotations', BnsCorrectionAnnotationsDirective)
  .controller('BnsCorrectionAnnotations', BnsCorrectionAnnotationsController)

;

/**
 * @ngdoc directive
 * @name bnsCorrectionAnnotations
 * @module bns.main.correction
 *
 * @description
 * Displays annotations for the given correction.
 *
 * ** Attributes **
 *  - `correction`: the Correction object to bind to.
 */
function BnsCorrectionAnnotationsDirective () {

  return {
    scope: {
      correction: '=',
      editable: '=bnsEditable',
    },
    templateUrl: 'views/main/correction/bns-correction-annotations.html',
    controller: 'BnsCorrectionAnnotations',
    controllerAs: 'ctrl',
    bindToController: true,
  };

}

function BnsCorrectionAnnotationsController (_, $scope, $element, $rootScope, $mdUtil, annotationSidebar) {

  var ctrl = this;
  ctrl.activeGuid = null;
  ctrl.formName = 'correction';
  ctrl.$list = $element.closest('md-sidenav');
  ctrl.removeAnnotation = removeAnnotation;

  init();

  function init () {
    var $form = $element.closest('form');
    if ($form.length) {
      ctrl.formName = $form.attr('name') + '[correction]';
    }

    if (!ctrl.editable && ctrl.correction.has_data) {
      annotationSidebar.open();
    }

    var unlistenAddAnnotation = $rootScope.$on('annotation:add', onAddAnnotation);
    var unlistenFocusAnnotation = $rootScope.$on('annotation:focus', onFocusAnnotation);
    var unlistenUnfocusAnnotation = $rootScope.$on('annotation:unfocus', onUnfocusAnnotation);

    $scope.$on('$destroy', function cleanup () {
      unlistenAddAnnotation();
      unlistenFocusAnnotation();
      unlistenUnfocusAnnotation();
    });
  }

  function onAddAnnotation (event, annotation) {
    $mdUtil.nextTick(function () { // wait for next tick, element may be inserter by ng-repeat
      focusAnnotation(annotation);
    });
  }

  function onFocusAnnotation (event, annotation) {
    $mdUtil.nextTick(function () {
      focusAnnotation(annotation);
    });
  }

  function onUnfocusAnnotation (event, scrollTop) {
    unfocusAnnotation(scrollTop);
  }

  function focusAnnotation (annotation) {
    if (!annotation.guid) {
      return;
    }

    ctrl.activeGuid = annotation.guid;

    var targetAnnotation = ctrl.$list.find('[data-bns-annotation-guid="'+annotation.guid+'"]');
    if (!targetAnnotation.length) {
      return;
    }

    return annotationSidebar.open().then(function () {
      ctrl.$list.scrollToElementAnimated(targetAnnotation);
    });
  }

  function unfocusAnnotation () {
    ctrl.activeGuid = null;
  }

  function removeAnnotation (annotation) {
    _.remove(ctrl.correction.correction_annotations, annotation);
    $rootScope.$emit('annotation:remove', {annotation: annotation});
  }

}

})(angular);
