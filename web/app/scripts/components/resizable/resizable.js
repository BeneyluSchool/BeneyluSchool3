(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.components.resizable
 */
angular.module('bns.components.resizable', [])

  .directive('bnsResizable', BNSResizableDirective)

;

/**
 * @ngdoc directive
 * @name bnsResizable
 * @module bns.components.resizable
 *
 * @description
 * Allows elements to be resized.
 * Shameless copy of https://github.com/Reklino/angular-resizable with bns
 * customizations.
 * Various events are fired while resizing:
 *  - `'bnsResizable.resizeStart'`
 *  - `'bnsResizable.resizing'`
 *  - `'bnsResizable.resizeEnd'`
 *
 * ** Attributes **
 * - `bnsDirections` {Array}: Allowed resize directions. Defaults to ['right'].
 * - `bnsUseFlex` {Boolean}: Wether to use flex styling. Defaults to false.
 * - `bnsPreventOverlap` {Boolean}: Whether to prevent resize the container
 *                                  smaller than its content. Defaults to true.
 * - `bnsPreventOverflow` {Boolean}: Whether to prevent resize bigger than
 *                                   the container. Defaults to true.
 * - `bnsOnResizeData` {Object}: Data to include with each 'onResize' event.
 * - `bnsCenteredX` {Boolean}: If enabled, doubles velocity of horizontal resize.
 * - `bnsCenteredY` {Boolean}: If enabled, doubles velocity of vertical resize.
 * - `bnsGrabber` {String}: DOM for a custom resize handle.
 * - `bnsDisabled` {Boolean}: True to disable resize.
 * - `bnsNoThrottle` {Boolean}: True to disable throttle in resize events.
 * - `bnsRelativeTo` {String}: Selector of an ancestor element to make resize
 *                             relative to (ie. set a size value as percentage).
 *
 * @requires $document
 * @requires $window
 */
function BNSResizableDirective ($document, $window, $log, $mdUtil) {
  var document = $document[0];
  var flexBasis = 'flexBasis' in document.documentElement.style ? 'flexBasis' :
    'webkitFlexBasis' in document.documentElement.style ? 'webkitFlexBasis' :
    'msFlexPreferredSize' in document.documentElement.style ? 'msFlexPreferredSize' : 'flexBasis';

  return {
    restrict: 'AE',
    link: postLink,
  };

  function postLink (scope, element, attrs) {
    var style = $window.getComputedStyle(element[0], null),
      w,
      h,
      dir = scope.$eval(attrs.bnsDirections) || ['right'],
      vx = scope.$eval(attrs.bnsCenteredX) ? 2 : 1, // if centered double velocity
      vy = scope.$eval(attrs.bnsCenteredY) ? 2 : 1, // if centered double velocity
      inner = scope.$eval(attrs.bnsGrabber) ? scope.$eval(attrs.bnsGrabber) : '<span></span>',
      useFlex = !!scope.$eval(attrs.bnsUseFlex),
      preventOverlap = attrs.bnsPreventOverlap ? !!scope.$eval(attrs.bnsPreventOverlap) : true,
      preventOverflow = attrs.bnsPreventOverflow ? !!scope.$eval(attrs.bnsPreventOverflow) : true,
      parent = element.parent(),
      reference,
      start,
      dragDir,
      axis,
      size,
      info = {},
      resizingEmitThrottled = $mdUtil.throttle(resizingEmit, 100);

    init();

    function init () {
      element.addClass('bns-resizable');

      // get the reference element if specified
      if (attrs.bnsRelativeTo) {
        reference = angular.element(attrs.bnsRelativeTo);
        if (!reference.length) {
          $log.warn('Could not find reference element', attrs.bnsRelativeTo);
          reference = null;
        }
      }

      dir.forEach(function (direction) {
        var grabber = document.createElement('div');

        // add class for styling purposes
        grabber.setAttribute('class', 'bns-resize-grip bns-resize-grip-' + direction);
        grabber.innerHTML = inner;
        element[0].appendChild(grabber);
        grabber.ondragstart = function() { return false; };

        var down = function(e) {
          var disabled = scope.$eval(attrs.bnsDisabled);
          if (!disabled && (e.which === 1 || e.touches)) {
            // left mouse click or touch screen
            dragStart(e, direction);
          }
        };
        grabber.addEventListener('mousedown', down, false);
        grabber.addEventListener('touchstart', down, false);

        if (attrs.bnsDisabled) {
          scope.$watch(function () {
            return scope.$eval(attrs.bnsDisabled);
          }, function isDisabled (val) {
            if (val) {
              element.addClass('bns-resize-disabled');
            } else {
              element.removeClass('bns-resize-disabled');
            }
          });
        }
      });
    }

    function resizingEmit (info) {
      scope.$emit('bnsResizable.resizing', info);
    }

    function dragStart (e, direction) {
      dragDir = direction;
      axis = dragDir === 'left' || dragDir === 'right' ? 'x' : 'y';
      size = dragDir === 'left' || dragDir === 'right' ? 'width' : 'height';
      start = axis === 'x' ? getClientX(e) : getClientY(e);
      w = parseInt(style.getPropertyValue('width'));
      h = parseInt(style.getPropertyValue('height'));

      //prevent transition while dragging
      element.addClass('no-transition');

      document.addEventListener('mouseup', dragEnd, false);
      document.addEventListener('mousemove', dragging, false);
      document.addEventListener('touchend', dragEnd, false);
      document.addEventListener('touchmove', dragging, false);

      // Disable highlighting while dragging
      if(e.stopPropagation) {
        e.stopPropagation();
      }
      if(e.preventDefault) {
        e.preventDefault();
      }
      e.cancelBubble = true;
      e.returnValue = false;

      updateInfo(e);
      scope.$emit('bnsResizable.resizeStart', info);
      scope.$apply();
    }

    function dragging (e) {
      var prop, value, offset = axis === 'x' ? start - getClientX(e) : start - getClientY(e);
      switch (dragDir) {
        case 'top':
          prop = preventOverlap ? 'minHeight' : useFlex ? flexBasis : 'height';
          value = h + (offset * vy);
          break;
        case 'bottom':
          prop = preventOverlap ? 'minHeight' : useFlex ? flexBasis : 'height';
          value = h - (offset * vy);
          break;
        case 'right':
          prop = preventOverlap ? 'minWidth' : useFlex ? flexBasis : 'width';
          value = w - (offset * vx);
          break;
        case 'left':
          prop = preventOverlap ? 'minWidth' : useFlex ? flexBasis : 'width';
          value = w + (offset * vx);
          break;
      }

      // convert to percentage value
      if (reference) {
        var referenceValue = getElementSize(reference[0], dragDir);
        value = (value / referenceValue * 100).toFixed(4) + '%';
      } else {
        value += 'px';
      }

      var previousValue = element[0].style[prop];
      element[0].style[prop] = value;

      // prevent overflow only if value is increasing
      if (preventOverflow && parseFloat(value) > parseFloat(previousValue)) {
        var parentSize = parseFloat(getElementSize(parent[0], dragDir).toFixed(2));
        var childSize = 0;
        parent.children().each(function (idx, child) {
          childSize += child.getBoundingClientRect()[size];
        });
        childSize = parseFloat(childSize.toFixed(2));
        if (parentSize < childSize) {
          element[0].style[prop] = previousValue;
        }
      }

      updateInfo(e);
      if (scope.$eval(attrs.bnsNoThrottle)) {
        resizingEmit(info);
      } else {
        resizingEmitThrottled(info);
      }
    }

    function dragEnd () {
      updateInfo();
      scope.$emit('bnsResizable.resizeEnd', info);
      scope.$apply();
      document.removeEventListener('mouseup', dragEnd, false);
      document.removeEventListener('mousemove', dragging, false);
      document.removeEventListener('touchend', dragEnd, false);
      document.removeEventListener('touchmove', dragging, false);
      element.removeClass('no-transition');
    }

    function getClientX (e) {
      return e.touches ? e.touches[0].clientX : e.clientX;
    }

    function getClientY (e) {
      return e.touches ? e.touches[0].clientY : e.clientY;
    }

    function updateInfo (e) {
      info.width = false; info.height = false;
      if (axis === 'x') {
        info.width = element[0].style[preventOverlap ? 'minWidth' : useFlex ? flexBasis : 'width'];
      } else {
        info.height = element[0].style[preventOverlap ? 'minHeight' : useFlex ? flexBasis : 'height'];
      }
      info.id = element[0].id;
      info.evt = e;
      info.element = element;
      info.data = scope.$eval(attrs.bnsOnResizeData);
    }

    function getElementSize (element, direction) {
      var style = $window.getComputedStyle(element);
      switch (direction) {
        case 'top':
        case 'bottom':
          return parseFloat(style.height) - parseFloat(style.paddingTop) - parseFloat(style.paddingBottom);
        case 'right':
        case 'left':
          return parseFloat(style.width) - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
      }
    }
  }
}

})(angular);
