(function (angular) {
'use strict';

angular.module('bns.breakfastTour.mapController', [
  'bns.components.dialog',
  'bns.components.toast',
  'bns.breakfastTour.breakfasts',
])

  .controller('BreakfastTourMapController', BreakfastTourMapController)

;

function BreakfastTourMapController ($state, dialog, toast, Breakfasts) {

  var ctrl = this;
  ctrl.breakfasts = [];
  ctrl.navigate = navigate;
  ctrl.canChallenge = canChallenge;
  ctrl.resetBreakfast = resetBreakfast;

  init();

  function init () {
    ctrl.busy = true;
    Breakfasts.getList()
      .then(function success (breakfasts) {
        ctrl.breakfasts = breakfasts;
      })
      .catch(function error (response) {
        toast.error(response && (response.statusText || response.message));
      })
      .finally(function end () {
        ctrl.busy = false;
      })
    ;
  }

  function navigate (breakfast) {
    if (breakfast.unlocked) {
      $state.go('^.breakfast.details', { code: breakfast.code });
    } else {
      $state.go('^.breakfast.unlock', { code: breakfast.code });
    }
  }

  function canChallenge () {
    if (!(ctrl.breakfasts && ctrl.breakfasts.length)) {
      return false;
    }

    for (var i = 0; i < ctrl.breakfasts.length; i++) {
      if (!ctrl.breakfasts[i].unlocked) {
        return false;
      }
    }

    return true;
  }

  function resetBreakfast() {
    dialog.confirm({
      title: 'BREAKFAST_TOUR.TITLE_CONFIRM_RESTART',
      content: 'BREAKFAST_TOUR.DESCRIPTION_RESTART',
      ok: 'BREAKFAST_TOUR.BUTTON_CONFIRM',
      cancel: 'BREAKFAST_TOUR.BUTTON_CANCEL',
    })
      .then(function confirmed () {
        ctrl.busy = true;
        Breakfasts.one('reset').patch()
          .then(function success (){
            ctrl.breakfasts = [];
            init();
          })
          .catch(function error (response) {
            toast.error(response && (response.statusText || response.message));
          })
          .finally(function end () {
            ctrl.busy = false;
          })
        ;
      })
    ;
  }

}

})(angular);
