(function (angular) {
'use strict'  ;

angular.module('bns.lunch.back.editControllers', [
  'ui.router',
  'angularMoment',
  'bns.lunch.lunchWeek',
])

  .controller('LunchBackEditContentController', LunchBackEditContentController)
  .controller('LunchBackEditActionbarController', LunchBackEditActionbarController)
  .controller('LunchBackEditSidebarController', LunchBackEditSidebarController)
  .factory('lunchWeekEditState', LunchWeekEditStateFactory)

;

function LunchBackEditContentController ($scope, $state, $stateParams, moment, dateUtils, LunchWeek, LunchWeekEditor, lunchWeekEditState) {

  if (!dateUtils.isMonday($stateParams.week)) {
    return $state.go('app.lunch.back.week', { week: dateUtils.getCurrentMonday() });
  }

  var ctrl = this;
  var shared = $scope.shared = lunchWeekEditState;

  init();

  function init () {
    var monday = $stateParams.week;
    shared.editor = new LunchWeekEditor();
    shared.date = moment.utc(monday);

    ctrl.weekForm = shared.editor.getForm();

    LunchWeek.one(monday).get()
      .then(success)
      .catch(error)
    ;

    function success (lunchWeek) {
      shared.editor.setModel(lunchWeek);
    }
    function error (response) {
      if (404 === response.status) {
        shared.editor.setModel({
          date_start: monday,
        });
      }
    }
  }

}

function LunchBackEditActionbarController ($scope, $state, dialog, lunchWeekEditState) {

  var ctrl = this;
  ctrl.remove = remove;

  $scope.shared = lunchWeekEditState;

  function remove ($event) {
    dialog.confirm({
      targetEvent: $event,
      title: 'LUNCH.TITLE_DELETE_MENU',
      content: 'LUNCH.DESCRIPTION_DELETE_MENU',
      ok: 'LUNCH.BUTTON_CONFIRM',
      cancel: 'LUNCH.BUTTON_CANCEL',
      intent: 'warn',
    })
      .then(function confirm () {
        lunchWeekEditState.editor.remove().then(function success () {
          $state.go('^');
        });
      })
    ;
  }
}

function LunchBackEditSidebarController ($scope, lunchWeekEditState) {

  $scope.shared = lunchWeekEditState;

}

function LunchWeekEditStateFactory () {

  return {};

}

})(angular);
