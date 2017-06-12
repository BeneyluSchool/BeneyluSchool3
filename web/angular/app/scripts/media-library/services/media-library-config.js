'use strict';

angular.module('bns.mediaLibrary.config', [])

  /**
   * @ngdoc service
   * @name bns.mediaLibrary.config.mediaLibraryConfig
   * @kind function
   *
   * @description
   * This is a simple provider for the global mediaLibraryConfig variable, set
   * outside of the ng app.
   *
   * It is an easy way to provide configuration options from the outside world.
   *
   * @requires $window
   *
   * @returns {Object} The mediaLibraryConfig
   */
  .factory('mediaLibraryConfig', function ($window) {
    return $window.mediaLibraryConfig || $window.parent.mediaLibraryConfig || {};
  });
