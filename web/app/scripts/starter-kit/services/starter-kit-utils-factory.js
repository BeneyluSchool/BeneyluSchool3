(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.starterKit.utils
 */
angular.module('bns.starterKit.utils', [])

  .factory('starterKitUtils', StarterKitUtilsFactory)

;

/**
 * CSS class applied to active starter kit UI elements
 *
 * @type {String}
 */
var ACTIVE_CLASS = 'starter-kit-active';

/**
 * @ngdoc service
 * @name starterKitUtils
 * @module bns.starterKit.utils
 *
 * @description
 * Collection of utilities used by the main starter kit service.
 *
 * @requires $rootScope
 * @requires $animate
 * @requires $q
 * @requires $interval
 * @requires $mdUtil
 */
function StarterKitUtilsFactory ($rootScope, $animate, $q, $interval, $timeout, $mdUtil) {

  var utils = {
    createBackdrop: createBackdrop,
    activate: activate,
    watchValidate: watchValidate,
    getElementAsync: getElementAsync,
    wrapInPromises: wrapInPromises,
  };

  return utils;

  /**
   * Creates a md backdrop bound to the given scope, and as child of the given
   * parent.
   *
   * @param {Scope} scope A ng scope. Defaults to $rootScope
   * @param {Element} parent Parent of the backdrop. Defaults to document body
   * @returns {Function} A deregistration function that removes the backdrop
   */
  function createBackdrop (scope, parent) {
    var backdrop = $mdUtil.createBackdrop(
      scope || $rootScope,
      'md-dialog-backdrop md-opaque starter-kit-backdrop'
    );
    $animate.enter(backdrop, parent || angular.element('body'));

    return function removeBackdrop () {
      $animate.leave(backdrop);
    };
  }

  /**
   * Makes the given UI element active
   *
   * @param {Element} element
   * @param {Boolean} watch
   * @returns {Function} A deregistration function that deactivates the element
   */
  function activate (element, watch) {
    var ANIMATION_DURATION = 400,
      prevOffset,
      interval;

    element.addClass(ACTIVE_CLASS);
    scrollIntoView(element);

    if (watch) {
      interval = $interval(function () {
        scrollIntoView(element);
      }, ANIMATION_DURATION * 4);

      // failsafe, clean everything if element is removed
      element.scope().$on('$destroy', cleanup);
    }

    return function deactivate () {
      element.removeClass(ACTIVE_CLASS);
      cleanup();
    };

    function cleanup () {
      if (angular.isDefined(interval)) {
        $interval.cancel(interval);
      }
    }

    function scrollIntoView (element, container) {
      container = container || getAncestorWithScroll(element);
      var offset = Math.max((container.innerHeight() - element.innerHeight()) / 2, 0);
      if (offset !== prevOffset) {
        container.scrollToElement(element, offset, ANIMATION_DURATION).then(function () {
          $rootScope.$emit('starterkit.control.moved', element);
        });
      }
      prevOffset = offset;
    }
  }

  /**
   * Sets up a watch function for the given expression on the given scope, that
   * executes the given callback when the value is truey.
   * If 'click' is given as expression and an element is provided, a special
   * click handler is setup instead.
   *
   * @param {Scope} scope A ng scope
   * @param {string} expression A ng expression
   * @param {Function} callback
   * @param {Element} the element to attach to (optional)
   * @returns {Function} The scope watch deregistration function
   */
  function watchValidate (scope, expression, callback, element) {
    if ('click' === expression && element) {
      element.on('click', callback);

      return function offClick () {
        element.off('click', callback);
      };
    }

    return scope.$watch(function () {
      return scope.$eval(expression);
    }, function (isValid) {
      if (isValid) {
        if (angular.isFunction(callback)) {
          callback();
        }
      }
    });
  }

  /**
   * Gets (or wait for) the given element asynchronously.
   *
   * @param {String|Element} selector The element to get, or a jQuery selector
   * @returns {Promise} A promise resolving with the element, if found
   */
  function getElementAsync (selector) {
    return $q(function (resolve) {
      var waitForElement = $interval(function () {
        var element = angular.element(selector);
        if (element.length) {
          $interval.cancel(waitForElement);
          resolve(element);
        }
      }, 250);
    });
  }

  /**
   * Wraps the given array of callables in promises, and return a promise.
   *
   * @param {Array} callables An array of functions
   * @returns {Promise} A promise resolving when all callables are resolved
   */
  function wrapInPromises (callables, timeout) {
    var promises = [];
    callables.forEach(function (callable) {
      promises.push($q.when(callable()));
    });

    // create a dummy promise that we can control
    var deferred = $q.defer();

    // promisify the given callables and chain our promise after them
    var allPromise = $q.all(promises);
    allPromise.then(function (result) {
      return deferred.resolve(result);
    });

    if (timeout) {
      // auto-resolve our promise after timeout
      var fallbackTimeout = $timeout(function () {
        deferred.resolve([]);
      }, timeout);

      // cancel timeout if promises are successful
      allPromise.then(function () {
        $timeout.cancel(fallbackTimeout);
      });
    }

    return deferred.promise;
  }


  // Internals
  // ---------------------

  /**
   * Gets the closest ancestor of the given element, that has scrollbars
   *
   * @param {Object} el A DOM element
   * @returns {Element} Ancestor if found, the body otherwise.
   */
  function getAncestorWithScroll (el) {
    var MAX = 20,
      i = 0,
      $el = angular.element(el),
      $parent = $el.parent();
    while (($parent[0] && $parent[0].tagName !== 'BODY') && i < MAX) {
      if (hasScroll($parent)) {
        return $parent;
      }
      $parent = $parent.parent();
      i++;
    }

    return $parent;
  }

  /**
   * Checks if the given element has scrollbars
   *
   * @param {Object} el A DOM element
   * @returns {Boolean}
   */
  function hasScroll (el) {
    var $el = angular.element(el),
      sX = $el.css('overflow-x'),
      sY = $el.css('overflow-y');

    if (sX === sY && (sY === 'hidden' || sY === 'visible')) {
      return false;
    }
    if (sX === 'scroll' || sY === 'scroll') {
      return true;
    }

    return $el.innerHeight() < $el[0].scrollHeight || $el.innerWidth() < $el[0].scrollWidth;
  }

  /**
   * Checks if the given element is visible in the given container
   *
   * @param {Object} el A DOM element
   * @param {Object} container A DOM element
   * @returns {Boolean}
   */
  /*
  function isScrolledIntoView (el, container) {
    el = angular.element(el);
    container = angular.element(container);
    var offset = el[0].getBoundingClientRect().top - container[0].getBoundingClientRect().top;

    return offset >= 0 && (offset + el.height()) <= container.innerHeight();
  }
  */

}

})(angular);
