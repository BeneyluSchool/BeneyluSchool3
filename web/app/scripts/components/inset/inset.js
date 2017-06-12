(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.components.inset
 */
angular.module('bns.components.inset', [])

  .directive('bnsInset', BNSInsetDirective)

;

/**
 * @ngdoc directive
 * @name bnsInset
 * @module bns.components.inset
 *
 * @description
 * Displays inset content. Layout can be specified by using standard md layout.
 *
 * **Attributes**
 *  - `bnsInsetIcon` {=String}: Specifies (or disables) the inset icon. Defaults
 *                              to Beneylu Jim head.
 *  - `bnsInsetSize` {=String}: Specifies the inset size. Supported values are:
 *                              - 'small' for a smaller inset (and icon)
 *  - `bnsInsetElevation` {=String}: Specifies the inset elevation. Supported
 *                                   values are [0-5]. Defaults to 1.
 *
 * @example
 * <!-- simple use -->
 * <bns-inset>My content</bns-inset>
 *
 * <!-- smaller inset -->
 * <bns-inset bns-inset-size="small">My content</bns-inset>
 *
 * <!-- no icon -->
 * <bns-inset bns-inset-icon="false">My content</bns-inset>
 *
 * <!-- vertical/horizontal center in a flex parent -->
 * <bns-inset class="flex layout-row layout-align-center-center">
 *   <div>My inset content</div>
 * </bns-inset>
 */
function BNSInsetDirective () {

  return {
    transclude: true,
    template: function (element, attrs) {
      var icon = (attrs.bnsInsetIcon || '').toLowerCase();
      var iconClass;
      switch (icon) {
        case 'false':
          iconClass = false;
          break;
        case 'shield':
          iconClass = icon;
          break;
        default:
          iconClass = 'jim-head';
      }
      var elevation = 1;
      if (angular.isDefined(attrs.bnsInsetElevation)) {
        elevation = attrs.bnsInsetElevation;
      }

      return '<div class="flex layout-sm-column layout-gt-sm-row layout-align-start-center md-whiteframe-z'+elevation+' bns-inset-container">' +
        (iconClass ? ('<div class="bns-inset-icon '+iconClass+'"></div>') : '') +
        '<div class="flex" ng-transclude></div>' +
      '</div>';
    },
  };

}

})(angular);
