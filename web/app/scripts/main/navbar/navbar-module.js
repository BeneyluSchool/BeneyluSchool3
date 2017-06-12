(function (angular) {
'use strict';

var NAVBAR = {
  MODES: ['front', 'back'],
  MODE_FRONT: 'front',
  MODE_BACK: 'back',
  DEFAULT_MODE: 'front',
  USER_APPS_ORDER: ['PROFILE', 'MESSAGING', 'MEDIA_LIBRARY', 'USER_DIRECTORY', 'DIRECTORY', 'NOTIFICATION'],
};

angular.module('bns.main.navbar', [
  'bns.components.dialog',
  'bns.core.parameters',
  'bns.main.beta',
  'bns.user.users',
  'bns.user.groups',
])

  .constant('NAVBAR', NAVBAR)
  .config(RestangularConfig)

;

function RestangularConfig (RestangularProvider, NAVBAR) {

  RestangularProvider.addResponseInterceptor(interceptAndSortUserApps);

  function interceptAndSortUserApps (data, operation, what, url) {
    if (/users\/me\/applications$/.test(url)) {
      data.sort(appsSort);
    }

    return data;
  }

  function appsSort (a, b) {
    var idxA = NAVBAR.USER_APPS_ORDER.indexOf(a.unique_name);
    if (idxA === -1) {
      idxA = 8000;
    }

    var idxB = NAVBAR.USER_APPS_ORDER.indexOf(b.unique_name);
    if (idxB === -1) {
      idxB = 8000;
    }

    return idxA - idxB;
  }

}

}) (angular);
