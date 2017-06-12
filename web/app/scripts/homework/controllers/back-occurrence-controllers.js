(function (angular) {
'use strict';

angular.module('bns.homework.back.occurrenceControllers', [
  'bns.homework.homeworks',
])

  .controller('HomeworkBackOccurrenceActionbar', HomeworkBackOccurrenceActionbarController)
  .controller('HomeworkBackOccurrenceContent', HomeworkBackOccurrenceContentController)

;

function HomeworkBackOccurrenceActionbarController ($state, dialog, toast, Homeworks, occurrence) {

  var ctrl = this;
  ctrl.occurrence = occurrence;
  ctrl.deleteOccurrence = deleteOccurrence;
  ctrl.deleteAll = deleteAll;

  function deleteOccurrence () {
    return dialog.confirm({
      title: 'HOMEWORK.TITLE_DELETE_OCCURRENCE',
      content: 'HOMEWORK.DESCRIPTION_DELETE_OCCURRENCE',
      ok: 'HOMEWORK.BUTTON_DELETE_OCCURRENCE',
      cancel: 'HOMEWORK.BUTTON_CANCEL',
      intent: 'warn',
    })
      .then(doDeleteOccurrence)
    ;
  }

  function doDeleteOccurrence () {
    return occurrence.remove()
      .then(function success () {
        toast.success('HOMEWORK.FLASH_OCCURRENCE_DELETE_SUCCESS');
        redirectBack();
      })
      .catch(function error (response) {
        toast.error('HOMEWORK.FLASH_OCCURRENCE_DELETE_ERROR');
        throw response;
      })
    ;
  }

  function deleteAll () {
    return dialog.confirm({
      title: 'HOMEWORK.TITLE_DELETE_HOMEWORK',
      content: 'HOMEWORK.DESCRIPTION_DELETE_HOMEWORK',
      ok: 'HOMEWORK.BUTTON_DELETE_HOMEWORK',
      cancel: 'HOMEWORK.BUTTON_CANCEL',
      intent: 'warn',
    })
      .then(doDeleteAll)
    ;
  }

  function doDeleteAll () {
    return Homeworks.one(occurrence._embedded.homework.id).remove()
      .then(function success () {
        toast.success('HOMEWORK.FLASH_WORK_DELETE_SUCCESS');
        redirectBack();
      })
      .catch(function error (response) {
        toast.error('HOMEWORK.FLASH_WORK_DELETE_ERROR');
        throw response;
      })
    ;
  }

  function redirectBack () {
    $state.go('app.homework.back.week.content');
  }

}

function HomeworkBackOccurrenceContentController (occurrence, preferences) {

  var ctrl = this;
  ctrl.occurrence = occurrence;
  ctrl.preferences = preferences;

}

})(angular);
