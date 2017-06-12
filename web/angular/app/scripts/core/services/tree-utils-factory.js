'use strict';

angular.module('bns.core.treeUtils', [
  'bns.core.objectHelpers',
])

  /**
   * @ngdoc service
   * @name bns.core.treeUtils.treeUtils
   * @kind function
   *
   * @description
   * Utility functions for nested collections
   *
   * @return {Object} The treeUtils service
   */
  .factory('treeUtils', function (_, objectHelpers) {
    var service = {
      walk: walk,
      find: find,
      remove: remove,
      alt: false, // used internally for alternative data return
    };

    return service;

    /**
     * Finds an item in the given collection. The finder can be either:
     * - Object: Each collection item is checked against it. An item is
     *           considered found if it has at least the same properties with
     *           the same values.
     * - Function: Executed for each item in the collection and receives the
     *             item as parameter. If returns truey, the item is considered
     *             found.
     *
     * @param {Array} collection The collection to search
     * @param {Object|Function} what What to find, or how to find it
     * @param {String} accessor An optional property path to access children
     *
     * @returns {Object|null} The item, or null if not found
     */
    function find (collection, what, accessor) {
      var callable;

      if (angular.isObject(what)) {
        callable = function (item) {
          var ret = true;
          _.forOwn(what, function (val, key) {
            ret = ret && item[key] === what[key];
          });

          return ret;
        };
      } else if (angular.isFunction(what)) {
        callable = what;
      } else {
        console.warn('Find works only with objects or functions');
        return;
      }

      return walk(collection, callable, accessor);
    }

    /**
     * Special case of `find`. If found, the object is removed from the
     * collection.
     *
     * @param {Array} collection The collection to search
     * @param {Object|Function} what What to find, or how to find it
     * @param {String} accessor An optional property path to access children
     * @returns {Boolean} Whether deletion was successful
     */
    function remove (collection, what, accessor) {
      var previousMode = service.alt;

      // Switch to alt mode. We need more info than simply the object itself:
      // the array and index where it sits.
      service.alt = true;
      var data = service.find(collection, what, accessor);

      service.alt = previousMode;

      if (data) {
        // properly remove item from collection
        data.collection.splice(data.index, 1);

        return true;
      }

      return false;
    }

    /**
     * Walks the given tree collection, and execute the given callback for each
     * item.
     * If callback returns a truey value, the process is stopped, and the item
     * for this callback is returned.
     *
     * @param {Object|Array} collection
     * @param {Function} callback
     * @param {String} accessor An optional property path to access children
     * @returns {null|Object} Nothing, or the first item for which callback
     *                        returned truey
     */
    function walk (collection, callback, accessor) {
      if (!collection) {
        return;
      }

      if (!angular.isArray(collection)) {
        collection = [collection];
      }

      return doWalk(collection, callback, accessor);
    }


    // -------------------------------------------------------------------------
    //  Implementation details
    // -------------------------------------------------------------------------

    // Recursive tree walker
    function doWalk (collection, callable, accessor) {
      if (!collection) {
        return null;
      }

      if (!accessor) {
        accessor = 'children';
      }

      var node,
        inChildren,
        i;

      for (i = 0; i < collection.length; i++) {
        node = collection[i];
        if (callable(node)) {
          return service.alt ?
            { node: node, collection: collection, index: i } :
            node
          ;
        }

        inChildren = doWalk(objectHelpers.get(node, accessor), callable, accessor);
        if (inChildren) {
          return inChildren;
        }
      }

      return null;
    }

  });
