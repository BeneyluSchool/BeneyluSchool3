(function (angular) {
'use strict';

angular.module('bns.twoDegrees.solveController', [
  'bns.components.dialog',
  'bns.components.toast',
  'bns.twoDegrees.state',
])

  .controller('TwoDegreesSolve', TwoDegreesSolveController)

;

function TwoDegreesSolveController ($state, arrayUtils, dialog, toast, twoDegreesState, challenge) {

  var ctrl = this;
  ctrl.challenge = challenge;
  ctrl.submit = submit;
  ctrl.busy = false;

  init();

  function init () {
    showDialog('presentation');
  }

  function submit () {
    if (!ctrl.answer || ctrl.form.$invalid || ctrl.busy) {
      return;
    }

    ctrl.busy = true;

    return challenge.one('solve').post('', {answer: ctrl.answer})
      .then(function success (response) {
        if (response.words) {
          arrayUtils.merge(twoDegreesState.unreadWords, response.words);
        }
        if (response.innovations) {
          arrayUtils.merge(twoDegreesState.unreadInnovations, response.innovations);
        }
        showSuccessDialog();
      })
      .catch(function error (response) {
        if (406 === response.status) {
          toast.error({content: 'TWO_DEGREES.FLASH_WRONG_PASSCODE', hideDelay: 2000});
        } else {
          toast.error('TWO_DEGREES.FLASH_ANSWER_ERROR');
          throw response;
        }
      })
      .finally(function end () {
        ctrl.busy = false;
      })
    ;
  }

  function showDialog (type) {
    return dialog.custom({
      templateUrl: 'views/two-degrees/challenge/'+type+'-dialog.html',
      locals: {
        challenge: challenge,
        close: dialog.hide,
      },
      controller: function dummyCtrl () {},
      controllerAs: 'dialog',
      bindToController: true,
      clickOutsideToClose: true,
    });
  }

  function showSuccessDialog () {
    return showDialog('success')
      .then(function resolve () {
        $state.go('app.twoDegrees.innovations.detail', {code: challenge.innovation});
      })
      .catch(function reject () {
        $state.go('app.twoDegrees.map');
      })
    ;
  }

}

})(angular);
