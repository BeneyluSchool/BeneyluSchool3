(function (angular) {
'use strict';

angular.module('bns.breakfastTour.challenge', [
  'restangular',
])


  .factory('Challenge', ChallengeFactory)

;

function ChallengeFactory (_, Restangular) {

  var Challenge = Restangular.service('challenge', Restangular.one('breakfast-tour', ''));

  return Challenge;

}

})(angular);
