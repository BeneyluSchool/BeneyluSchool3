'use strict';

angular.module('bns.workshop.config.states', [
  'ui.router',
  'bns.core.url',
  'bns.main.navbar',
])

  .config(function ($stateProvider, URL_BASE_VIEW) {

    /* ---------------------------------------------------------------------- *\
     *    State change handler
    \* ---------------------------------------------------------------------- */

    var onWorkshopEnter = ['navbar', 'workshopAudioAccessRules', function (navbar, workshopAudioAccessRules) {
      angular.element('body').addClass('workshop');
      navbar.setApp('WORKSHOP');
      navbar.mode = 'front';
      workshopAudioAccessRules.enable();
    }];

    var onWorkshopExit = ['workshopAudioAccessRules', function (workshopAudioAccessRules) {
      angular.element('body').removeClass('workshop');
      workshopAudioAccessRules.disable();
    }];

    /* ---------------------------------------------------------------------- *\
     *    States
    \* ---------------------------------------------------------------------- */

    $stateProvider
      .state('app.workshop', {
        url: '/workshop',
        abstract: true,
        templateUrl: URL_BASE_VIEW + '/workshop/base.html',
        onEnter: onWorkshopEnter,
        onExit: onWorkshopExit,
      })

      .state('app.workshop.index', {
        url: '', // default child state
        views: {
          workshop: {
            templateUrl: URL_BASE_VIEW + '/workshop/index.html',
            controller: 'WorkshopIndexController',
            controllerAs: 'ctrl',
          },
        },
      })
    ;

  })
;
