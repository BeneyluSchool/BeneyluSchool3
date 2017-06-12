'use strict';

angular

  .module('bns.workshop.document.deletePageModal', [
    'btford.modal',
    'ui.router',
    'bns.core.url',
    'bns.workshop.document.manager',
  ])

  .factory('workshopDocumentDeletePageModal', function (btfModal, url) {
    return btfModal({
      controller: 'WorkshopDocumentDeletePageController',
      controllerAs: 'ctrl',
      templateUrl: url.view('/workshop/document/modals/delete-page-modal.html'),
    });
  })

  .controller('WorkshopDocumentDeletePageController', function ($state, workshopDocumentManager, workshopDocumentDeletePageModal) {
    var ctrl = this;

    ctrl.deletion = {
      confirmed: false,
      error: '',
    };
    ctrl.confirm = confirm;
    ctrl.closeModal = closeModal;
    ctrl.page = workshopDocumentDeletePageModal.page;

    function confirm () {
      ctrl.deletion.error = '';

      if (!ctrl.deletion.confirmed) {
        return false;
      }

      workshopDocumentManager.removePage(ctrl.page)
        .then(function success () {
          ctrl.closeModal();
        })
        .catch(function error (response) {
          ctrl.deletion.error = response;
        })
      ;
    }

    function closeModal () {
      workshopDocumentDeletePageModal.deactivate();
    }
  })

;
