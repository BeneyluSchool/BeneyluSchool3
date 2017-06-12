(function (angular) {
'use strict';

angular.module('bns.builders.bookController', [
  'bns.builders.stepDirectives',
])

  .controller('BuildersBook', BuildersBookController)

;

function BuildersBookController (toast, book) {

  var ctrl = this;
  ctrl.book = book;           // resolved by state
  ctrl.step = 1;              // current step
  ctrl.prev = prev;
  ctrl.next = next;
  ctrl.goTo = goTo;
  ctrl.answers = {};          // answers by step
  ctrl.submitAnswer = submitAnswer;
  ctrl.submitMessage = submitMessage;
  ctrl.updateUnfinishedSteps = updateUnfinishedSteps;
  ctrl.busy = false;
  ctrl.unfinishedSteps = [];
  ctrl.setupEdit = setupEdit;
  ctrl.cancelEdit = cancelEdit;

  getStepTemplate();

  function prev () {
    ctrl.cancelEdit();
    ctrl.step = Math.max(ctrl.step - 1, 1);
    getStepTemplate();
  }

  function next () {
    ctrl.cancelEdit();
    ctrl.step = Math.min(ctrl.step + 1, 5);
    getStepTemplate();
  }

  function goTo (step) {
    if (step > 0 && step < 5) {
      ctrl.cancelEdit();
      ctrl.step = step;
      getStepTemplate();
    }
  }

  function getStepTemplate () {
    if(ctrl.step < 5) {
      ctrl.template = 'views/builders/front/step/'+ctrl.book.story+'-'+ctrl.step+'.html';
      ctrl.asideTemplate = 'views/builders/front/aside/'+ctrl.book.story+'-'+ctrl.step+'.html';
    } else {
      ctrl.template = 'views/builders/front/step/share.html';
      ctrl.asideTemplate = null;
    }
    ctrl.updateUnfinishedSteps();
  }

  function updateUnfinishedSteps () {
    ctrl.unfinishedSteps = [];
    for (var i=1; i <= 4; i++) {
      if (!ctrl.book.answers[i]) {
        ctrl.unfinishedSteps.push(i);
      }
    }
  }

  function submitAnswer (step) {
    if (ctrl.busy) {
      return;
    }

    ctrl.editing = false;
    ctrl.busy = true;
    var data = {
      answers: {},
    };
    data.answers[step] = ctrl.answers[step];

    return ctrl.book.patch(data)
      .then(function success (book) {
        toast.success('BUILDERS.FLASH_SAVE_ANSWER_SUCCESS');
        angular.merge(ctrl.book, book);
      })
      .catch(function error (response) {
        toast.error('BUILDERS.FLASH_SAVE_ANSWER_ERROR');
        throw response;
      })
      .finally(function end () {
        ctrl.busy = false;
        ctrl.updateUnfinishedSteps();
      })
    ;
  }

  function setupEdit () {
    if (ctrl.book.answers && ctrl.book.answers[ctrl.step]) {
      ctrl.answers[ctrl.step] = ctrl.book.answers[ctrl.step];
      ctrl.editing = true;
    }
  }

  function cancelEdit () {
    ctrl.editing = false;
  }

  function submitMessage () {
    if (ctrl.busy) {
      return;
    }

    ctrl.busy = true;

    return ctrl.book.all('messages').post({ content: ctrl.message })
      .then(function success (message) {
        ctrl.book._embedded.message = message;
        toast.success('BUILDERS.FLASH_SAVE_MESSAGE_SUCCESS');
      })
      .catch(function error (response) {
        toast.error('BUILDERS.FLASH_SAVE_MESSAGE_ERROR');
        throw response;
      })
      .finally(function end () {
        ctrl.busy = false;
      })
    ;
  }

}

})(angular);
