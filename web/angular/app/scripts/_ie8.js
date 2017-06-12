/**
 * @fileOverview Contains all js hacks to make IE8 happy
 */

/* global document */

'use strict';

(function (document) {
  // manually create one of every custom tag used by the app
  var tags = [
    'ng-include',
    'ng-pluralize',
    'ng-view',
    'ui-view',
    'scrollable',
    'bns-modal'
  ];
  for (var i = 0; i < tags.length; i++) {
    document.createElement(tags[i]);
  }
}) (document);
