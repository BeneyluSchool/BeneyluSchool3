(function (angular) {
'use strict';

angular.module('bns.twoDegrees.state', [])

  .factory('twoDegreesState', TwoDegreesStateFactory)

;

function TwoDegreesStateFactory () {

  return {
    challenges: [],
    unreadWords: [],
    unreadInnovations: [],
  };

}

})(angular);
