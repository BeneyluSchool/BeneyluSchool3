'use strict';

/* global document */
/* global window */

(function ($) {

  if (!$) {
    return console.warn('Cannot bootstrap without jQuery');
  }
  if (true == window.angularStopBoot) {
    window.angularBootstrap = function() {};
    return console.warn('angular wont bootstrap');
  }

  window.angularBootstrap = angularBootstrap;

  $(setupDockbar);
  $(bootFromHash);

  var deferred = $.Deferred();

  /**
   * Bootstraps the angular app.
   *
   * @param {String} route An optional URL (hash) to load after boot
   *
   * @returns {Object} A promise, resolved when app is available, with the app
   *                   injector
   */
  function angularBootstrap (route) {
    if (!window.angularInjector) {
      window.angularInjector = angular.bootstrap(document, ['beneyluSchoolMaterialApp']);
    }

    if (route) {
      window.location.hash = route;
    }

    deferred.resolve(window.angularInjector);

    return $.when(deferred.promise());
  }

  /**
   * Bootstraps the angular app. Loads all necessary scripts and styles.
   *
   * @param {String} route An optional URL (hash) to load after boot
   */
  function angularBootstrapWithSrc (route) {
    if (window.angularBootstrapping) {
      console.warn('Angular already bootstrapping');
      return deferred.promise();
    }

    window.angularBootstrapping = true;

    if (!window.angularData) {
      console.warn('Cannot bootstrap without data');
      return deferred.reject('nope');
    }

    console.info('Bootstrapping angular');

    // insert angular-related styles
    var styles = window.angularData.styles;
    for (var i = 0; i < styles.length; i++) {
      var link = document.createElement('link');
      link.href = styles[i];
      link.type = 'text/css';
      link.rel = 'stylesheet';
      $('head').append(link);
    }

    // insert angular-related scripts
    var scripts = window.angularData.scripts;
    var loader = new Loader();
    loader.require(scripts, function () {
      console.info('angular loaded');
      if (route) {
        window.location.hash = route;
      }
      var injector = angular.bootstrap(document, ['beneyluSchoolApp']);
      deferred.resolve(injector);
    });

    return $.when(deferred.promise());
  }

  /**
   * Sets up handlers in the module dockbar
   */
  function setupDockbar () {
    // clicks on angular modules that must be treated as modals
    $('.dockbar-module.angular-modal').on('click', function (e) {
      e.preventDefault();

      // bootstrap the whole thing if not already present
      if (!window.angular) {
        angularBootstrap();
      }

      // update the hash: angular router kicks in
      var hash = $(this).attr('href').split('#')[1];
      if (hash) {
        window.location.hash = hash;
      }
    });
  }

  /**
   * Checks current hash, and bootstraps the angular app if necessary
   */
  function bootFromHash () {
    // hash looks like an angular route, start it
    if (window.location.hash && window.location.hash.indexOf('#/') === 0) {
      if (!window.angular) {
        angularBootstrap();
      }
    }
  }

  var Loader = function () {
    var self = this;

    this.require = function (scripts, callback) {
      this.scripts = scripts;
      this.loadCount = 0;
      this.totalRequired = scripts.length;
      this.callback = callback;
      this.writeScript(this.scripts.shift());
    };
    this.loaded = function () {
      self.loadCount++;

      if (self.loadCount === self.totalRequired && typeof self.callback === 'function') {
        self.callback.call();
      } else {
        self.writeScript(self.scripts.shift());
      }
    };
    this.writeScript = function (src) {
      var s = document.createElement('script');
      s.type = 'text/javascript';
      s.async = true;
      s.src = src;
      s.addEventListener('load', function (e) {
        self.loaded(e);
      }, false);
      var head = document.getElementsByTagName('head')[0];
      head.appendChild(s);
    };
  };

}) (window.jQuery);
