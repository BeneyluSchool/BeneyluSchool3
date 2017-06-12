'use strict';

angular.module('bns.workshop.document.panelGeneralController', [
  'bns.core.message',
  'bns.workshop.document.state',
  'bns.workshop.document.editor',
])

.controller('WorkshopDocumentPanelGeneralController', function ($rootScope, $scope, message, me, WorkshopDocumentState, workshopDocumentEditor) {
  var ctrl = this;

  ctrl.document = WorkshopDocumentState.document;
  ctrl.submit = submit;
  ctrl.cancel = cancel;
  ctrl.busy = false;
  ctrl.adminSettings = false;
  ctrl.contributors = {
    userIds: [],
    groupIds: []
  };

  init();

  function init () {
    workshopDocumentEditor.init(ctrl.document);

    // admin settings
    if (me.rights.workshop_document_manage_lock) {
      ctrl.adminSettings = true;
    }

    if (me.rights.school_competition_manage) {
      ctrl.schoolCompetitionManage = true;
      if (ctrl.document.attempts_number != -1) {
        ctrl.limited_attempts = true;
      }
    }

    var unregisterDocumentUpdated = $rootScope.$on('workshop.document.updated', function () {
      workshopDocumentEditor.refreshSource();
    });

    $scope.$on('$destroy', function () {
      workshopDocumentEditor.rollback();
      unregisterDocumentUpdated();
    });
  }

  /**
   * Submits the document form
   */
  function submit () {
    ctrl.busy = true;

    if (ctrl.schoolCompetitionManage && !ctrl.limited_attempts) {
      ctrl.document.attempts_number = -1;
    }

    return workshopDocumentEditor.commit()
      .then(success)
      .catch(error)
      .finally(end)
    ;
    function success () {
      message.success('WORKSHOP.DOCUMENT.UPDATE_SUCCESS');
    }
    function error (response) {
      message.error('WORKSHOP.DOCUMENT.UPDATE_ERROR');
      console.error('[PATCH document]', response);
    }
    function end () {
      ctrl.busy = false;
    }
  }

  /**
   * Cancel all changes to the local data
   */
  function cancel () {
    workshopDocumentEditor.rollback();
  }
});
