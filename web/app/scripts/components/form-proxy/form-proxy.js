(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.components.formProxy
 *
 * @description
 * Allows forms to be proxied, ie. their inputs duplicated in another part of
 * the application.
 *
 * @usage
 * Within a <form> directive, add the classes .input-container.proxy on the
 * input containers that must be cloned.
 * Place a <bns-form-proxy> directive where the clones should appear.
 * Make sure that both the original form and the proxy can access data on the
 * same scope (ie the form model), for input sync.
 */
angular.module('bns.components.formProxy', [])

  .directive('bnsFormProxySource', BNSFormProxySourceDirective)
  .directive('bnsFormProxy', BNSFormProxyDirective)
  .factory('formProxyData', FormProxyDataFactory)

;

/**
 * @ngdoc directive
 * @name form
 *
 * @restrict AE
 *
 * @description
 * Overload of the base form directive. It stores its template before
 * compilation, so that a proxy can be built later from this initial DOM.
 *
 * ** Parameters **
 *  - bnsFormProxySource | name (string): defines the namespace uneder which
 *                                        the template is stored. Used by the
 *                                        bnsFormProxy directive.
 *  - scope (bool): whether to also store current scope and use it in proxified
 *                  elements.
 *
 * @requires formProxyData
 */
function BNSFormProxySourceDirective (formProxyData) {

  return {
    restrict: 'AE',
    priority: 2000,
    compile: function (element, attrs) {
      var namespace = attrs.bnsFormProxySource || attrs.name || 'form';
      formProxyData.templates[namespace] = element[0].outerHTML;

      return postLink;
    },
  };

  function postLink (scope, element, attrs) {
    if (attrs.scope) {
      var namespace = attrs.bnsFormProxySource || attrs.name || 'form';
      formProxyData.scopes[namespace] = scope;
    }
  }

}

/**
 * @ngdoc directive
 * @name bnsFormProxy
 * @module bns.components.formProxy
 *
 * @restrict E
 *
 * @description
 * Displays the form proxy.
 *
 * @example
 * <bns-form-proxy-data></bns-form-proxy-data>
 *
 * @requires $compile
 * @requires formProxyData
 */
function BNSFormProxyDirective ($compile, $anchorScroll, $mdSidenav, formProxyData) {

  return {
    restrict: 'E',
    link: postLink,
  };

  function postLink (scope, element, attrs) {
    var namespace = attrs.source || 'form';

    // when proxy template changes, insert the relevant nodes into the
    // placeholder.
    scope.$watch(function () { return formProxyData.templates[namespace]; }, function () {
      var template = angular.element(formProxyData.templates[namespace]);
      var toKeep = template.find('.input-container.proxy, md-input-container.proxy');
      var proxies = angular.element();
      toKeep.each(function (idx, container) {
        proxies = proxies.add(prepareProxy(container));
      });
      element.empty();
      element.append($compile(proxies)(formProxyData.scopes[namespace] || scope));
    });

    /**
     * Augments the given jQuery element
     *
     * @param {Object} container
     * @returns {Object} A new jQuery element
     */
    function prepareProxy (container) {
      var $container = angular.element(container);
      var ret = angular.element();
      var label = $container.find('label').text();
      if (!label) {
        console.warn('Form proxy without label', container);
      }

      // add an anchor for easy nav to the original element
      if ($container.attr('id')) {
        var $anchor = angular.element('<md-button>'+
          '<div class="flex layout-row">'+
            '<span class="flex text-ellipsis">'+label+'</span>'+
            '<md-icon>chevron_right</md-icon>'+
          '</div>'+
        '</md-button>');
        $anchor.attr({
          'class': 'bns-form-proxy-anchor',
          'ng-href': '#' + $container.attr('id'),
          'du-smooth-scroll': true,
          'ng-click': 'app.toggleSidebar()',
        });
        $container.removeAttr('id');

        ret = ret.add($anchor);
      }

      // wrap into an expander
      $container.wrap('<bns-expander label="'+label+'" is-open="true"></bns-expander>');
      ret = ret.add($container.parent());

      return ret;
    }
  }

}

/**
 * @ngdoc service
 * @name formProxyData
 * @module bns.components.formProxy
 *
 * @description
 * A simple service to store proxy data shared between directives.
 */
function FormProxyDataFactory () {

  return {
    templates: {},
    scopes: {},
  };

}

}) (angular);
