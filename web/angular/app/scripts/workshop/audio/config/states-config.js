'use strict';

angular.module('bns.workshop.audio.config.states', [
  'ui.router',
  'bns.core.url',
  'bns.user.users',
  'bns.workshop.audio.accessRules',
])

  .config(function ($stateProvider, URL_BASE_VIEW) {

    /* ---------------------------------------------------------------------- *\
     *    State change handler
    \* ---------------------------------------------------------------------- */

    var onAudioEnter = ['$rootScope', function ($rootScope) {
      $rootScope.hideDockBar = true;
    }];

    var onAudioExit = ['$rootScope', function ($rootScope) {
      $rootScope.hideDockBar = false;
    }];


    /* ---------------------------------------------------------------------- *\
     *    States
    \* ---------------------------------------------------------------------- */

    $stateProvider
      .state('app.workshop.audio', {
        url: '/audio',
        abstract: true,
        views: {
          topbar: {
            templateUrl: URL_BASE_VIEW + '/workshop/audio/topbar.html',
            controller: 'WorkshopAudioTopbarController',
            controllerAs: 'ctrl',
          },
          workshop: {
            templateUrl: URL_BASE_VIEW + '/workshop/audio/audio.html',
            controller: 'WorkshopAudioMainController',
            controllerAs: 'ctrl',
          },
          'workshop.sidebar@app.workshop.audio': {
            templateUrl: URL_BASE_VIEW + '/workshop/audio/sidebar.html',
            controller: 'WorkshopAudioSidebarController',
            controllerAs: 'ctrl',
          },
          'workshop.panel@app.workshop.audio': {
            templateUrl: URL_BASE_VIEW + '/workshop/audio/panel.html',
          },
        },
        onEnter: onAudioEnter,
        onExit: onAudioExit,
      })
      .state('app.workshop.audio.create', {
        url: '/create',
        views: {
          'workshop.scene': {
            templateUrl: URL_BASE_VIEW + '/workshop/audio/scene.html',
            controller: 'WorkshopAudioSceneController',
            controllerAs: 'ctrl',
          }
        },
      })
      .state('app.workshop.audio.create.index', {
        url: '/index',
        views: {
          'workshop.panel.content@app.workshop.audio': {
            templateUrl: URL_BASE_VIEW + '/workshop/audio/panel/content-general.html',
            controller: 'WorkshopAudioPanelGeneralController',
            controllerAs: 'ctrl',
          }
        },
      })
    ;

  })

;
