(function (angular) {
'use strict';

angular.module('bns.embed.config.states', [
  'ui.router',
  'bns.core.appStateProvider',
  'bns.user.users',

  'bns.embed.baseController',
  'bns.embed.embeds',

  'bns.embed.tour.controllers',
])

  .config(EmbedConfig)

;

function EmbedConfig ($stateProvider, appStateProvider) {

  var rootState = appStateProvider.createRootState('embed');

  $stateProvider
    .state('app.embed', rootState)

    .state('app.embed.base', {
      url: '/:item?utm_campaign&utm_medium&utm_source&utm_content',
      templateUrl: function ($stateParams) {
        // dynamic template based on item name, defaults to 'base'
        var item = 'base';
        switch ($stateParams.item) {
          case 'tour':
            item = $stateParams.item;
            break;
        }

        return 'views/embed/' + item + '.html';
      },
      controllerProvider: ['$stateParams', function ($stateParams) {
        // dynamic controller based on item name, defaults to 'EmbedBase'
        switch ($stateParams.item) {
          case 'tour':
            return 'EmbedTour';
        }

        return 'EmbedBase';
      }],
      controllerAs: 'ctrl',
      resolve: {
        item: ['$stateParams', 'Embeds', function ($stateParams, Embeds) {
          return Embeds.one($stateParams.item).get();
        }],
      },
      onEnter: ['$rootScope', '$translate', 'navbar', 'item', function ($rootScope, $translate, navbar, item) {
        var tokenItem = item.name.toUpperCase().replace('-', '_');
        navbar.setApp(tokenItem);
        angular.element('body').attr('data-embed', item.name);
        $translate(tokenItem + '.TITLE_META').then(function (title) {
          $rootScope.title = title;
        });
      }],
      onExit: ['$rootScope', function ($rootScope) {
        angular.element('body').removeAttr('data-embed');
        $rootScope.title = '';
      }],
    })
  ;

}

})(angular);
