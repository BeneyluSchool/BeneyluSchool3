'use strict';

angular.module('bns.workshop.document.panelLayoutController', [
  'bns.workshop.document.layouts',
  'bns.workshop.document.manager',
  'bns.workshop.document.state',
  'bns.core.message',
])

  .controller('WorkshopDocumentPanelLayoutController', function (flash, $scope, $rootScope, message, WorkshopRestangular, workshopDocumentLayouts, workshopDocumentManager, WorkshopDocumentState) {
    var ctrl = this;
    ctrl.changeLayout = changeLayout;
    ctrl.page = WorkshopDocumentState.page;

    init();

    function init () {
      workshopDocumentLayouts.getList().then(function (layouts) {
        ctrl.layouts = layouts;
      });

      workshopDocumentLayouts.getTypesList().then(function (layoutTypes) {
        ctrl.layoutTypes = layoutTypes;
      });
    }

    /**
     * Changes the layout of the current page for the one with given id
     * @param  {Integer} id
     */
    function changeLayout (layout) {
      if (!ctrl.page) {
        message.error('WORKSHOP.DOCUMENT.NO_CURRENT_PAGE');
        return;
      }

      ctrl.busy = true;

      return workshopDocumentManager.editPage(ctrl.page, { layout_code: layout.code })
        .then(success)
        .catch(error)
        .finally(end)
      ;
      function success () {
        message.success('WORKSHOP.DOCUMENT.LAYOUT_CHANGE_SUCCESS');
      }
      function error (response) {
        message.error('WORKSHOP.DOCUMENT.LAYOUT_CHANGE_ERROR');
        console.error('[PATCH page]', response);
      }
      function end () {
        ctrl.busy = false;
      }
    }
  });
