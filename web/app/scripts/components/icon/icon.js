(function (angular) {
'use strict';

angular.module('bns.components.icon', [])

  .directive('bnsIcon', BNSIconDirective)

;

var DEFAULT_NAMESPACE = 'bns-icon-';

/**
 * @ngdoc directive
 * @name bnsIcon
 * @module bns.components.icon
 *
 * @description
 * The little brother of md-icon, for bns custom icons.
 * Because of CORS and multi-domain install constraints, this directive does not
 * support loading of assets by the angular app. Instead all icons must be set
 * via css.
 * Icon name is prefixed by 'bns-icon-' by default. Namespace can be specified
 * by using ':' in icon name
 *
 * For dynamic icons, name can be specified as an angular expression in
 * attribute. It will then be watched for changes and must evaluate to a string
 * representing the icon name (optionally with a namespace);
 *
 * @example
 * <!-- static icon -->
 * <bns-icon>my-icon-name</bns-icon>
 *
 * <!-- static icon with namespace -->
 * <bns-icon>my-namespace:my-icon-name</bns-icon>
 *
 * <!-- dynamic -->
 * <bns-icon name="myAngularExpression"></bns-icon>
 */
function BNSIconDirective () {

  return {
    compile: compile,
  };

  function compile (element, attrs) {
    // get icon name from content (à la md-icon)
    var name = (element.text() || '').trim();

    if (name) {
      return applyIcon(element, name, attrs.size);
    }

    // name as attribute: setup watcher for dynamic value
    if (attrs.name) {
      return postLink;
    }

    return console.warn('bns-icon without name', element);
  }

  function postLink (scope, element, attrs) {
    scope.$watch(function () {
      var watch = scope.$eval(attrs.name);
      return watch;
    }, function (name) {
      applyIcon(element, name, attrs.size);
    });
  }

  function applyIcon (element, name, size) {
    var prev = element.data('previousIconName');
    if (prev) {
      element.removeClass(prev);
    }

    var namespace = DEFAULT_NAMESPACE;
    if (name.indexOf(':') !== -1) {
      var splits = name.split(':');
      namespace = splits[0] + '-';
      name = splits[1];
    }

    size = size ? size + '-' : '';
    prev = namespace + size + name;

    element.addClass(prev);
    element.data('previousIconName', prev);
  }

}

})(angular);
