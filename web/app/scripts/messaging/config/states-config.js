(function (angular) {
'use strict'  ;

angular.module('bns.messaging.config.states', [
  'ui.router',
  'bns.core.appStateProvider',
  'bns.main.navbar',

  'bns.messaging.back.messageControllers',
  'bns.messaging.back.moderationControllers',
  'bns.messaging.front.composeControllers',
  'bns.messaging.front.conversationControllers',
  'bns.messaging.front.conversationsControllers',
  'bns.messaging.front.frontController',
  'bns.messaging.front.messageControllers',
  'bns.messaging.front.messagesControllers',
])

  .config(MessagingStatesConfig)

;

function MessagingStatesConfig ($stateProvider, $urlRouterProvider, appStateProvider) {

  /* ------------------------------------------------------------------------ *\
   *    Defaults and redirects
  \* ------------------------------------------------------------------------ */

  $urlRouterProvider.when('/messaging', '/messaging/inbox');
  $urlRouterProvider.when('/messaging/compose', '/messaging/compose/');

  /* ------------------------------------------------------------------------ *\
   *    States
  \* ------------------------------------------------------------------------ */

  var rootState = appStateProvider.createRootState('messaging');
  var backState = appStateProvider.createBackState();

  $stateProvider
    .state('app.messaging', angular.extend(rootState, {
      resolve: {
        // need user directory and media preview
        legacy: ['legacyApp', function (legacyApp) {
          return legacyApp.load();
        }]
      },
    }))

    // Front
    // ------------------------------

    .state('app.messaging.front', {
      url: '', // default child state
      templateUrl: 'views/messaging/front.html',
      controller: 'MessagingFront',
      controllerAs: 'ctrl',
      onEnter: ['statistic', function (statistic) {
        angular.element('body').attr('data-mode', 'front');
        statistic.visit('MESSAGING');
      }],
      onExit: function () {
        angular.element('body').removeAttr('data-mode');
      },
    })

    .state('app.messaging.front.inbox', {
      url: '/inbox',
      views: {
        actionbar: {
          templateUrl: 'views/messaging/front/inbox-actionbar.html',
          controller: 'MessagingFrontConversationsActionbar',
          controllerAs: 'ctrl',
        },
        content: {
          templateUrl: 'views/messaging/front/inbox-content.html',
          controller: 'MessagingFrontConversationsContent',
          controllerAs: 'ctrl',
        },
      },
    })

    .state('app.messaging.front.sent', {
      url: '/sent',
      views: {
        actionbar: {
          templateUrl: 'views/messaging/front/sent-actionbar.html',
        },
        content: {
          templateUrl: 'views/messaging/front/sent-content.html',
          controller: 'MessagingFrontMessagesContent',
          controllerAs: 'ctrl',
        },
      },
    })

    .state('app.messaging.front.drafts', {
      url: '/drafts',
      views: {
        actionbar: {
          templateUrl: 'views/messaging/front/drafts-actionbar.html',
          controller: 'MessagingFrontMessagesActionbar',
          controllerAs: 'ctrl',
        },
        content: {
          templateUrl: 'views/messaging/front/drafts-content.html',
          controller: 'MessagingFrontMessagesContent',
          controllerAs: 'ctrl',
        },
      },
    })

    .state('app.messaging.front.trash', {
      url: '/trash',
      views: {
        actionbar: {
          templateUrl: 'views/messaging/front/trash-actionbar.html',
          controller: 'MessagingFrontConversationsActionbar',
          controllerAs: 'ctrl',
        },
        content: {
          templateUrl: 'views/messaging/front/trash-content.html',
          controller: 'MessagingFrontConversationsContent',
          controllerAs: 'ctrl',
        },
      },
    })

    .state('app.messaging.front.conversation', {
      url: '/conversation/{id}',
      views: {
        actionbar: {
          templateUrl: 'views/messaging/front/conversation-actionbar.html',
          controller: 'MessagingFrontConversationActionbar',
          controllerAs: 'ctrl',
        },
        content: {
          templateUrl: 'views/messaging/front/conversation-content.html',
          controller: 'MessagingFrontConversationContent',
          controllerAs: 'ctrl',
        }
      }
    })

    .state('app.messaging.front.message', {
      url: '/message/{id}',
      views: {
        actionbar: {
          templateUrl: 'views/messaging/front/message-actionbar.html',
        },
        content: {
          templateUrl: 'views/messaging/front/message-content.html',
          controller: 'MessagingFrontMessageContent',
          controllerAs: 'ctrl',
        }
      }
    })

    .state('app.messaging.front.compose', {
      url: '/compose',
      abstract: true,
      views: {
        actionbar: {
          templateUrl: 'views/messaging/front/compose-actionbar.html',
          controller: 'MessagingFrontComposeActionbar',
          controllerAs: 'ctrl',
        },
        content: {
          templateUrl: 'views/messaging/front/compose-content.html',
          controller: 'MessagingFrontComposeContent',
          controllerAs: 'ctrl',
        },
      },
    })

    // dummy states to support transitions without reload
    .state('app.messaging.front.compose.edit', {
      url: '/:id',
    })
    .state('app.messaging.front.compose.new', {
      url: '/{id}',
      params: {
        id: null, // optional parameter
      },
    })

    // Back
    // ------------------------------

    .state('app.messaging.back', backState)

    .state('app.messaging.back.moderation', {
      url: '', // default back state
      views: {
        sidebar: {
          templateUrl: 'views/messaging/back/moderation-sidebar.html',
          controller: 'MessagingBackModerationSidebar',
          controllerAs: 'ctrl',
        },
        actionbar: {
          templateUrl: 'views/messaging/back/moderation-actionbar.html',
          controller: 'MessagingBackModerationActionbar',
          controllerAs: 'ctrl',
        },
        content: {
          templateUrl: 'views/messaging/back/moderation-content.html',
          controller: 'MessagingBackModerationContent',
          controllerAs: 'ctrl',
        },
      }
    })

    .state('app.messaging.back.message', {
      url: '/message/{id}',
      views: {
        sidebar: {
          templateUrl: 'views/messaging/back/message-sidebar.html',
        },
        actionbar: {
          templateUrl: 'views/messaging/back/message-actionbar.html',
        },
        content: {
          templateUrl: 'views/messaging/back/message-content.html',
          controller: 'MessagingBackMessageContent',
          controllerAs: 'ctrl',
        }
      }
    })
  ;

}

})(angular);
