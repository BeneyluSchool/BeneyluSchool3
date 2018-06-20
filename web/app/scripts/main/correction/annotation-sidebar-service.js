(function (angular) {
'use strict';

angular.module('bns.main.correction')

  .service('annotationSidebar', AnnotationSidebarService)

;

function AnnotationSidebarService ($rootScope, $mdSidenav, storage, sidebar) {

  var COMPONENT_NAME = 'annotations';
  var BASE_STORAGE_KEY = 'bns/correction/sidebar';
  var persistState = false;

  var appSidenavWasLockedOpen = sidebar.getIsLockedOpen();
  var annotationSidebar = {
    isOpen: persistState ? get('open') : false,
    open: open,
    close: close,
    toggle: toggle,
  };

  var sidebarInstance = $mdSidenav(COMPONENT_NAME, true);
  if (sidebarInstance.then) {
    sidebarInstance.then(init);
  } else {
    init();
  }

  return annotationSidebar;

  function init () {
    if (annotationSidebar.isOpen) {
      annotationSidebar.open();
    }
  }

  function open () {
    appSidenavWasLockedOpen = sidebar.getIsLockedOpen();
    // make main sidebar collapse, and persist its closed state
    sidebar.canLockOpen = false;
    if (persistState) {
      sidebar.close();
    }

    var sidenav = $mdSidenav(COMPONENT_NAME, true);
    if (sidenav.then) {
      return sidenav.then(doOpen);
    } else {
      return doOpen();
    }

    function doOpen () {
      $rootScope.$emit('annotation:open');

      return $mdSidenav(COMPONENT_NAME).open().then(function success () {
        annotationSidebar.isOpen = true;
        if (persistState) {
          set('open', true);
        }
      });
    }
  }

  function close () {
    sidebar.canLockOpen = true;
    if (appSidenavWasLockedOpen) {
      sidebar.open();
    }

    var sidenav = $mdSidenav(COMPONENT_NAME, true);
    if (sidenav.then) {
      return sidenav.then(doClose);
    } else {
      return doClose();
    }

    function doClose () {
      $rootScope.$emit('annotation:unfocus', true);
      $rootScope.$emit('annotation:close');

      return $mdSidenav(COMPONENT_NAME).close().then(function success () {
        annotationSidebar.isOpen = false;
        if (persistState) {
          set('open', false);
        }
      });
    }
  }

  function toggle () {
    if (annotationSidebar.isOpen) {
      return annotationSidebar.close();
    } else {
      return annotationSidebar.open();
    }
  }

  /**
   * Sets a localstorage value in the sidebar namespace.
   */
  function set (name, value) {
    return storage.set(BASE_STORAGE_KEY + '/' + name, value);
  }

  /**
   * Gets a localstorage value in the sidebar namespace.
   */
  function get (name) {
    return storage.get(BASE_STORAGE_KEY + '/' + name);
  }

}

})(angular);
