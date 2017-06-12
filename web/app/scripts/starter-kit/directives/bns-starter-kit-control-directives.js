(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.starterKit.controlDirectives
 */
angular.module('bns.starterKit.controlDirectives', [
  'bns.starterKit.service',
])

  .directive('bnsStarterKitValidate', BNSStarterKitValidateDirective)
  .directive('bnsStarterKitExplanation', BNSStarterKitExplanationDirective)
  .directive('bnsStarterKitPointer', BNSStarterKitPointerDirective)
  .directive('bnsStarterKitStepper', BNSStarterKitStepperDirective)

;

/**
 * Decorates a starter kit control directive, with common functionalities.
 *
 * @param {Scope} scope           The directive scope
 * @param {Element} element       The directive element
 * @param {Object} attrs          The directive attrs
 * @param {StarterKit} starterKit The global starterKit manager
 * @param {Function} enter        A callback to be executed when the directive
 *                                activates, ie when its step becomes current.
 * @param {Function} exit         A callback to be executed when the directive
 *                                deactivates, ie when its step has finished.
 */
function decorateStarterKitDirective (scope, element, attrs, starterKit, enter, exit) {

  if (!element._skControls) {
    element._skControls = {
      enters: [],
      exits: [],
      hasEntered: false,
    };
    init();
  }

  // register callbacks
  element._skControls.enters.push(enter);
  element._skControls.exits.push(exit);

  function init () {
    // watch current step, and activates this directive if necessary
    scope.$watch(function () {
      return starterKit.current;
    }, function (current) {
      if (current && attrs.step.split('|').indexOf(current.step) > -1) {
        // happens in case of state redirects: dom from src state is still
        // briefly parsed
        if (!element.closest('html').length) {
          return;
        }

        starterKit.activate(element);
        // call all registered callbacks
        element._skControls.enters.forEach(function (enterFn) {
          enterFn(current);
        });
        element._skControls.hasEntered = true;
      } else if (element._skControls.hasEntered) {
        element._skControls.exits.forEach(function (exitFn) {
          exitFn();
        });
        element._skControls.hasEntered = false;
      }
    });
  }

}

/**
 * @ngdoc directive
 * @name bnsStarterKitValidate
 * @module bns.starterKit.controlDirectives
 *
 * @description
 * Sets up a starter kit watchValidate on the given expression
 *
 * ** Attributes **
 * - `step` {String}: the relevant starter kit step
 * - `bnsStarterKitValidate` {String}: If the litteral string 'click' is given,
 *                                     validation will pass with a simple click.
 *                                     Otherwise a starter kit watchValidation
 *                                     is set up for this expression.
 *
 * @requires starterKit
 */
function BNSStarterKitValidateDirective (starterKit) {

  return {
    restrict: 'A',
    link: postLink,
  };

  function postLink (scope, element, attrs) {
    decorateStarterKitDirective(scope, element, attrs, starterKit, enter, exit);

    function enter () {
      starterKit.watchValidate(scope, attrs.bnsStarterKitValidate, null, element);
    }

    function exit () {}
  }

}

/**
 * @ngdoc directive
 * @name bnsStarterKitExplanation
 * @module bns.starterKit.controlDirectives
 *
 * @description
 * Triggers a starter kit explanation UI component targeted on this element.
 *
 * ** Attributes **
 * - `step` {String}: the relevant starter kit step
 *
 * @requires starterKit
 */
function BNSStarterKitExplanationDirective (starterKit) {

  return {
    link: postLink,
  };

  function postLink (scope, element, attrs) {
    decorateStarterKitDirective(scope, element, attrs, starterKit, enter, exit);

    function enter (step) {
      step.target = element;
      starterKit.doStep(step);
    }

    function exit () {}
  }

}

/**
 * @ngdoc directive
 * @name bnsStarterKitPointer
 * @module bns.starterKit.controlDirectives
 *
 * @description
 * Triggers a starter kit pointer UI component with this element.
 *
 * ** Attributes **
 * - `step` {String}: the relevant starter kit step
 * - `pointer` {String}: the pointer code, to set which pointer text in the
 *                       current step will be used.
 *
 * @requires $compile
 * @requires $log
 * @requires starterKit
 */
function BNSStarterKitPointerDirective ($compile, $log, starterKit) {

  return {
    link: postLink,
  };

  function postLink (scope, element, attrs) {
    decorateStarterKitDirective(scope, element, attrs, starterKit, enter, exit);
    var tooltip;

    function enter (step) {
      if (!step.data.pointers[attrs.pointer]) {
        return $log.warn('Missing content for pointer', attrs.pointer, step);
      }

      var tpl = angular.element('<div class="starter-kit-pointer-tooltip-container">'+
        '<md-tooltip class="starter-kit-pointer-tooltip" md-direction="bottom">'+
          step.data.pointers[attrs.pointer]+
        '</md-tooltip>'+
      '</div>');
      tooltip = $compile(tpl)(scope);
      element.append(tooltip);
    }

    function exit () {
      if (tooltip) {
        tooltip.remove();
      }
    }
  }

}

/**
 * @ngdoc directive
 * @name bnsStarterKitPointer
 * @module bns.starterKit.controlDirectives
 *
 * @description
 * Triggers a starter kit stepper UI component targeted on this element.
 *
 * ** Attributes **
 * - `step` {String}: the relevant starter kit step
 * - `require` {String}: An optional ng expression that will block next step
 *                       until it evaluates to a truey value.
 *
 * @requires $mdUtil
 * @requires starterKit
 */
function BNSStarterKitStepperDirective ($mdUtil, starterKit) {

  return {
    templateUrl: 'views/starter-kit/directives/bns-starter-kit-stepper.html',
    scope: true,
    transclude: true,
    priority: -100,
    link: {
      pre: preLink,
      post: postLink,
    }
  };

  function preLink (scope) {
    scope.transcludeName = $mdUtil.nextUid();
  }

  function postLink (scope, element, attrs) {
    decorateStarterKitDirective(scope, element, attrs, starterKit, enter, exit);

    function enter (step) {
      var source = element;
      if (!source[0].offsetHeight) {
        // TODO: element is somehow not visible, try to find a substitute
        if (element.closest('bns-transclude-dest').length) {
          // Element is in a transcluded container, ie it has a copy somewhere
          // that will trigger exactly the same step.
          return console.warn('In a transcluded container, aborting');
        }
        console.warn('Stepper source is not visible', source);
        source = false;
      }
      starterKit.showDialog({
        templateUrl: 'views/starter-kit/dialogs/stepper.html',
        controller: 'StarterKitStepperDialog',
        controllerAs: 'skdialog',
        bindToController: true,
        escapeToClose: false,
        locals: {
          source: source,
          require: attrs.require || step.validate,
        },
        scope: scope,
        preserveScope: true,
        // fake event, $mdDialog only needs the target for positioning animation
        targetEvent: source ? { target: source } : null,
      });
    }

    function exit () {}
  }

}

})(angular);
