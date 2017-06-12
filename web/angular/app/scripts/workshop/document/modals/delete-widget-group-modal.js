'use strict';

angular

  .module('bns.workshop.document.deleteWidgetGroupModal', [
    'btford.modal',
    'bns.core.url',
    'bns.workshop.document.manager',
  ])

  .factory('workshopDocumentDeleteWidgetGroupModal', function (btfModal, url) {
    return btfModal({
      controller: 'WorkshopDocumentDeleteWidgetGroupController',
      controllerAs: 'ctrl',
      templateUrl: url.view('/workshop/document/modals/delete-widget-group-modal.html'),
    });
  })

  .controller('WorkshopDocumentDeleteWidgetGroupController', function (workshopDocumentManager, workshopDocumentDeleteWidgetGroupModal) {
    var ctrl = this;

    ctrl.deletion = {
      confirmed: false,
      error: '',
    };
    ctrl.confirm = confirm;
    ctrl.closeModal = closeModal;
    ctrl.widgetGroup = workshopDocumentDeleteWidgetGroupModal.widgetGroup;

    function confirm () {
      ctrl.deletion.error = '';

      if (!ctrl.deletion.confirmed) {
        return false;
      }

      workshopDocumentManager.removeWidgetGroup(ctrl.widgetGroup)
        .then(function success () {
          ctrl.closeModal();
        })
        .catch(function error (response) {
          ctrl.deletion.error = response;
        })
      ;
    }

    function closeModal () {
      workshopDocumentDeleteWidgetGroupModal.deactivate();
    }
  })

;
