(function (angular) {
'use strict';


/**
 * Array of external libraries to expose as Angular services. They are retrieved
 * as global variables on the window object.
 *
 * @type {Array}
 */
var libraries = [
  'Routing',
  '_',
  'SmsCounter',
];
var module = angular.module('bns.core.libraries', []);

libraries.forEach(provideLibrary);

/**
 * Finds the given library object and make a service of it.
 *
 * @param {String} library the library name
 */
function provideLibrary (library) {
  module.factory(library, ['$window', function ($window) {
    if (!$window[library]) {
      throw library + ' object not found';
    }

    return $window[library];
  }]);
}

}) (angular);
