(function (angular) {
'use strict'  ;

angular.module('bns.minisite.config.states', [
  'bns.core.appStateProvider',
  'ui.router',
  'bns.main.navbar',
  'bns.minisite.front.baseControllers',
  'bns.minisite.front.pageControllers',
])

  .config(MinisiteStatesConfig)

;

function MinisiteStatesConfig ($stateProvider, $urlRouterProvider, appStateProvider) {

  // redirect from the root state to the empty front state
  // TODO: ng5 add /app
  $urlRouterProvider.when('/minisite', '/minisite/');

  var rootState = appStateProvider.createRootState('minisite');

  $stateProvider
    .state('app.minisite', rootState)

    .state('app.minisite.empty', {
      url: '/',
      resolve: {
        loadMinisite: ['$stateParams', '$state', 'Restangular', 'navbar', function ($stateParams, $state, Restangular, navbar) {
          return navbar.getOrRefreshGroup()
            .then(function (group) {
              return Restangular.one('groups/' + group.id + '/minisite')
                .get()
                .then(function (result) {
                  if (result.minisite && result.minisite.slug) {
                    return $state.go('app.minisite.front', {slug: result.minisite.slug});
                  }
                  throw 'Nope';
                })
                .catch(function error (response) {
                  console.log(response.status);
                });
            });
        }]
      },
      resolvePolicy: { when: 'EAGER' },
    })

    .state('app.minisite.front', {
      url: '/{slug}',
      templateUrl: 'views/minisite/front.html',
      controller: 'MinisiteFrontBase',
      controllerAs: 'ctrl',
      onEnter: ['statistic', function (statistic) {
        angular.element('body').attr('data-mode', 'front');
        angular.element('html').css('overflow-y', 'scroll');
        statistic.visit('MINISITE');
      }],
      onExit: function () {
        angular.element('body').removeAttr('data-mode');
        angular.element('html').css('overflow-y', '');
      },
    })

    .state('app.minisite.front.page', {
      url: '/{page_slug}',
      views: {
        'content': {
          templateUrl: 'views/minisite/front/content.html',
          controller: 'MinisiteFrontPageContentController',
          controllerAs: 'ctrl',
        }
      },
      onEnter: function () {
        angular.element('body').scrollTop(0);
      },
    });
}

})(angular);
