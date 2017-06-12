(function (angular) {
'use strict';

angular.module('bns.twoDegrees.innovationsControllers', [
  'bns.twoDegrees.state',
])

  .controller('TwoDegreesInnovations', TwoDegreesInnovationsController)
  .controller('TwoDegreesInnovation', TwoDegreesInnovationController)

;

function TwoDegreesInnovationsController (_, $state, innovations) {

  var ctrl = this;
  ctrl.innovations = innovations;
  ctrl.possibleInnovations = [
    'ELECTRIC_CAR',
    'SMART_TRASH',
    'FLYING_WIND_TURBINE',
    'HYDROPONICS',
    'SOLAR_HOUSE',
    'RENEWABLE_WEATHER_INDICATORS',
  ];
  ctrl.getInnovation = getInnovation;

  init();

  function init () {
    // redirect to first available innovation
    if ($state.current.name.indexOf('detail') === -1) {
      var firstInnovation = _.first(ctrl.innovations);
      if (firstInnovation) {
        $state.go('.detail', {code: firstInnovation.code});
      }
    }
  }

  function getInnovation (code) {
    return _.find(ctrl.innovations, {code: code});
  }

}

function TwoDegreesInnovationController (arrayUtils, twoDegreesState, innovation) {

  var ctrl = this;
  ctrl.innovation = innovation;

  init();

  function init () {
    // remove innovation from list of unread
    arrayUtils.remove(twoDegreesState.unreadInnovations, innovation.code);
  }

}

})(angular);
