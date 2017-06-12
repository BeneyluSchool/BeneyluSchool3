(function (angular) {
'use strict';

angular.module('bns.core.futureStates', [
  'ct.ui.router.extras.future',
  'bns.core.legacy',
])

  .config(FutureStatesConfig)

;

function FutureStatesConfig ($futureStateProvider, LEGACY_APP_NAME) {
  // define injectable factory inline: we're in a config block :(
  var lazyLoadState = ['$q', '$ocLazyLoad', 'futureState', LazyLoadStateFactory];

  // register our custom state loader
  $futureStateProvider.stateFactory('lazyLoad', lazyLoadState);

  // register module placeholders with 2 required parameters:
  //  - state: the base state of the module
  //  - url:   the base url
  var futureModules = [
    { state: 'workshop', url: '/workshop' },
    { state: 'mediaLibrary', url: '/media-library' },
    { state: 'userDirectory', url: '/user-directory' },
  ];
  futureModules.forEach(function (module) {
    $futureStateProvider.futureState({
      stateName: module.state,
      url: module.url,
      type: 'lazyLoad',
      app: LEGACY_APP_NAME,
    });
  });

  function LazyLoadStateFactory ($q, $ocLazyLoad, futureState) {
    var deferred = $q.defer();
    $ocLazyLoad.load(futureState.app).then(
      function success () {
        deferred.resolve();
      },
      function error (response) {
        console.error('$ocLazyLoad error', response);
        deferred.reject();
      }
    );

    return deferred.promise;
  }
}

}) (angular);
