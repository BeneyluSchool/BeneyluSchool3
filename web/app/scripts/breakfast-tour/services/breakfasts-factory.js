(function (angular) {
'use strict';

angular.module('bns.breakfastTour.breakfasts', [
  'restangular',
])


  .factory('Breakfasts', BreakfastsFactory)

;

function BreakfastsFactory (_, Restangular) {

  var Breakfasts = Restangular.service('breakfasts', Restangular.one('breakfast-tour', ''));

  Breakfasts.findOne = findOne;
  Breakfasts.unlock = unlock;

  return Breakfasts;

  function findOne (code) {
    return Breakfasts.getList()
      .then(function success (breakfasts) {
        var breakfast = _.find(breakfasts, {code: code});
        if (breakfast) {
          return breakfast;
        }

        throw 'Breakfast not found';
      })
    ;
  }

  function unlock (code, choices) {
    return Breakfasts.one(code).post('unlock', {
      choices: choices,
    });
  }

}

})(angular);
