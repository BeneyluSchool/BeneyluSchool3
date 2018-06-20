/* global window */
(function (window) {
'use strict';

/**
 * A simple helper to angularize data (ie dom from a jQuery ajax call).
 *
 * @example
 * var angularizer = new Angularizer();
 * var data = someHtmlFromRandomJqueryStuff;
 * jQuery('#mySelector').html(angularizer.process(data));
 * // #mySelector now contains fully-functional angular elements
 */
function Angularizer () {}

Angularizer.prototype.init = function () {
  this.injector = window.angular.element(window.document).injector();
  if (!this.injector) {
    return console.warn('Could not find angular injector');
  }
  this.$compile = this.injector.get('$compile');
  this.scope = this.injector.get('$rootScope').$new();
};

Angularizer.prototype.process = function (data) {
  if (!this.scope) {
    this.init();
  }

  return this.$compile(data)(this.scope);
};

Angularizer.prototype.get = function (serviceName) {
  if (!this.injector) {
    this.init();
  }

  return this.injector.get(serviceName);
};

window.Angularizer = Angularizer;

}) (window);
