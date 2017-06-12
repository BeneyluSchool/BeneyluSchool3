'use strict';

angular.module('bns.workshop.document.pageWrite', [])

  /**
   * @ngdoc directive
   * @name bns.workshop.document.pageWrite.bnsWorkshopDocumentPageWrite
   * @kind function
   *
   * @description
   * Handles editions to a workshop document page
   *
   * @returns {Object} the bnsWorkshopDocumentPageWrite directive
   */
  .directive('bnsWorkshopDocumentPageWrite', function () {
    return {
      restrict: 'AE',
      require: ['bnsWorkshopDocumentPage', 'bnsWorkshopDocumentPageWrite'],
      link: function (scope, element, attrs, controllers) {
        var pageCtrl = controllers[0];
        var writeCtrl = controllers[1];

        writeCtrl.bind(pageCtrl.page);
      },
      controller: 'WorkshopDocumentPageWriteController',
    };
  })

  .controller('WorkshopDocumentPageWriteController', function ($scope, $element, $compile, WorkshopDocumentState) {
    var ctrl = this;
    ctrl.bind = bind;

    /**
     * Called by directive link, after controller instantiation and dependency
     * resolution.
     *
     * @param {Object} page
     */
    function bind (page) {
      ctrl.page = page;
      $scope.state = WorkshopDocumentState;

      addLayoutChooser();
      addWidgetEditBackdrop();
      $compile($element.contents())($scope);
    }

    function addLayoutChooser () {
      // TODO: make a nice template
      $element.append(
        '<a ng-if="!ctrl.page.layout_code" ' +
          'ui-sref="app.workshop.document.base.layout({ pagePosition: ctrl.page.position })" ' +
          'bns-workshop-document-panel-toggle="true" ' +
          'class="workshop-page-no-layout"> ' +
          '<span class="incentive" translate>WORKSHOP.DOCUMENT.CHOOSE_LAYOUT</span>' +
        '</a>'
      );
    }

    function addWidgetEditBackdrop() {
      $element.append(
        '<div ng-show="state.editedWidgetGroup" ' +
          'ui-sref="app.workshop.document.base.kit" ' +
          'class="workshop-edition-backdrop" ' +
        '></div>'
      );
    }
  })

;
