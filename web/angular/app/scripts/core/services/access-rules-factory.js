'use strict';

angular.module('bns.core.accessRules', [])

  /**
   * @ngdoc service
   * @name bns.core.accessRules.AccessRules
   * @kind function
   *
   * @description
   * Collection of various access rules, that can be enabled and disabled.
   *
   * ** Methods **
   * - `enable()`: Enables all access rules
   * - `disable()`: Disables all access rules
   *
   * @returns {Function} The AccessRules constructor
   */
  .factory('AccessRules', function () {

    /**
     * Creates a set with the given rules.
     * Each rule must be given as a function that, when executed:
     *  - Creates and enables the actual rule (with whatever business logic)
     *  - Returns a destroyer function, ie one that when executed disables the
     *    actual rule
     *
     * @param {Array} rules Array of callable rules
     */
    function AccessRules (rules) {
      this.rules = rules || [];
      this._destroyers = [];
    }

    /**
     * Enables every rule, by executing their creator function.
     */
    AccessRules.prototype.enable = function () {
      var self = this;
      angular.forEach(self.rules, function (rule) {
        self._destroyers.push(rule());
      });
    };

    /**
     * Disables all rules, by executing their destroyer functions.
     */
    AccessRules.prototype.disable = function () {
      var self = this;
      angular.forEach(self._destroyers, function (destroyer) {
        destroyer();
      });
      self._destroyers = [];
    };

    return AccessRules;
  })

;
