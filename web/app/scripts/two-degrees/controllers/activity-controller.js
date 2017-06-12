(function (angular) {
'use strict';

angular.module('bns.twoDegrees.activityController', [])

  .controller('TwoDegreesActivity', TwoDegreesActivityController)

;

function TwoDegreesActivityController (activity) {

  var ctrl = this;
  ctrl.activity = activity;

}

})(angular);
