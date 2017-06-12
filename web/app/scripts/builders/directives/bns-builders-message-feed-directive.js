(function (angular) {
'use strict';

angular.module('bns.builders.messageFeed', [])

  .directive('bnsBuildersMessageFeed', BNSBuildersMessageFeedDirective)
  .controller('BNSBuildersMessageFeed', BNSBuildersMessageFeedController)

;

function BNSBuildersMessageFeedDirective () {

  return {
    templateUrl: 'views/builders/directives/bns-builders-message-feed.html',
    controller: 'BNSBuildersMessageFeed',
    controllerAs: 'ctrl',
  };

}

function BNSBuildersMessageFeedController ($scope, $interval, Restangular) {

  var INTERVAL = 60000; // ms
  var ctrl = this;
  ctrl.busy = false;

  init();

  function init () {
    updateMessage();
    ctrl.interval = $interval(updateMessage, INTERVAL);

    $scope.$on('$destroy', function cleanup () {
      $interval.cancel(ctrl.interval);
    });
  }

  function updateMessage () {
    ctrl.busy = true;
    var data = {};
    if (ctrl.message) {
      data.last_id = ctrl.message.id;
    }

    return Restangular.all('builders').all('messages').one('random').get(data)
      .then(function success (message) {
        if (message && message.id) {
          ctrl.message = message;
        } else {
          // no content in API response => a plain Restangular object is
          // returned
          ctrl.message = null;
        }
      })
      .catch(function error () {
        ctrl.message = null;
      })
      .finally(function end () {
        ctrl.busy = false;
      })
    ;
  }

}

})(angular);
