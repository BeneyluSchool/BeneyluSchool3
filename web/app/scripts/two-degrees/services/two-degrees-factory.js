(function (angular) {
'use strict';

angular.module('bns.twoDegrees.twoDegrees', [
  'restangular',
])


  .factory('TwoDegrees', TwoDegreesFactory)

;

function TwoDegreesFactory (_, Restangular) {

  var TwoDegrees = Restangular.service('two-degrees');

  TwoDegrees.getChallenges = getChallenges;
  TwoDegrees.getChallenge = getChallenge;
  TwoDegrees.getInnovations = getInnovations;
  TwoDegrees.getWords = getWords;
  TwoDegrees.getActivity = getActivity;
  TwoDegrees.resetGame = resetGame;
  TwoDegrees.getTotal = getTotal;

  return TwoDegrees;

  function getChallenges () {
    return TwoDegrees.one('challenges', '').getList();
  }

  function getChallenge (code) {
    return TwoDegrees.one('challenges').one(code).get();
  }

  function getInnovations () {
    return TwoDegrees.one('innovations').getList();
  }

  function getWords () {
    return TwoDegrees.one('words').getList();
  }

  function getActivity (code) {
    return TwoDegrees.one('activities').one(code).get();
  }

  function resetGame () {
    return TwoDegrees.one('reset').post();
  }

  function getTotal () {
    return TwoDegrees.one('participation').one('total').get();
  }

}

})(angular);
