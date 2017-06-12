(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.builders.stepDirectives
 *
 * @description
 * A collection of mini-directives intended to be used as children of the
 * front BuildersBook controller.
 */
angular.module('bns.builders.stepDirectives', [])

  .directive('bnsBuildersStepText', BNSBuildersStepTextDirective)
  .directive('bnsBuildersStepShare', BNSBuildersStepShareDirective)
  .directive('bnsBuildersStepQuizz', BNSBuildersStepQuizzDirective)
  .controller('BNSBuildersStepQuizz', BNSBuildersStepQuizzController)
  .directive('bnsBuildersStepTool', BNSBuildersStepToolDirective)
  .directive('bnsBuildersStepContent', BNSBuildersStepContentDirective)
  .directive('bnsBuildersStepTip', BNSBuildersStepTipDirective)
  .directive('bnsBuildersStepBubble', BNSBuildersStepBubbleDirective)
  .controller('BNSBuildersStepBubble', BNSBuildersStepBubbleController)
  .directive('bnsBuildersStepFinished', BNSBuildersStepFinishedDirective)
  .directive('bnsBuildersStepNext', BNSBuildersStepNextDirective)

;

/**
 * @ngdoc directive
 * @name bnsBuildersStepText
 * @module bns.builders.stepDirectives
 *
 * @description
 * Represents a step textarea form, or its already-submitted value.
 */
function BNSBuildersStepTextDirective () {

  return {
    templateUrl: 'views/builders/directives/bns-builders-step-text.html',
  };

}

/**
 * @ngdoc directive
 * @name bnsBuildersStepShare
 * @module bns.builders.stepDirectives
 *
 * @description
 * Represents a step share form, or its already-submitted value.
 */
function BNSBuildersStepShareDirective () {

  return {
    templateUrl: 'views/builders/directives/bns-builders-step-share.html',
  };

}

function BNSBuildersStepQuizzDirective () {

  return {
    scope: true,
    templateUrl: 'views/builders/directives/bns-builders-step-quizz.html',
    controller: 'BNSBuildersStepQuizz',
    controllerAs: 'quizz',
  };

}

function BNSBuildersStepQuizzController ($scope, $attrs) {

  var quizz = this;
  quizz.choices = [];
  quizz.answer = $attrs.answer;
  quizz.question = 'BUILDERS.QUESTION_QUIZZ_'+$scope.ctrl.book.story+'_'+$scope.ctrl.step;
  quizz.answerText = 'BUILDERS.ANSWER_QUIZZ_'+$scope.ctrl.book.story+'_'+$scope.ctrl.step;

  init();

  function init () {
    angular.forEach(['A', 'B', 'C'], function (value) {
      quizz.choices.push({
        label: 'BUILDERS.LABEL_QUIZZ_'+$scope.ctrl.book.story+'_'+$scope.ctrl.step+'_'+value,
        value: value,
      });
    });
  }

}

function BNSBuildersStepToolDirective () {

  return {
    templateUrl: 'views/builders/directives/bns-builders-step-tool.html',
    scope: true,
    link: postLink,
  };

  function postLink (scope, element, attrs) {
    scope.name = attrs.name;
    scope.url = attrs.url;
    scope.file = attrs.file;
  }

}

function BNSBuildersStepContentDirective () {

  return {
    templateUrl: 'views/builders/directives/bns-builders-step-content.html',
    scope: true,
    link: postLink,
  };

  function postLink (scope, element, attrs) {
    scope.position = attrs.position || 1;
    scope.image = attrs.image;
  }

}

function BNSBuildersStepTipDirective () {

  return {
    templateUrl: 'views/builders/directives/bns-builders-step-tip.html',
    transclude: true,
  };

}

function BNSBuildersStepBubbleDirective () {

  return {
    scope: true,
    templateUrl: 'views/builders/directives/bns-builders-step-bubble.html',
    controller: 'BNSBuildersStepBubble',
    controllerAs: 'bubble',
  };

}

function BNSBuildersStepBubbleController ($attrs) {
  var bubble = this;

  bubble.extra = '';
  if ($attrs.labelNumber) {
    bubble.extra = '_'+$attrs.labelNumber;
  }
}

  function BNSBuildersStepFinishedDirective () {

    return {
      templateUrl: 'views/builders/directives/bns-builders-step-finished.html',
    };

  }

function BNSBuildersStepNextDirective () {

  return {
    templateUrl: 'views/builders/directives/bns-builders-step-next.html',
  };

}


})(angular);
