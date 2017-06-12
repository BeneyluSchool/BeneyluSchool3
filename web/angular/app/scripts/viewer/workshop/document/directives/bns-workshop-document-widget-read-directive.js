'use strict';

angular.module('bns.viewer.workshop.document.widgetRead', [
  'bns.core.url',
  'timer'
])

  /**
   * @ngdoc directive
   * @name bns.viewer.workshop.document.widget.bnsWorkshopDocumentWidgetRead
   * @kind function
   *
   * @description
   * Responsible for handling visual appearance of a widget.
   *
   * @example
   * <any bns-workshop-document-widget-read></any>
   *
   * @returns {Object} The bnsWorkshopDocumentWidgetRead directive
   */
  .directive('bnsWorkshopDocumentWidgetRead', function (url) {
    return {
      scope: {
        widget: '=bnsWorkshopDocumentWidgetRead',
      },
      templateUrl: url.view('viewer/workshop/document/directives/bns-workshop-document-widget.html'),
      controller: 'WorkshopDocumentWidgetReadController',
      controllerAs: 'ctrl',
      bindToController: true,
    };
  })

  .controller('WorkshopDocumentWidgetReadController', function ($scope, $element, $attrs, $translate, $window, $timeout, url, workshopThemeStyler, WorkshopRestangular) {
    var ctrl = this;
    var EMPTY_CLASS = 'workshop-widget-empty';

    ctrl.verifyAnswer = verifyAnswer;
    ctrl.resetAnswer = resetAnswer;
    ctrl.focusPage = focusPage;
    ctrl.revealHelp = revealHelp;
    ctrl.startChrono = startChrono;
    ctrl.stopChrono = stopChrono;
    ctrl.stoppedChrono = stoppedChrono;
    ctrl.timeStopped = false;

    $scope.toggle = function (item, list) {
      var idx = list.indexOf(item);
      if (idx > -1) {
        list.splice(idx, 1);
      }
      else {
        list.push(item);
      }
    };

    $scope.exists = function (item, list) {
      if (!list) {
        return false;
      }
      return list.indexOf(item) > -1;
    };

    init();

    initResponse();

    if (ctrl.widget._embedded.extended_settings && ctrl.widget._embedded.extended_settings.advanced_settings && ctrl.widget._embedded.extended_settings.advanced_settings.show_chrono) {
      initChrono();
    }

    function init () {
      $element.addClass('workshop-widget-' + ctrl.widget.type);
      $element.attr('data-placeholder', $translate.instant('WORKSHOP.DOCUMENT.' + ctrl.widget.type.toUpperCase()));
      ctrl.templatePath = url.view('viewer/workshop/document/widget/' + ctrl.widget.type + '.html');

      var questionType = ['simple', 'closed', 'multiple', 'page-break', 'gap-fill-text'];

      if (questionType.indexOf(ctrl.widget.type) != -1) {
        if (!$attrs.hasOwnProperty('bnsWorkshopDocumentWidgetWrite')) {
          if (ctrl.widget.type == 'page-break') {
            ctrl.templatePath = url.view('viewer/workshop/document/widget/' + ctrl.widget.type + '.html');
          } else {
            ctrl.templatePath = url.view('viewer/workshop/document/widget/question-type.html');
            ctrl.templateQuestion = url.view('viewer/workshop/document/widget/' + ctrl.widget.type + '.html');
          }

        } else {
          ctrl.templatePath = url.view('viewer/workshop/document/widget/' + ctrl.widget.type + '-write.html');
          ctrl.templateAdvancedSettings = url.view('viewer/workshop/document/widget/advanced-settings.html');
        }
      }

      $scope.$watchCollection('ctrl.widget.settings', function () {
        refreshStyles();
      });

      $scope.$on('questionnaire.reset', function () {
        initResponse();
      });

      // listen to theme changes
      $scope.$on('theme.changed', function () {
        refreshStyles();
      });

      $scope.$watch(isEmpty, function (empty) {
        if (empty) {
          $element.addClass(EMPTY_CLASS);
        } else {
          $element.removeClass(EMPTY_CLASS);
        }
      });
    }


    function initResponse() {
      ctrl.myResponse = [];
      if (ctrl.widget.type == 'gap-fill-text') {
        ctrl.myResponse = {};
        ctrl.gapRetry = true;
      }
      if (ctrl.widget.type == 'closed') {
        ctrl.myResponse = '';
      }
      ctrl.chronoStarted = false;
      ctrl.canAnswer = true;
      ctrl.correct = false;
      ctrl.attempts = 0;
      ctrl.canRetry = true;
      ctrl.showClue = false;
      ctrl.correctAnswers = false;
    }

    function verifyAnswer() {
      var showSolution = false;
      var data = ctrl.myResponse;
      if (!ctrl.widget._embedded.extended_settings.advanced_settings.hide_solution) {
        showSolution = true;
      }
      WorkshopRestangular.one('questionnaire', ctrl.widget.id).one(ctrl.widget.type).all('verify').post({data: data, show_solution: showSolution})
        .then(function (result) {
          ctrl.isCorrect = result.is_correct;
          ctrl.rightAnswers = result.right_answers;
          ctrl.attempts++;
          ctrl.correctCount = result.correct_count;
          ctrl.total = result.total;
          ctrl.canAnswer = false;
          ctrl.canRetry = false;
          ctrl.gapRetry = false;

          if (ctrl.widget.attempts_number > -1 || ctrl.widget.attempts_number) {
            ctrl.canRetry = true;
            if (ctrl.widget.type == 'gap-fill-text') {
              ctrl.gapRetry = true;
            }
          }

          if (result.is_correct == true) {
            ctrl.resultMessage = 'WORKSHOP.QUESTIONNAIRE.CONGRATS';
            ctrl.canRetry = false;
            ctrl.gapRetry = false;
            stopChrono();
          } else {
            ctrl.resultMessage = 'WORKSHOP.QUESTIONNAIRE.WRONG';
          }

          if (result.correct_answers) {
            ctrl.correctAnswers = result.correct_answers;
          }

          if (ctrl.widget.attempts_number > 0 && ctrl.attempts >= ctrl.widget.attempts_number) {
            ctrl.canRetry = false;
            ctrl.gapRetry = false;
          }

          if (ctrl.widget._embedded.extended_settings &&
            ctrl.widget._embedded.extended_settings.advanced_settings.show_chrono &&
            ctrl.attempts >= 1
          ) {
            stopChrono();
            ctrl.canRetry = false;
            ctrl.gapRetry = false;
          }

        })
      ;
    }

    function revealHelp() {
      ctrl.helpRevealed = true;
    }

    function initChrono() {
      var minutes = ctrl.widget._embedded.extended_settings.advanced_settings.chrono.minutes;
      var seconds = ctrl.widget._embedded.extended_settings.advanced_settings.chrono.seconds;
      ctrl.countdownVal = (minutes * 60) + seconds;
    }

    function startChrono() {
      ctrl.chronoStarted = true;
      $scope.$broadcast('timer-reset');
      $scope.$broadcast('timer-start');
    }

    function stopChrono() {
      $scope.$broadcast('timer-stop');
    }

    function stoppedChrono() {
      if (ctrl.myResponse.length == 0) {
        ctrl.canRetry = false;
      }
      $scope.$apply(function () {
        ctrl.timeStopped = true;
      });
    }

    function resetChrono() {
      $scope.$broadcast('timer-reset');
      $scope.$broadcast('timer-start');
    }

    function focusPage(page) {
      var offset = 0;
      var duration = 0;
      var position =  page.position + 1;

      var target = angular.element($window.document.getElementById('workshop-page-' + position));
      var container = angular.element($window.document.getElementById('workshop-document')).closest('.nano-content');

      if (target.length && container.length) {
        var targetY = target.offset().top - target.parent().offset().top + offset;
        container.scrollTo(0, targetY, duration);
      }
    }

    function resetAnswer() {
      ctrl.timeStopped = false;
      ctrl.myResponse = [];
      if (ctrl.widget.type == 'gap-fill-text') {
        ctrl.myResponse = {};
      }
      if (ctrl.widget.type == 'closed') {
        ctrl.myResponse = '';
      }
      ctrl.resultMessage = '';
      ctrl.canAnswer = true;
      ctrl.correct = false;
    }

    /**
     * Updates inline widget styles
     */
    function refreshStyles () {
      ctrl.widgetStyles = workshopThemeStyler.getStylesForSettings(ctrl.widget.settings, ctrl.widget.type);
    }

    function isEmpty () {
      return !(ctrl.widget.content || ctrl.widget.media_id);
    }

    /**
     * Gets the template name of the current widget
     *
     * @return {String}
     */
    function getTemplateName () {
      // template name is simply the widget's type
      var template = ctrl.widget.type;

      // add subtype if necessary
      if (ctrl.widget.subtype) {
        template += '-' + ctrl.widget.subtype;
      } else if (ctrl.widget._embedded && ctrl.widget._embedded.resource) {
        template += '-' + ctrl.widget._embedded.resource.type_unique_name.toLowerCase();
      }

      return template;
    }

  });
