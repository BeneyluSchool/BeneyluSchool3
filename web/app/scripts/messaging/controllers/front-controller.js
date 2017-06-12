(function (angular) {
'use strict';

angular.module('bns.messaging.front.frontController', [
  'bns.messaging.counters',
])

  .controller('MessagingFront', MessagingFrontController)

;

function MessagingFrontController ($scope, $rootScope, $state, $mdUtil, MessagingCounters) {

  var ctrl = this;
  ctrl.counters = {};
  ctrl.refreshCounters = $mdUtil.throttle(getCounters, 500);
  ctrl.isState = isState;

  init();

  function init() {
    ctrl.refreshCounters();

    var cleanup = $rootScope.$on('messaging.counters.refresh', function () {
      ctrl.refreshCounters();
    });

    $scope.$on('$destroy', cleanup);
  }

  function getCounters () {
    return MessagingCounters.one('').get()
      .then(function success (counters) {
        ctrl.counters = counters;
      })
    ;
  }

  function isState (stateName) {
    return $state.current && $state.current.name === stateName;
  }

}

})(angular);
