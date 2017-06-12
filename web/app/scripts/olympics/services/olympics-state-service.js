(function (angular) {
'use strict';

angular.module('bns.olympics.state', [
  'angularLocalStorage',
])

  .service('olympicsState', OlympicsStateService)

;

function OlympicsStateService (storage) {

  var STORAGE_KEY = 'bns/olympics';

  var olympicsState = {
    tennis: get('tennis') || false,
    rowing: get('rowing') || false,
    fencing: get('fencing') || false,
    last: get('last') || null,
    setPlayed: setPlayed,
    setLastPlayed: setLastPlayed,
  };

  return olympicsState;

  function setPlayed (name, value) {
    value = !!value;
    set(name, value);
  }

  function setLastPlayed (value) {
    set('last', value);
  }

  function get (name) {
    return storage.get(STORAGE_KEY+'/'+name);
  }

  function set (name, value) {
    olympicsState[name] = value;
    storage.set(STORAGE_KEY+'/'+name, value);
  }

}

})(angular);
