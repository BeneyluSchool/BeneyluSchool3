(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.starterKit.utils
 */
angular.module('bns.starterKit.utils', [])

  .factory('starterKitUtils', StarterKitUtilsFactory)
  .controller('BnsStarterKitFrame', BnsStarterKitFrameController)

;

/**
 * CSS class applied to active starter kit UI elements
 *
 * @type {String}
 */
var ACTIVE_CLASS = 'starter-kit-active';

/**
 * CSS class applied to frozen starter kit UI elements
 *
 * @type {String}
 */
var FROZEN_CLASS = 'starter-kit-frozen';

/**
 * CSS z-index applied to starter kit UI elements. Just above default dialogs.
 *
 * @type {Integer}
 */
var Z_INDEX = 81;

/**
 * Value of the offset from the edge of viewport for absolutely positioned dialogs.
 *
 * @type {String}
 */
var DIALOG_POSITION_OFFSET = '40px';

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
function StarterKitUtilsFactory ($rootScope, $animate, $q, $interval, $timeout, $mdUtil, $mdPanel) {

  var utils = {
    createBackdrop: createBackdrop,
    activate: activate,
    freeze: freeze,
    frame: frame,
    scrollIntoView: scrollIntoView,
    watchValidate: watchValidate,
    getElementAsync: getElementAsync,
    wrapInPromises: wrapInPromises,
    showPointer: showPointer,
    showDialog: showDialog,
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
      if (container.length && offset !== prevOffset) {
        container.scrollToElement(element, offset, ANIMATION_DURATION).then(function () {
          $rootScope.$emit('starterkit.control.moved', element);
        });
      }
      prevOffset = offset;
    }
  }

  /**
   * Makes the given UI element frozen, ie non reactive to input
   *
   * @param {Element} element
   * @returns {Function} A deregistration function that unfreezes the element
   */
  function freeze (element) {
    element.addClass(FROZEN_CLASS);

    return function unfreeze () {
      element.removeClass(FROZEN_CLASS);
    };
  }

  function frame (element, clickable) {
    var position = $mdPanel.newPanelPosition()
      .relativeTo(element)
      .addPanelPosition($mdPanel.xPosition.ALIGN_START, $mdPanel.yPosition.ALIGN_TOPS);
    var syncPositionInterval;
    var conf = {
      attachTo: angular.element('body'),
      template: '<bns-starter-kit-frame></bns-starter-kit-frame>',
      position: position,
      controller: 'BnsStarterKitFrame',
      locals: {
        target: element,
        clickable: clickable,
      },
      onOpenComplete: keepPositionSync,
    };
    var panelRef = $mdPanel.create(conf);
    panelRef.open();

    return function unframe () {
      $interval.cancel(syncPositionInterval);
      panelRef.close();
      panelRef.destroy();
    };

    function keepPositionSync () {
      panelRef.updatePosition(conf.position);
      syncPositionInterval = $interval(function () {
        panelRef.updatePosition(conf.position);
      }, 250);
    }
  }

  /**
   * Makes the given UI element active
   *
   * @param {Element} element
   * @param {Boolean} watch
   * @returns {Function} A deregistration function that deactivates the element
   */
  function scrollIntoView (element, container) {
    var ANIMATION_DURATION = 400;

    container = container || getAncestorWithScroll(element);
    var offset = Math.max((container.innerHeight() - element.innerHeight()) / 2, 0);
    if (container.length && offset !== element._prevOffset) {
      container.scrollToElement(element, offset, ANIMATION_DURATION).then(function () {
        $rootScope.$emit('starterkit.control.moved', element);
      });
    }
    element._prevOffset = offset;
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
          $timeout(callback, 0);
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

  function showPointer (element, text) {
    element.shown = true;
    var syncPositionInterval;
    var position = $mdPanel.newPanelPosition()
      .relativeTo(element)
      .addPanelPosition($mdPanel.xPosition.ALIGN_START, $mdPanel.yPosition.ALIGN_TOPS);
    var config = {
      attachTo: angular.element('body'),
      template:
        '<bns-starter-kit-pointer-display class="starter-kit-active">'+
          '<md-tooltip class="starter-kit-pointer-tooltip" md-direction="bottom">'+
            text+
          '</md-tooltip>'+
        '</bns-starter-kit-pointer-display>',
      position: position,
      clickOutsideToClose: false,
      escapeToClose: false,
      focusOnOpen: false,
      propagateContainerEvents: true,
      zIndex: Z_INDEX + 1,
    };

    var panelRef = $mdPanel.create(config);
    syncPositionInterval = $interval(function () {
      panelRef.updatePosition(position);
    }, 250);
    panelRef.open();

    return function cleanup () {
      if (syncPositionInterval) {
        $interval.cancel(syncPositionInterval);
      }
      element.shown = false;
      panelRef.close();
    };
  }

  function showDialog (conf) {
    conf = buildConfiguration(conf, {
      templateUrl: 'views/starter-kit/dialogs/dialog.html',
      panelClass: 'bns-starter-kit-dialog-panel',
      onOpenComplete: keepPositionSync,
    });

    var panelRef = $mdPanel.create(conf);
    var syncPositionInterval;
    panelRef.open();

    return function cleanupDialog () {
      if (syncPositionInterval) {
        $interval.cancel(syncPositionInterval);
      }

      return panelRef.close();
    };

    function keepPositionSync () {
      if (conf.position) {
        panelRef.updatePosition(conf.position);
        syncPositionInterval = $interval(function () {
          panelRef.updatePosition(conf.position);
        }, 1000);
      }
    }
  }

  function buildConfiguration (conf, defaults) {
    var target, position, animation;
    conf = conf || {};
    defaults = defaults || {};
    if (conf.locals && conf.locals.target) {
      target = conf.locals.target;
    }
    if (target) {
      position = $mdPanel.newPanelPosition()
        .relativeTo(target)
        .addPanelPosition($mdPanel.xPosition.OFFSET_START, $mdPanel.yPosition.ALIGN_TOPS)   // before
        .addPanelPosition($mdPanel.xPosition.OFFSET_START, $mdPanel.yPosition.CENTER)
        .addPanelPosition($mdPanel.xPosition.OFFSET_START, $mdPanel.yPosition.ALIGN_BOTTOMS)
        .addPanelPosition($mdPanel.xPosition.OFFSET_END, $mdPanel.yPosition.ALIGN_TOPS)     // after
        .addPanelPosition($mdPanel.xPosition.OFFSET_END, $mdPanel.yPosition.CENTER)
        .addPanelPosition($mdPanel.xPosition.OFFSET_END, $mdPanel.yPosition.ALIGN_BOTTOMS)
        .addPanelPosition($mdPanel.xPosition.ALIGN_START, $mdPanel.yPosition.BELOW)         // below
        .addPanelPosition($mdPanel.xPosition.CENTER, $mdPanel.yPosition.BELOW)
        .addPanelPosition($mdPanel.xPosition.ALIGN_END, $mdPanel.yPosition.BELOW)
        .addPanelPosition($mdPanel.xPosition.ALIGN_START, $mdPanel.yPosition.ABOVE)         // above
        .addPanelPosition($mdPanel.xPosition.CENTER, $mdPanel.yPosition.ABOVE)
        .addPanelPosition($mdPanel.xPosition.ALIGN_END, $mdPanel.yPosition.ABOVE)
      ;
      animation = $mdPanel.newPanelAnimation()
        .openFrom(target)
        .closeTo(target)
        .withAnimation($mdPanel.animation.SCALE)
        .duration(200)
      ;
    } else {
      position = $mdPanel.newPanelPosition()
        .absolute()
      ;
      if (angular.isString(conf.position)) {
        // apply position transformers
        angular.forEach(conf.position.split(' '), function (pos) {
          if (angular.isFunction(position[pos])) {
            position[pos](DIALOG_POSITION_OFFSET);
          }
        });
        // remove initial position declaration, to be replaced by actual position object
        delete conf.position;
      } else {
        // by default, center dialog in viewport
        position.center();
      }
      animation = $mdPanel.newPanelAnimation()
        .withAnimation($mdPanel.animation.FADE)
      ;
    }

    var panelClasses = ['bns-starter-kit-panel', 'md-body-2', conf.panelClass || '', defaults.panelClass || ''];

    // /!\ do NOT use angular.merge as it copies jQuery elements and loses the
    // original dom reference
    return angular.extend({
      templateUrl: 'views/starter-kit/dialogs/explanation.html',
      controller: 'StarterKitDialog',
      controllerAs: 'ctrl',
      attachTo: conf.parent || angular.element('body'),
      position: position,
      animation: animation,
      escapeToClose: false,
      clickOutsideToClose: false,
      focusOnOpen: false,
      zIndex: Z_INDEX,
      transformTemplate: function (template) {
        return '' +
          '<div bns-eat-click-if="!ctrl.step.frame" class="md-panel-outer-wrapper ' + panelClasses.join('-wrapper ') + '-wrapper">' +
          '  <div class="md-panel" style="left: -9999px;" ng-style="panelStyle">' + template + '</div>' +
          '</div>';
      },
      hasBackdrop: false,
    }, defaults, conf, {
      // smart merge classes together
      panelClass: panelClasses.join(' '),
      // smart merge locals together
      locals: angular.extend({
        target: null,
      }, defaults.locals || {}, conf.locals || {}),
    });
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

function BnsStarterKitFrameController ($element, $scope, target, clickable) {

  var $frame = $element.find('bns-starter-kit-frame');

  if (clickable) {
    $frame.addClass('animate-pulse-shadow');

    $frame.on('mouseenter', allowClick);
    $frame.on('mouseleave', blockClick);
    target.on('mouseenter', allowClick);
    target.on('mouseleave', blockClick);

    $element.on('mousewheel touchmove', prevent);
    target.on('mousewheel touchmove dragstart', prevent);

    $scope.$on('$destroy', function cleanup () {
      target.off('mouseenter', allowClick);
      target.off('mouseleave', blockClick);
      target.off('mousewheel touchmove dragstart', prevent);
    });
  }

  // keep frame dimensions
  $scope.$watch(function() {
    var rect = target[0].getBoundingClientRect();
    return rect.width + '|' + rect.height;
  }, function (dimensions) {
    dimensions = dimensions.split('|');
    $frame.width(dimensions[0]);
    $frame.height(dimensions[1]);
  });

  function allowClick () {
    $element.css('pointer-events', 'none');
  }

  function blockClick () {
    $element.css('pointer-events', 'auto');
  }

  function prevent (event) {
    event.preventDefault();
  }

}

})(angular);
