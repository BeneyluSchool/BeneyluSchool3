(function (angular) {
'use strict';

angular.module('bns.core.baseStates', [])

  .constant('BASE_STATE_URL', '') // TODO: ng5 /app
  .config(BaseStatesConfig)
  .config(IgnoreEmptyHashLinks)

;

function BaseStatesConfig ($windowProvider, $stateProvider, $locationProvider, BASE_STATE_URL, $uiRouterProvider) {

  // TODO: ng5 reenable this
  // $locationProvider.html5Mode({
  //   enabled: true,
  //   requireBase: true,
  //   rewriteLinks: false
  // });

  // TODO: ng5 remove this
  var StickyStatesPlugin = $windowProvider.$get()['@uirouter/sticky-states'].StickyStatesPlugin;
  $uiRouterProvider.plugin(StickyStatesPlugin);

  function redirect (route, params) {
    // quick n'ugly way to get access to external libs in config
    var $window = $windowProvider.$get();
    $window.location = $window.Routing.generate(route, params || {});
  }

  $stateProvider
    // State without template => current view will not be altered.
    // Serves only to redirect to a non-angular page.
    .state('classroom', {
      onEnter: function () {
        redirect('BNSAppClassroomBundle_front');
      }
    })

    // Base state for full-page apps
    .state('app', {
      // url: BASE_STATE_URL, // TODO: ng5 add ?embed as optional parameter
      sticky: true,
      views: {
        'app@': {
          // TODO: ng5 reenable template
          // template: '<ui-view id="app-view" class="flex layout-column layout-fill" ng-controller="AppController as app"></ui-view><bns-navbar ng-if="::!(isEmbed || anonymous)" ng-show="!hideDockBar"></bns-navbar>',
          template: '<ui-view class="flex layout-column layout-fill" du-scroll-container></ui-view>',
        }
      },
    })
  ;

}

function IgnoreEmptyHashLinks () {

  angular.element('body').on('click', 'a', function preventEmptyHashNav(e) {
    // relic of old router: in hash mode, empty hashes were ignored.
    if ('#' === angular.element(this).attr('href')) {
      console.warn('Prevented navigation to empty hash');
      e.preventDefault();
    }
  });

}

})(angular);
