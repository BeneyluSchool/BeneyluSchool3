'use strict';

angular.module('bns.core.navigationTree.service', [
  'bns.core.objectHelpers',
])

  /**
   * @ngdoc service
   * @name bns.core.navigationTree.service.NavigationTree
   * @kind function
   *
   * @description
   * This factory provides NavigationTree constructor, to ease manipulation of
   * tree-like data.
   *
   * @returns {function} The NavigationTree constructor
   */
  .factory('NavigationTree', function (objectHelpers) {

    /**
     * Creates a new NavigationTree with the given data.
     *
     * @param {Object|Array} data The tree data, used as root(s) element(s)
     * @param {String} prop The property used to identify objects. Defaults to
     *                      'id'.
     */
    var NavigationTree = function (data, prop, childrenAcessor) {
      prop = prop || 'id';
      childrenAcessor = childrenAcessor || 'children';

      this.init(prop, childrenAcessor);

      if (data) {
        this.setData(data);
      }
    };

    /**
     * Initializes the object, resetting its data and maps.
     *
     * @param {String} prop The property used to identify objects.
     * @param {String} childrenAccessor Property path to access children
     */
    NavigationTree.prototype.init = function (prop, childrenAcessor) {
      this.prop = prop;
      this.parentProp = 'parent_key';
      this.childrenAcessor = childrenAcessor;
      this.roots = [];
      this.expandedNodesMap = {};
      this.parentsMap = {};
      this.nodesMap = {};
      this.virtualRoot = {  // dummy node to be used as virtual root
        $virtual: true,
      };
    };

    /**
     * Sets the model data, and refreshes parent mapping
     *
     * @param {Object|Array} data The data, to be used as root(s)
     */
    NavigationTree.prototype.setData = function (data) {
      if (angular.isArray(data)) {
        this.roots = data;
      } else {
        this.roots = [data];
      }

      this.expandedNodesMap = {};
      this.refreshParents();
    };

    /**
     * Tests equality of two nodes
     *
     * @param {Object} node1
     * @param {Object} node2
     * @returns {Boolean} Whether nodes are equal
     */
    NavigationTree.prototype.equality = function(node1, node2) {
      if (!node1 || !node2) {
        return false;
      }

      return node1[this.prop] === node2[this.prop];
    };

    /**
     * Toggles expanded state for the given node
     *
     * @param {Object} node
     * @param {String} key an optional key under which save the expanded state
     *                     (useful when node appears multiple times in tree but
     *                     expanded state should not be shared). Defaults to the
     *                     identifying node property of the tree.
     * @returns {Boolean} Whether the node is now expanded
     */
    NavigationTree.prototype.toggleExpanded = function (node, key) {
      if (this.isExpanded(node, key)) {
        this.collapse(node, key);

        return false;
      } else {
        this.expand(node, key);

        return true;
      }
    };

    /**
     * Sets the given node's expanded state
     *
     * @param {Object} node
     * @param {String} key see toggleExpanded()
     */
    NavigationTree.prototype.expand = function (node, key) {
      this.expandedNodesMap[key || node[this.prop]] = node;
    };

    /**
     * Removes the given node's expanded state
     *
     * @param {Object} node
     * @param {String} key see toggleExpanded()
     */
    NavigationTree.prototype.collapse = function (node, key) {
      this.expandedNodesMap[key || node[this.prop]] = undefined;
    };

    /**
     * Collapses all currently expanded nodes
     */
    NavigationTree.prototype.collapseAll = function () {
      var self = this;
      angular.forEach(this.expandedNodesMap, function (node) {
        self.collapse(node);
      });
    };

    /**
     * Expands all ancestors of the given node (but not itself)
     *
     * @param {Object} node
     * @param {String} key see toggleExpanded()
     */
    NavigationTree.prototype.expandAncestors = function (node, key) {
      var parent;
      while ((parent = this.getParent(node))) {
        this.expand(parent, key);
        node = parent;
      }
    };

    /**
     * Checks whether the given node is expanded
     *
     * @param {Object} node
     * @param {String} key see toggleExpanded()
     * @returns {Boolean}
     */
    NavigationTree.prototype.isExpanded = function (node, key) {
      return !!this.expandedNodesMap[key || node[this.prop]];
    };

    /**
     * Gets the parent of the given node
     *
     * @param {Object} node
     * @returns {Object}
     */
    NavigationTree.prototype.getParent = function (node) {
      var parent = this.parentsMap[node[this.prop]];
      if (parent) {
        return parent;
      }
      if (node[this.parentProp]) {
        return this.nodesMap[node[this.parentProp]];
      }

      return null;
    };

    /**
     * Gets the children of the given node
     * @param {Object} node
     * @returns {Array}
     */
    NavigationTree.prototype.getChildren = function (node) {
      if (node.$virtual) {
        return this.roots;
      }

      return objectHelpers.get(node, this.childrenAcessor);
    };

    /**
     * Sets the children of the given node to the given collection
     * @param {Object} node
     * @param {Array} children
     * @returns {Array} The newly-setted children
     */
    NavigationTree.prototype.setChildren = function (node, children) {
      objectHelpers.set(node, this.childrenAcessor, children);

      return children;
    };

    /**
     * Gets the root of the given node
     *
     * @param {Object} node
     * @returns {Object} The root, or null if node is not in the tree
     */
    NavigationTree.prototype.getRoot = function (node) {
      if (!this.nodesMap[node[this.prop]]) {
        return null;
      }

      var parent = this.getParent(node);
      while (parent) {
        node = parent;
        parent = this.getParent(parent);
      }

      return node;
    };

    /**
     * Checks whether the given reference node has the given object as ancestor.
     *
     * @param {Object} node The reference node
     * @param {Object} ancestor The ancestor candidate
     * @returns {Boolean}
     */
    NavigationTree.prototype.isAncestor = function (node, ancestor) {
      var parent = this.getParent(node);

      if (parent) {
        if (this.equality(parent, ancestor)) {
          return true;
        }

        return this.isAncestor(parent, ancestor);
      }

      return false;
    };

    /**
     * Refreshes the parent mapping. To be called manually when underlying data
     * changes.
     */
    NavigationTree.prototype.refreshParents = function () {
      this.parentsMap = {};
      this.nodesMap = {};
      this._processChildren(this.roots);
    };

    /**
     * Internal helper to recursively process the tree structure and map parent
     * nodes.
     *
     * @protected
     * @param {Array} coll The current node collection
     * @param {Object} parent Parent of the current collection
     */
    NavigationTree.prototype._processChildren = function (coll, parent) {
      // parse each element of the collection
      for (var i = 0; i < coll.length; i++) {
        var current = coll[i];

        // store current element
        this.nodesMap[current[this.prop]] = current;

        // store parent of current element
        this.parentsMap[current[this.prop]] = parent;

        // parse its children
        var children = this.getChildren(current);
        if (children && children.length) {
          this._processChildren(children, current);
        }
      }
    };

    return NavigationTree;
  });
