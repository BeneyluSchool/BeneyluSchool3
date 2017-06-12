(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.core.loader
 */
angular.module('bns.core.loader', [])

  .factory('Loader', LoaderFactory)

;

/**
 * @ngdoc service
 * @name Loader
 * @module bns.core.loader
 *
 * @description
 * A helper to load external scripts sequentially.
 *
 * @example
 * var loader = new Loader();
 * loader.require([
 *   'http://my.domain/script1.js',
 *   'http://my.domain/script2.js',
 * ], function success () {
 *   // yay!
 * })
 *
 * @requires $window
 */
function LoaderFactory ($window) {

  var document = $window.document;

  return function Loader () {

    /**
     * Loads the given scripts and executes a callback.
     *
     * @param {Array} scripts Array of URL to load
     * @param {Function} callback A callback to execute on success
     */
    this.require = function (scripts, callback) {
      this.scripts = scripts;
      this.loadCount = 0;
      this.totalRequired = scripts.length;
      this.callback = callback;

      // init sequential loading
      this._writeScript(this.scripts.shift());
    };

    this._incrementLoaded = function () {
      this.loadCount++;

      if (this.loadCount === this.totalRequired) {
        if (typeof this.callback === 'function') {
          this.callback.call();
        }
      } else {
        // load next in queue
        this._writeScript(this.scripts.shift());
      }
    };

    this._writeScript = function (src) {
      var self = this;
      var s = document.createElement('script');
      s.type = 'text/javascript';
      s.async = true;
      s.src = src;
      s.addEventListener('load', function (e) {
        self._incrementLoaded(e);
      }, false);
      var head = document.getElementsByTagName('head')[0];
      head.appendChild(s);
    };
  };

}

})(angular);
