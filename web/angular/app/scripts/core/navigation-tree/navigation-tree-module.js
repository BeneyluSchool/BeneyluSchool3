'use strict';

/**
 * @ngdoc overview
 * @name bns.core.navigationTree
 *
 * @description Module for a generic navigation tree.
 */
angular.module('bns.core.navigationTree', [
  'dotjem.angular.tree',
  'bns.core.navigationTree.service',
  'bns.core.navigationTree.navigationTreeRoot',
  'bns.core.navigationTree.navigationTreeNode',
]);
