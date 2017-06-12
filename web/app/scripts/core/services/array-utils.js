(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.core.arrayUtils
 */
angular.module('bns.core.arrayUtils', [])

  .factory('arrayUtils', ArrayUtilsFactory)

;

/**
 * @ngdoc service
 * @name arrayUtils
 *
 * @description
 * Various utility functions on arrays.
 */
function ArrayUtilsFactory () {

  var arrayUtils = {
    /**
     * Merges the content of one or more arrays into the first array given. It
     * is different from [].concat in that the source array is **modified**.
     *
     * @param {array} arr1 The array into which values are merged
     * @param {array} arr2 One or more arrays to be merged
     *
     * @example
     * var arr1 = [1, 2, 3], arr2 = [4, 5, 6];
     * merge(arr1, arr2);
     * console.log(arr1); // is: [1, 2, 3, 4, 5, 6]
     */
    merge: Function.prototype.apply.bind(Array.prototype.push),
    remove: remove,
    empty: empty,
  };

  return arrayUtils;

  /**
   * Removes the given value from array, if it exists.
   *
   * @param {array} arr
   * @param {mixed} value
   * @return {mixed} the removed value if found, null otherwise
   */
  function remove (arr, value) {
    var idx = arr.indexOf(value);
    if (idx > -1) {
      return arr.splice(idx, 1)[0];
    }

    return null;
  }

  /**
   * Empties the given array, and returns it
   * @param  {Array} arr
   * @return {Array}
   */
  function empty (arr) {
    arr.splice(0, arr.length);

    return arr;
  }

}

})(angular);
