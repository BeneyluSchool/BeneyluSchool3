(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.core.ga
 */
angular.module('bns.core.ga', [])

  .directive('bnsGaEvent', BNSGaEventDirective)
  .directive('bnsGaEventVideo', BNSGaEventVideoDirective)
  .factory('bnsGa', BNSGaFactory)

;

/**
 * @ngdoc directive
 * @name bnsGaEvent
 * @module bns.core.ga
 *
 * @description
 * Tracks Google Analytics events on click. Event data are to be specified
 * inside the directive attribute, and are evaluated on each track occurrence.
 *
 * @example
 * <any bns-ga-event="{category: ctrl.myEventCat, action: 'someRelevantAction', label: aMeaningfulLabel, value: 42}">...</any>
 *
 * @requires $window
 */
function BNSGaEventDirective (bnsGa) {

  return {
    link: postLink,
  };

  function postLink (scope, element, attrs) {
    element.on('click', track);
    scope.$on('$destory', function cleanup () {
      element.off('click', track);
    });

    function track () {
      return bnsGa.trackEvent(scope.$eval(attrs.bnsGaEvent));
    }
  }

}

function BNSGaEventVideoDirective (bnsGa) {

  return {
    link: postLink,
  };

  function postLink (scope, element, attrs) {
    var hasStarted = false;
    element.on('play', trackPlay);
    element.on('ended', trackEnded);
    scope.$on('$destroy', function cleanup () {
      element.off('play', trackPlay);
      element.off('ended', trackEnded);
    });

    function trackPlay () {
      // track play event only once (ignore pause/resume)
      if (hasStarted) {
        return;
      }
      hasStarted = true;

      return bnsGa.trackEvent(angular.extend(scope.$eval(attrs.bnsGaEventVideo), {
        action: 'video-play',
      }));
    }

    function trackEnded () {
      hasStarted = false;

      return bnsGa.trackEvent(angular.extend(scope.$eval(attrs.bnsGaEventVideo), {
        action: 'video-ended',
      }));
    }
  }

}

function BNSGaFactory ($log, $window) {

  return {
    trackEvent: trackEvent,
    trackPageview: trackPageview,
  };

  function trackEvent (data) {
    if (!data) {
      return;
    }

    $log.info('tracking', data);

    if ($window.ga) {
      return $window.ga('send', 'event', data.category, data.action, data.label, data.value);
    } else if ($window._gaq) {
      return $window._gaq.push(['_trackEvent', data.category, data.action, data.label, data.value]);
    } else {
      return $log.warn('GA not present, event not sent');
    }
  }

  function trackPageview (url) {
    if (!url) {
      url = $window.location.pathname + $window.location.search + $window.location.hash;
    }

    $log.info('tracking page', url);

    if ($window.ga) {
      return $window.ga('send', 'pageview', url);
    } else if ($window._gaq) {
      return $window._gaq.push(['_trackPageview', url]);
    } else {
      return $log.warn('GA not present, event not sent');
    }
  }

}

})(angular);
