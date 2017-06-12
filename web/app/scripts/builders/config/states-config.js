(function (angular) {
'use strict';

angular.module('bns.builders.config.states', [
  'ui.router',
  'bns.core.appStateProvider',
  'bns.main.navbar',
  // controllers
  'bns.builders.backBooksControllers',
  'bns.builders.backMessagesControllers',
  'bns.builders.bookController',
  'bns.builders.frontController',
  'bns.builders.frontIntroStory4Controller',
  'bns.builders.storyController',
])

  .config(BuildersStatesConfig)

;

function BuildersStatesConfig ($stateProvider, appStateProvider) {

  /* ------------------------------------------------------------------------ *\
   *    States
  \* ------------------------------------------------------------------------ */

  var rootState = appStateProvider.createRootState('builders');
  var backState = appStateProvider.createBackState();

  $stateProvider
    .state('app.builders', angular.merge(rootState, {
      resolve: {
        hasBack: ['Users', function (Users) {
          return Users.hasCurrentRight('BUILDERS_ACCESS_BACK');
        }],
      },
    }))

    // Front
    // ------------------------------

    .state('app.builders.front', {
      url: '', // default child state
      abstract: true,
      template: '<ui-view class="flex layout-column"></ui-view>',
      controller: 'BuildersFront',
      controllerAs: 'ctrl',
      onEnter: ['navbar', function (navbar) {
        navbar.mode = 'front';
        angular.element('body').attr('data-mode', 'front');
      }],
      onExit: function () {
        angular.element('body').removeAttr('data-mode');
      },
    })

    .state('app.builders.front.home', {
      url: '', // default child state
      templateUrl: 'views/builders/front/home.html',
    })

    .state('app.builders.front.cms', {
      url: '',
      abstract: true,
      templateUrl: 'views/builders/front/cms.html',
    })

    .state('app.builders.front.cms.builders', {
      url: '/builders',
      templateUrl: 'views/builders/front/builders.html',
    })

    .state('app.builders.front.cms.tools', {
      url: '/tools',
      templateUrl: 'views/builders/front/tools.html',
    })

    .state('app.builders.front.cms.characters', {
      url: '/characters',
      templateUrl: 'views/builders/front/characters.html',
    })

    .state('app.builders.front.cms.method', {
      url: '/method',
      templateUrl: 'views/builders/front/method.html',
    })

    .state('app.builders.front.story-4-intro', {
      url: '/story/4/intro',
      templateUrl: 'views/builders/front/story-4-intro.html',
      controller: 'BuildersFrontIntroStory4',
      controllerAs: 'intro',
    })

    .state('app.builders.front.story', {
      url: '/story/{story:[1-4]}',
      templateUrl: 'views/builders/front/story.html',
      controller: 'BuildersStory',
      controllerAs: 'ctrl',
    })

    .state('app.builders.front.book', {
      url: '/book/{id:[0-9]+}',
      templateUrl: 'views/builders/front/book.html',
      controller: 'BuildersBook',
      controllerAs: 'ctrl',
      resolve: {
        book: ['$stateParams', 'Restangular', function ($stateParams, Restangular) {
          return Restangular.all('builders').one('books', $stateParams.id).get();
        }],
      },
    })

    // Back
    // ------------------------------

    .state('app.builders.back', angular.merge(backState, {
      templateUrl: 'views/builders/back.html',
    }))

    .state('app.builders.back.books', {
      url: '', // default child state
      views: {
        sidebar_books: {
          templateUrl: 'views/builders/back/books-sidebar.html',
          controller: 'BuildersBackBooksSidebar',
          controllerAs: 'ctrl',
        },
        content: {
          templateUrl: 'views/builders/back/books-content.html',
          controller: 'BuildersBackBooksContent',
          controllerAs: 'ctrl',
        },
      },
    })

    .state('app.builders.back.messages', {
      url: '/messages',
      views: {
        sidebar_messages: {
          templateUrl: 'views/builders/back/messages-sidebar.html',
          controller: 'BuildersBackMessagesSidebar',
          controllerAs: 'ctrl',
        },
        content: {
          templateUrl: 'views/builders/back/messages-content.html',
          controller: 'BuildersBackMessagesContent',
          controllerAs: 'ctrl',
        },
      },
    })
  ;

}

})(angular);
