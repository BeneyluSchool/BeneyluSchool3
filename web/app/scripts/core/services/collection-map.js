'use strict';

angular.module('bns.core.collectionMap', [])

  .factory('CollectionMap', CollectionMapFactory)

;

/**
 * @ngdoc service
 * @name bns.core.collectionMap.CollectionMap
 * @kind function
 *
 * @description
 * This is a wrapper for a collection of item, that also maintains a map of
 * them for quick reference.
 * The main purpose of this class is to complete Javascript's lack of indexed
 * arrays.
 *
 * @example
 * var myArray = [{id: 4}, {id: 8}, {id: 15}];
 * var myCollectionMap = new CollectionMap(myArray);
 * myCollectionMap.list === myArray; // true
 * myCollectionMap.map[4]; // {id: 4}
 *
 * var anotherArray = [{key: 'AAA'}, {key: 'BBB'}];
 * var myOtherCollMap = new CollectionMap(anotherArray, 'key');
 * myOtherCollMap.map.AAA === anotherArray[0]; // true
 *
 * @requires $rootScope
 * @requires _
 *
 * @returns {Function} The CollectionMap constructor
 */
function CollectionMapFactory ($rootScope, _) {

  /**
   * Constructor, builds a collection map for the given array, indexed by the
   * given property.
   *
   * @param {Array} data
   * @param {String} prop Defaults to `id`
   */
  function CollectionMap (data, prop) {
    var self = this;

    this.list = [];
    this.map = {};
    this.prop = prop ? prop : 'id';

    this.setData(data);

    $rootScope.$watchCollection(function () {
      return self.list;
    }, function () {
      self.refreshMap();
    });
  }

  /**
   * Sets the collection data
   *
   * @param {Array} data
   */
  // comment string
  CollectionMap.prototype.setData = function (data) {
    if (!angular.isArray(data)) {
      throw 'CollectionMap can only work with arrays';
    }

    this.list = data;
    this.refreshMap();
  };

  /**
   * Reflect list changes in the map
   */
  CollectionMap.prototype.refreshMap = function () {
    var self = this;

    this.map = {};
    angular.forEach(this.list, function (item) {
      self.map[item[self.prop]] = item;
    });
  };

  /**
   * Resets the list and map.
   */
  CollectionMap.prototype.reset = function () {
    this.list = [];
    this.map = {};
  };

  /**
   * Checks whether the given item is in the map
   *
   * @param {Object} item
   * @return {Boolean}
   */
  CollectionMap.prototype.has = function (item) {
    return !!this.map[item[this.prop]];
  };

  /**
   * Checks whether all the given items are in the map
   *
   * @param  {Array} items
   * @return {Boolean}
   */
  CollectionMap.prototype.hasc = function (items) {
    var self = this;

    return _.every(items, function (item) {
      return self.has(item);
    });
  };

  /**
   * Adds given item to the collection, if not already here
   *
   * @param {Object} item
   */
  CollectionMap.prototype.add = function (item) {
    if (!this.has(item)) {
      this.map[item[this.prop]] = item;
      this.list.push(item);
    }
  };

  /**
   * Adds the given items to the collection, if not already here
   *
   * @param {Array} items
   */
  CollectionMap.prototype.addc = function (items) {
    var self = this;
    angular.forEach(items, function (item) {
      self.add(item);
    });
  };

  /**
   * Removes given item from the collection, if present
   *
   * @param {Object} item
   */
  CollectionMap.prototype.remove = function (item) {
    if (this.has(item)) {
      var comparator = {},
        key = item[this.prop];
      comparator[this.prop] = key;
      delete this.map[key];
      _.remove(this.list, comparator);
    }
  };

  /**
   * Removes the given items to the collection, if present
   *
   * @param {Array} items
   */
  CollectionMap.prototype.removec = function (items) {
    var self = this;
    angular.forEach(items, function (item) {
      self.remove(item);
    });
  };

  /**
   * Adds or remove the given element, depending on its current presence (or
   * absence).
   *
   * @param {Object} item
   * @returns {Boolean} Whether the collection now has the item or not.
   */
  CollectionMap.prototype.toggle = function (item) {
    var hasItem;

    if (this.has(item)) {
      this.remove(item);
      hasItem = false;
    } else {
      this.add(item);
      hasItem = true;
    }

    return hasItem;
  };

  /**
   * Checks whether the given key is set.
   *
   * @param {String|Integer} key
   * @returns {Boolean}
   */
  CollectionMap.prototype.isset = function (key) {
    return !!this.map[key];
  };

  /**
   * Gets the item with the given key.
   *
   * @param {String|Integer} key
   * @returns {Object}
   */
  CollectionMap.prototype.get = function (key) {
    return this.map[key];
  };

  /**
   * Gets the collection of items with the given keys
   *
   * @param {Array} keys
   * @returns {Array}
   */
  CollectionMap.prototype.getc = function (keys) {
    var self = this;
    var ret = [];
    angular.forEach(keys, function (key) {
      if (self.isset(key)) {
        ret.push(self.get(key));
      }
    });

    return ret;
  };

  /**
   * Executes the given callable for each element in the collection. The
   * callable is given the element as parameter
   *
   * @param {Function} callable
   */
  CollectionMap.prototype.batch = function (callable) {
    angular.forEach(this.list, function (item) {
      callable(item);
    });
  };

  return CollectionMap;
}
