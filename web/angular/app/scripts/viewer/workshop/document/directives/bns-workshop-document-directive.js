'use strict';

angular.module('bns.viewer.workshop.document.directive', [
  'bns.core.url',
])

  /**
   * @ngdoc directive
   * @name bns.viewer.workshop.document.bnsWorkshopDocument
   * @kind function
   *
   * @description
   *
   * @example
   * <any bns-workshop-document="myDocument"></any>
   *
   * @returns {Object} The bnsWorkshopDocument directive
   */
  .directive('bnsWorkshopDocument', function (url) {
    return {
      scope: {
        document: '=bnsWorkshopDocument',
        questionnaire: '=bnsQuestionnaire',
        hideNav: '=',
        onePage: '=',
        print: '=',
      },
      templateUrl: url.view('viewer/workshop/document/directives/bns-workshop-document.html'),
      controller: 'WorkshopDocumentController',
      controllerAs: 'ctrl',
      bindToController: true,
    };
  })

  .controller('WorkshopDocumentController', function (_, $window, $timeout, $scope, workshopThemeStyler) {
    var ctrl = this;
    ctrl.progress = 0;
    ctrl.position = 1;

    $timeout(function () {
      $window.status = 'done';
    }, 2000);

    $scope.$on('questionnaire.reset.click', function () {
      ctrl.progress = 0;
      $scope.$broadcast('questionnaire.reset');
      focusPage(1);
    });

    $scope.$on('wavesurfer.play', function (event, item) {
      if (item) {
        $scope.$broadcast('wavesurfer.stopOthers', item);
      }
    });

    $scope.$on('questionnaire.answered', function (event) {
      event.stopPropagation();
      ctrl.progress += 1;
    });

    $scope.$on('workshop.page.focus', function (event, position, duration, offset) {
      focusPage(position, duration, offset);
    });

    $scope.$on('questionnaire.page.next', function (event, page) {
      if (!ctrl.questionnaire) {
        return;
      }
      var targetPosition = page.position + 1;
      var targetPage = _.find(ctrl.document._embedded.pages, {position: targetPosition});
      if (targetPage) {
        focusPage(targetPosition);
      }
    });

    workshopThemeStyler.setTheme(ctrl.document._embedded.theme);

    function focusPage (position, duration, offset) {
      ctrl.position = position;
      $timeout(function () {
        scrollToPage(position, duration, offset);
      }, 10);
    }

    function scrollToPage(position, duration, offset) {
      duration = duration || 0;
      offset = offset || 0;
      var target = angular.element($window.document.getElementById('workshop-page-' + position));
      var container = angular.element($window.document.getElementById('workshop-document')).closest('.nano-content');

      if (target.length && container.length) {
        // get the element's position relative to its parent (should stay
        // constant...) and apply offset
        var targetY = target.offset().top - target.parent().offset().top + offset;
        container.scrollTo(0, targetY, duration);
      }
    }
  });
