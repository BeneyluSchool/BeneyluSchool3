(function (angular) {
'use strict'  ;

/**
 * @ngdoc module
 * @name bns.components.button
 * @description
 * The button component.
 */
angular.module('bns.components.button', [])

  .filter('buttonize', ButtonizeFilter)
  .directive('bnsButtonRadio', BNSButtonRadioDirective)

;

/**
 * @ngdoc filter
 * @name buttonize
 * @module bns.components.button
 *
 * @description
 * Filter responsible for the formatting of text inside buttons. The text is
 * split in two parts at the occurence of the first '|' character. Each part is
 * wrapped in HTML tags to allow styling (multiline buttons for example).
 *
 * @param {String} text The button text to parse.
 * @param {String} firstTag HTML tag wrapping the first part of text. Defaults
 *                          to 'span'.
 * @param {String} sencondTag HTML tag wrapping the remainder of text. Defaults
 *                            to 'small'.
 *
 * @returns {String} The formatted html, as a trusted sce resource.
 *
 * @requires $sce
 */
function ButtonizeFilter ($sce) {

  return function (text, firstTag, secondTag) {
    if (!text) {
      text = '';
    }
    text = '' + text; // make sure we have a valid string

    var splitterIndex = text.indexOf('|');
    if (splitterIndex > -1) {
      firstTag = firstTag || 'span';
      secondTag = secondTag || 'small';
      var first = text.substring(0, splitterIndex).trim();
      var rest = text.substring(splitterIndex + 1).trim();
      text = $sce.trustAsHtml('<'+firstTag+'>' + first + '</'+firstTag+'><'+secondTag+'>' + rest + '</'+secondTag+'>');

      return text;
    }

    return $sce.trustAsHtml(text);
  };

}

/**
 * @ngdoc directive
 * @name bnsButtonRadio
 * @module bns.components.button
 *
 * @description
 * Form radio controls, with the look and feel of buttons.
 * Shameless rip of mdRadioButton.
 *
 * @requires $mdAria
 * @requires $mdUtil
 */
function BNSButtonRadioDirective ($mdAria, $mdUtil) {

  var CHECKED_CSS = 'md-primary';

  return {
    restrict: 'A',
    require: '^mdRadioGroup',
    link: postLink
  };

  function postLink (scope, element, attr, rgCtrl) {
    var lastChecked;

    configureAria(element, scope);

    rgCtrl.add(render);
    attr.$observe('value', render);

    element
      .on('click', listener)
      .on('$destroy', function() {
        rgCtrl.remove(render);
      });

    function listener (ev) {
      if (element[0].hasAttribute('disabled')) {
        return;
      }

      scope.$apply(function() {
        rgCtrl.setViewValue(attr.value, ev && ev.type);
      });
    }

    function render () {
      var checked = (rgCtrl.getViewValue() === attr.value);
      if (checked === lastChecked) {
        return;
      }
      lastChecked = checked;
      element.attr('aria-checked', checked);
      if (checked) {
        element.addClass(CHECKED_CSS);
        rgCtrl.setActiveDescendant(element.attr('id'));
      } else {
        element.removeClass(CHECKED_CSS);
      }
    }
    /**
     * Inject ARIA-specific attributes appropriate for each radio button
     */
    function configureAria (element, scope) {
      scope.ariaId = buildAriaID();

      element.attr({
        'id' :  scope.ariaId,
        'role' : 'radio',
        'aria-checked' : 'false'
      });

      $mdAria.expectWithText(element, 'aria-label');

      /**
       * Build a unique ID for each radio button that will be used with aria-activedescendant.
       * Preserve existing ID if already specified.
       * @returns {*|string}
       */
      function buildAriaID () {
        return attr.id || ( 'button_radio_' + $mdUtil.nextUid() );
      }
    }
  }
}

})(angular);
