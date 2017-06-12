(function (angular) {
  'use strict'  ;

  angular.module('bns.campaign.config.states', [
      'ui.router',
      'bns.core.appStateProvider',

      'bns.campaign.backController',
      'bns.campaign.back.editController',
      'bns.campaign.back.showController'
    ])

    .config(CampaignStatesConfig)

  ;

  function CampaignStatesConfig ($stateProvider, appStateProvider) {

    /* ------------------------------------------------------------------------ *\
     *    States
     \* ------------------------------------------------------------------------ */

    var rootState = appStateProvider.createRootState('campaign');
    var backState = appStateProvider.createBackState();

    $stateProvider
      .state('app.campaign', rootState)

      // Back
      // ---------------------

      .state('app.campaign.back', angular.merge(backState, {
        resolve: {
          groupId : ['navbar', function (navbar) {
            return navbar.getOrRefreshGroup()
              .then(function (group) {
                return group.id;
              });
          }],
          // need user directory and media preview
          legacy: ['legacyApp', function (legacyApp) {
            return legacyApp.load();
          }]
        },
      }))

      .state('app.campaign.back.list', {
        url: '',
        views: {
          actionbar: {
            templateUrl: 'views/campaign/back/actionbar.html',
            controller: 'CampaignBackActionbar',
            controllerAs: 'ctrl'
          },
          sidebar: {
            templateUrl: 'views/campaign/back/sidebar.html',
            controller: 'CampaignBackSidebar',
            controllerAs: 'ctrl'
          },
          content: {
            templateUrl: 'views/campaign/back/content.html',
            controller: 'CampaignBackContent',
            controllerAs: 'ctrl'
          }
        }
      })

     .state('app.campaign.back.edit', {
        url: '/edit/:id',
        views: {
          actionbar: {
            templateUrl: 'views/campaign/back/actionbar-edit.html',
            controller: 'CampaignBackEditActionbar',
            controllerAs: 'ctrl'
          },
          sidebar: {
            templateUrl: 'views/campaign/back/sidebar-edit.html',
            controller: 'CampaignBackEditSidebar',
            controllerAs: 'ctrl'
          },
          content: {
            templateUrl: 'views/campaign/back/content-edit.html',
            controller: 'CampaignBackEditContent',
            controllerAs: 'ctrl'
          }
        }
      })

      .state('app.campaign.back.show', {
        url: '/show/:id',
        views: {
          actionbar: {
            templateUrl: 'views/campaign/back/actionbar-show.html',
            controller: 'CampaignBackShowActionbar',
            controllerAs: 'ctrl'
          },
          sidebar: {
            templateUrl: 'views/campaign/back/sidebar-show.html',
            controller: 'CampaignBackShowSidebar',
            controllerAs: 'ctrl'
          },
          content: {
            templateUrl: 'views/campaign/back/content-show.html',
            controller: 'CampaignBackShowContent',
            controllerAs: 'ctrl'
          }
        }
      });
  }

})(angular);
