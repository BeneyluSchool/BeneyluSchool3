(function (angular) {
'use strict';

angular.module('bns.viewer.workshop.questionnaire.pageFooter', [])

  .directive('bnsWorkshopQuestionnairePageFooter', BnsWorkshopQuestionnairePageFooterDirective)
  .controller('BnsWorkshopQuestionnairePageFooter', BnsWorkshopQuestionnairePageFooterController)

;

function BnsWorkshopQuestionnairePageFooterDirective (url) {

  return {
    scope: {
      document: '=bnsDocument',
      page: '=bnsPage',
      questionnaire: '=bnsQuestionnaire',
      participation: '=bnsParticipation',
    },
    controller: 'BnsWorkshopQuestionnairePageFooter',
    controllerAs: 'ctrl',
    bindToController: true,
    templateUrl: url.view('viewer/workshop/questionnaire/directives/bns-workshop-questionnaire-page-footer.html'),
  };

}

function BnsWorkshopQuestionnairePageFooterController ($scope, $log, Restangular, toast) {

  var ctrl = this;
  ctrl.showFinish = false;
  ctrl.next = next;
  ctrl.finish = finish;
  ctrl.reset = reset;
  ctrl.back = back;
  ctrl.like = like;

  init();

  function init () {
    $scope.$watch('ctrl.document', setupDocument);
    $scope.$watch('ctrl.page', updateIsLast);
  }

  function setupDocument (document) {
    if (!document) {
      return;
    }
    ctrl.competition = document._embedded.competition;
    ctrl.book = document._embedded.book;
    if (!(ctrl.competition)) {
      $log.warn('Participation without competition');
    }
  }

  function updateIsLast (page) {
    if (!page && ctrl.document && ctrl.document._embedded.pages) {
      ctrl.isLast = false;
    }
    ctrl.isLast = page.position === ctrl.document._embedded.pages.length;
  }

  function next () {
    return $scope.$emit('questionnaire.page.next', ctrl.page);
  }

  function finish () {
    if (ctrl.participation) {
      return Restangular.one('questionnaire-participation').one('finish', ctrl.questionnaire.id).patch()
        .then(function success (data) {
          ctrl.participation.score = data.score;
          ctrl.showFinish = true;
        })
        .catch(function error (response) {
          toast.error('COMPETITION.FLASH_FINISH_QUESTIONNAIRE_ERROR');
          throw response;
        })
      ;
    } else {
      ctrl.showFinish = true;
    }
  }

  function reset () {
    if (!ctrl.participation) {
      ctrl.showFinish = false;
    }

    return $scope.$emit('questionnaire.reset.click');
  }

  function back () {
    return $scope.$emit('questionnaire.back');
  }

  function like () {
    if (ctrl.participation.like) {
      return;
    }
    return Restangular.one('questionnaire-participation').one('like', ctrl.questionnaire.id).patch()
      .then(function() {
        ctrl.participation.like = true;
      })
    ;
  }

}

})(angular);
