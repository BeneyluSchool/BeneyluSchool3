(function (angular) {
'use strict';

angular.module('bns.mediaLibrary.viewerInvoker', [])

  .run(MediaLibraryInvokerRun)

;

function MediaLibraryInvokerRun (legacyApp, $ocLazyLoad, $injector) {

  angular.element('body').on('click', '.bns-viewer-invoker', openViewer);

  function openViewer(event) {
    var target = event.target;
    if (!target) {
      return console.warn('no target');
    }
    var mediaId = angular.element(target).attr('data-id');
    if (!mediaId) {
      return console.warn('no media id');
    }

    legacyApp.load()
      .then(function () {
        return $ocLazyLoad.load('mediaLibrary');
      })
      .then(function () {
        return $ocLazyLoad.load('userDirectory');
      })
      .then(function () {
        return $ocLazyLoad.load('workshop');
      })
      .then(function () {
        var bnsViewer = $injector.get('bnsViewer');
        var viewer = bnsViewer();
        viewer.activate({'mediaId' : mediaId});
      })
      .catch(function (e) {
        throw e;
      })
    ;

    event.stopPropagation();
    event.stopImmediatePropagation();
    event.preventDefault();

    return false;
  }

}

})(angular);
