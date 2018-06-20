(function (angular) {
'use strict';

angular.module('bns.main.featureFlags')

  .directive('bnsFeatureFlag', BnsFeatureFlagDirective)
  .directive('bnsFeaturePush', BnsFeaturePushDirective)

;

/**
 * @ngdoc directive
 * @name bnsFeatureFlag
 * @module bns.main.featureFlags
 *
 * @requires $compile
 * @requires $window
 * @requires featureFlags
 *
 * @param {string=} bnsFeatureFlag The flag to use
 * @param {string=} bnsFeaturePush If attribute is present, checks flags for
 *                                 adding a push UI. If 'incentive', also adds a
 *                                 link to the spot licences.
 * @param {string=} bnsFeaturePushFlag The flag to check for push UI. Defaults
 *                                     to the feature flag appended with
 *                                     '_push'.
 */
function BnsFeatureFlagDirective ($compile, $window, featureFlags) {

  return {
    restrict: 'A',
    priority: 2000,
    terminal: true,
    compile: compile,
  };

  function compile (element, attrs) {
    return function postLink (scope, element) {
      var flag = scope.$eval(attrs.bnsFeatureFlag);

      // nothing to do here
      if (!flag) {
        return compileForFlag();
      }

      featureFlags.get(flag)
        .then(function applyFlag (flagValue) {
          if (flagValue) {
            // has feature, proceed with compilation
          } else {
            // handle an eventual push flag
            if (angular.isDefined(attrs.bnsFeaturePush)) {
              // set the default flag to use for push
              if (!angular.isDefined(attrs.bnsFeaturePushFlag)) {
                element.attr('bns-feature-push-flag', '\'' + flag + '_push\'');
              }
            } else {
              // no feature = remove everyting
              element.remove();
            }
          }
          compileForFlag();
        })
      ;

      function compileForFlag () {
        element.removeAttr('bns-feature-flag');
        element.removeAttr('ng-attr-bns-feature-flag');
        $compile(element)(scope);
      }
    };
  }

}

function BnsFeaturePushDirective ($compile, $window, featureFlags) {

  return {
    restrict: 'A',
    priority: 1990,
    terminal: true,
    link: postLink,
  };

  function postLink (scope, element, attrs) {
    var pushFlag = scope.$eval(attrs.bnsFeaturePushFlag);

    if (!pushFlag) {
      return compileForFlag();
    }

    return featureFlags.get(pushFlag).then(function (pushValue) {
      if (pushValue) {
        // no feature & push = add push UI
        var overlay = angular.element('<div class="bns-feature-push-overlay" bns-feature-push-overlay></div>');
        element.addClass('bns-feature-push-container');
        element.append(overlay);

        // make the element totally inactive
        disableElement(element);

        // disable nested control elements
        if (angular.isDefined($window.MutationObserver)) {
          // use observer to dynamically disable new elements
          var observer = new $window.MutationObserver(function () {
            disableNestedInteractiveElements(element, overlay);
          });
          observer.observe(element[0], {
            childList: true,
            subtree: true
          });
        } else {
          // fallback to disabling elements present on 1st pass only
          disableNestedInteractiveElements(element, overlay);
        }
      } else {
        // no feature & no push = remove everything
        element.remove();
      }
      compileForFlag();
    });

    function compileForFlag () {
      element.removeAttr('bns-feature-flag');
      element.removeAttr('ng-attr-bns-feature-flag');
      element.removeAttr('bns-feature-push');
      element.removeAttr('ng-attr-bns-feature-push');
      $compile(element)(scope);
    }
  }

  function disableElement (element) {
    element.attr('disabled', 'disabled');
    element.attr('ng-disabled', 'true');
    element.removeAttr('data-toggle');
    element.removeAttr('data-target');
    element.removeAttr('ui-sref');
    element.removeAttr('ng-href');
    element.removeAttr('href');
    element.removeAttr('ng-click');
    element.removeAttr('ng-mouseup');
    element.removeAttr('ng-mousedown');
    element.off();
  }

  function disableNestedInteractiveElements (root, ignore) {
    var nestedElements = root
      .find('input,md-button,.md-button,[ng-model],[ng-value],[value]')
      .filter(function () {
        if (ignore) {
          // ignore elements that are children of the ignore reference
          return !ignore.has(this).length;
        }

        return true;
      })
    ;
    disableElement(nestedElements);
  }

}

})(angular);
