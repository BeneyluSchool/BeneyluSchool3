'use strict';

angular.module('bns.core.stringHelpers', [])

  /**
   * @ngdoc service
   * @name bns.core.stringHelpers
   * @kind function
   *
   * @description
   * Collection of helper functions for string manipulations
   *
   * ** Methods **
   * - `dashToCamel(str)` : Gets the `camelCased` version of the given
   *                        `dash-cased` string.
   * - `camelToDash(str)` : Gets the `dash-cased` version of the given
   *                        `camelCased` string.
   * - `dashToSnake(str)` : Gets the `snake_cased` version of the given
   *                        `dash-cased` string.
   * - `snakeToDash(str)` : Gets the `dash-cased` version of the given
   *                        `snake_cased` string.
   */
  .factory('stringHelpers', function () {

    // dash-case to camelCase
    var dashToCamel = function (str) {
      return str.replace(/(\-\w)/g, function (match) {
        return match[1].toUpperCase();
      });
    };

    // camelCase to dash-case
    var camelToDash = function (str) {
      return str.replace(/([A-Z])/g, function (match) {
        return '-' + match.toLowerCase();
      });
    };

    // dash-case to snake_case
    var dashToSnake = function (str) {
      return str.replace('-', '_');
    };

    // snake_case to dash-case
    var snakeToDash = function (str) {
      return str.replace('_', '-');
    };

    // snake_case to camelCase
    var snakeToCamel = function (str) {
      return dashToCamel(snakeToDash(str));
    };

    // camelCase to snake_case
    var camelToSnake = function (str) {
      return dashToSnake(camelToDash(str));
    };

    // public API
    return {
      dashToCamel: dashToCamel,
      camelToDash: camelToDash,
      dashToSnake: dashToSnake,
      snakeToDash: snakeToDash,
      snakeToCamel: snakeToCamel,
      camelToSnake: camelToSnake,
    };
  });
