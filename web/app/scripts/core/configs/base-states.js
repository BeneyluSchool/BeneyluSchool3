(function (angular) {
'use strict';

angular.module('bns.core.baseStates', [])

  .config(BaseStatesConfig)

;

function BaseStatesConfig ($windowProvider, $stateProvider) {

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
  ;

  $stateProvider
    // Base state for full-page apps
    .state('app', {
      sticky: true,
      views: {
        'app@': {
          template: '<ui-view class="flex layout-column layout-fill"></ui-view>',
        }
      }
    })
  ;

}

})(angular);
