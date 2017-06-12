(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.components.transclude
 *
 * @description
 * Transclusion, BNS style.
 * The main advantage  of this set of directives over the core ngTransclude is
 * that they allows transclusions into  ARBITRARY elements across the DOM, even
 * with no common scope.
 * The order of appearance of the directives is not relevant, they only must
 * have the same name to work together.
 * The actual transclusion is still handled by angular.
 *
 * @example
 *
 * <bns-transclude-src name="myContent">
 *   <!-- arbitrary content, with bindings and whatnot -->
 * </bns-transclude-src>
 *
 * <bns-transclude-dest name="myContent">
 *   <!-- the same content is transcluded here and bindings still work -->
 * </bns-transclude-dest>
 */
angular.module('bns.components.transclude', [])

  .directive('bnsTranscludeEval', BNSTranscludeEvalDirective)
  .directive('bnsTranscludeSrc', BNSTranscludeSrcDirective)
  .directive('bnsTranscludeDest', BNSTranscludeDestDirective)
  .factory('bnsTranscludeStore', BNSTranscludeStoreFactory)

;

/**
 * @ngdoc directive
 * @name bnsTranscludeEval
 * @module bns.components.transclude
 *
 * @description
 * Transcludes the evaluated expression (typically a string representing DOM
 * elements), if it is not empty.
 * Template is evaluated only once, during directive link.
 *
 * @example
 * <any bns-transclude-eval="myTemplate">default content</any>
 *
 * @requires $compile
 */
function BNSTranscludeEvalDirective ($compile) {

  return {
    restrict: 'A',
    // priority: 1000,
    link: link,
    scope: false
  };

  function link (scope, element, attr) {
    var newHtml = scope.$eval(attr.bnsTranscludeEval);
    if (newHtml) {
      element.html(newHtml);
      $compile(element.contents())(scope);
    }
  }

}

/**
 * @ngdoc directive
 * @name bnsTranscludeSrc
 * @module bns.components.transclude
 *
 * @description
 * Defines a transclusion source for the given namespace.
 *
 * @example
 * <bns-transclude-src name="myTransclusionNamespace"></bns-transclude-src>
 *
 * @requires bnsTranscludeStore
 */
function BNSTranscludeSrcDirective (bnsTranscludeStore) {

  return {
    restrict: 'EA',
    transclude: true,
    link: postLink,
  };

  function postLink (scope, element, attrs, ctrl, transclude) {
    if (!attrs.name) {
      return console.warn('Attempting transclusion without src name');
    }
    if (bnsTranscludeStore[attrs.name]) {
      console.warn('Overriding transclusion src for name', attrs.name);
    }

    // store transclusion for later use by the dest directive
    bnsTranscludeStore[attrs.name] = transclude;

    // and transclude as direct child
    transclude(function (clone) {
      element.empty();
      element.append(clone);
    });
  }

}

/**
 * @ngdoc directive
 * @name bnsTranscludeDest
 * @module bns.components.transclude
 *
 * @description
 * Defines a transclusion destination for the given namespace.
 *
 * @example
 * <bns-transclude-dest name="myTransclusionNamespace"></bns-transclude-dest>
 *
 * @requires bnsTranscludeStore
 */
function BNSTranscludeDestDirective ($timeout, bnsTranscludeStore) {

  return {
    restrict: 'EA',
    link: postLink,
  };

  function postLink (scope, element, attrs) {
    if (!attrs.name) {
      return console.warn('Attempting transclusion without dest name');
    }

    // src directive may be present later in the DOM, so we wait for a complete
    // digest cycle before attempting to transclude
    $timeout(bind);

    function bind () {
      if (!bnsTranscludeStore[attrs.name]) {
        return console.warn('Transclusion src not found for name', attrs.name);
      }

      bnsTranscludeStore[attrs.name](function (clone) {
        element.empty();
        element.append(clone);
      });
    }
  }

}

/**
 * @ngdoc service
 * @name bnsTranscludeStore
 * @module bns.components.transclude
 *
 * @description
 * Used internally to share transclusion functions.
 */
function BNSTranscludeStoreFactory () {

  return {};

}

})(angular);
