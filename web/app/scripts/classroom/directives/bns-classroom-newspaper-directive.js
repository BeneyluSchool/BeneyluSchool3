(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.classroom.newspaper
 */
angular.module('bns.classroom.newspaper', [])

  .directive('bnsClassroomNewspaper', BnsClassroomNewspaperDirective)
  .controller('BnsClassroomNewspaper', BnsClassroomNewspaperController)
  .controller('BnsClassroomNewspaperDialog', BnsClassroomNewspaperDialogController)

;

/**
 * @ngdoc directive
 * @name bnsClassroomNewspaper
 * @module bns.classroom.newspaper
 *
 * @description
 * Fetches and displays today's newspaper in the classroom.
 */
function BnsClassroomNewspaperDirective () {

  return {
    restrict: 'E',
    scope: {},
    controller: 'BnsClassroomNewspaper',
    controllerAs: 'ctrl',
    bindToController: true,
    template:
    '<md-button md-no-ink ng-if="::ctrl.newspaper" class="classroom-newspaper-opener animate-wiggle" ng-click="ctrl.openNewspaper($event)" bns-ga-event="{category: \'Newspaper\', action: \'open\', label: ctrl.newspaper.title}">' +
      '<md-tooltip>{{::\'CLASSROOM.TITLE_NEWSPAPER\'|translate}}</md-tooltip>' +
    '</md-button>',
  };

}

function BnsClassroomNewspaperController (Routing, Restangular, $window, $http, bnsGa, dialog, legacyApp) {

  var ctrl = this;
  ctrl.openNewspaper = openNewspaper;

  init();

  function init () {
    return Restangular.one('classroom/newspaper', 'today').get()
      .then(function success (newspaper) {
        ctrl.newspaper = newspaper;
      })
    ;
  }

  function openNewspaper ($event) {
    return dialog.show({
      templateUrl: 'views/classroom/newspaper-dialog.html',
      controller: 'BnsClassroomNewspaperDialog',
      controllerAs: 'ctrl',
      locals: {
        newspaper: ctrl.newspaper,
      },
      resolve: {
        // legacy app needed for media viewer
        legacy: function () {
          return legacyApp.load();
        },
      },
      targetEvent: $event,
      onComplete: function () {
        bnsGa.trackPageview($window.location.pathname + '#/journal');
        $http({
          url: Routing.generate('BNSAppClassroomBundle_front_newspaper_count', {
            id: ctrl.newspaper.id,
          }),
        });
      },
    });
  }

}

function BnsClassroomNewspaperDialogController (moment, $scope, dialog) {

  var ctrl = this;
  ctrl.hide = dialog.hide;
  ctrl.openMedia = openMedia;
  $scope.moment = moment;

  function openMedia (event) {
    return dialog.show({
      templateUrl: 'views/classroom/newspaper-media-dialog.html',
      locals: {
        newspaper: ctrl.newspaper,
      },
      targetEvent: event,
      multiple: true,
      hasBackdrop: false, // backdrop managed by custom dialog container
      onShowing: function (scope, element) {
        element.addClass('classroom-newspaper-media-dialog-container');
      },
      onRemoving: function (element) {
        element.removeClass('classroom-newspaper-media-dialog-container');
      },
    });
  }

}

})(angular);
