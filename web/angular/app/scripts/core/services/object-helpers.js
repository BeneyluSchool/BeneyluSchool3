'use strict';

angular.module('bns.core.objectHelpers', [])

  /**
   * @ngdoc service
   * @name bns.core.objectHelper.objectHelper
   * @kind function
   *
   * @description Utility functions for objects
   *
   * @returns {Object} The objectHelper service.
   */
  .factory('objectHelpers', function () {
    var srvc = {
      map: map,
      softMerge: softMerge,
      get: get,
      set: set,
    };

    return srvc;

    /**
     * Maps the given collection of objects using the given key to identify
     * them.
     *
     * @param {Array} coll
     * @param {String} key
     * @returns {Object}
     */
    function map (coll, key) {
      var m = {};
      var i, length = coll.length;

      if (angular.isObject(coll[0])) {
        // map objects by their key
        for (i = 0; i < length; i++) {
          m[coll[i][key]] = coll[i];
        }
      } else {
        // map scalars by themselves
        for (i = 0; i < length; i++) {
          m[coll[i]] = coll[i];
        }
      }

      return m;
    }

    /**
     * Merges src into dest, by updating properties and merging arrays, instead
     * of overriding everything.
     *
     * By default, only properties that both objects possess are considered; and
     * angular properties are ignored.
     *
     * @param {Object} dest
     * @param {Object} src
     * @param {String} key The key used to compare objects in collections.
     *                     Defaults to `id`
     * @param {Boolean} allowAdd Whether to allow addition of new properties (ie
     *                           that are in src but not in dest).
     * @param {Boolean} allowRemove Whether to allow removal of existing
     *                              properties (ie that are in dest but not in
     *                              src).
     * @param {Boolean} recursive Whether to perform the same soft merge on
     *                            embedded objects.
     */
    function softMerge (dest, src, key, allowAdd, allowRemove, recursive) {
      key = key ? key : 'id';
      for (var prop in src) {
        if (src.hasOwnProperty(prop)) {
          if (allowAdd || dest.hasOwnProperty(prop)) {
            // skip ng properties
            if (prop.indexOf('$$') === 0) {
              continue;
            }

            if (angular.isArray(src[prop]) && angular.isArray(dest[prop])) {
              doArrayMerge(dest[prop], src[prop], key, allowAdd);
            } else if (recursive && angular.isObject(src[prop]) && angular.isObject(dest[prop])) {
              softMerge(dest[prop], src[prop], key, allowAdd, allowRemove, recursive);
            } else {
              dest[prop] = src[prop];
            }
          }
        }
      }
    }

    /**
     * Gets the value of the given object property, using the given dot
     * property-path accessor.
     *
     * @param {Object} obj
     * @param {String} accessor
     * @returns {Object}
     */
    function get (obj, accessor) {
      if (accessor.indexOf('.') === -1) {
        return obj[accessor];
      }

      var parts = accessor.split('.'),
        last = parts.pop(),
        l = parts.length,
        i = 1,
        current = parts[0];

      while((obj = obj[current]) && i < l) {
        current = parts[i];
        i++;
      }

      if (obj) {
        return obj[last];
      }
    }

    /**
     * Sets the value of the given object property, using the given dot
     * property-path accessor.
     *
     * @param {Object} obj
     * @param {String} accessor
     * @param {Object|String|Whatever} value
     */
    function set (obj, accessor, value) {
      if (accessor.indexOf('.') === -1) {
        return (obj[accessor] = value);
      }

      var parts = accessor.split('.'),
        last = parts.pop(),
        limit = parts.length;  // lenght without last property path part, already popped

      for (var i = 0; i < limit; i++) {
        var current = parts[i];

        // create intermerdiary objects if needed
        if (!obj[current]) {
          obj[current] = {};
        }

        obj = obj[current];
      }

      if (obj) {
        return (obj[last] = value);
      }
    }


    // -------------------------------------------------------------------------
    //  Implementation details
    // -------------------------------------------------------------------------

    function doArrayMerge (dest, src, key, allowAdd) {
      var srcMap = map(src, key);
      for (var i = dest.length; i--;) {
        var item = dest[i],
          itemSrc = srcMap[item[key]];

        if (itemSrc) {
          // item is also in src, update
          softMerge(item, itemSrc, key, allowAdd);
        } else {
          // item is no longer in src, remove
          dest.splice(i, 1);
        }
      }

      // index current dest, and check if some items are in src but not in dest
      var destMap = map(dest, key);
      for (var srcKey in srcMap) {
        if (!destMap[srcKey]) {
          // item is only in src, add
          dest.push(srcMap[srcKey]);
        }
      }
    }

  });
