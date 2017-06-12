(function (angular) {
'use strict';

angular.module('bns.account.config.states', [
  'ui.router',
  'bns.core.appStateProvider',

  'bns.account.linkController',
  'bns.account.linkParentController',
  'bns.account.changePassword',
])

  .config(AccountStatesConfig)

;

function AccountStatesConfig ($stateProvider, appStateProvider) {

  var navbarWasHidden = false;

  $stateProvider
    .state('app.account', angular.merge(appStateProvider.createRootState('account'), {
      onEnter: ['$rootScope', function ($rootScope) {
        navbarWasHidden = $rootScope.hideDockBar;
        $rootScope.hideDockBar = true;
        angular.element('body').addClass('app-account');
      }],
      onExit: ['$rootScope', function ($rootScope) {
        $rootScope.hideDockBar = navbarWasHidden;
        angular.element('body').removeClass('app-account');
      }]
    }))

    .state('app.account.link', {
      url: '/link',
      templateUrl: 'views/account/link/base.html',
      controller: 'AccountLink',
      controllerAs: 'ctrl',
    })

    .state('app.account.link-parent', {
      url: '/link-parent',
      templateUrl: 'views/account/link/base.html',
      controller: 'AccountLinkParent',
      controllerAs: 'ctrl',
    })

    .state('app.account.changePassword', {
      url: '/password/change',
      templateUrl: 'views/account/change-password.html',
    })
  ;

}

})(angular);
