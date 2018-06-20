(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.components.pager
 */
angular.module('bns.components.pager', [])

  .directive('bnsPager', BnsPagerDirective)
  .controller('BnsPager', BnsPagerController)

;

/**
 * @ngdoc directive
 * @name bnsPager
 * @module bns.components.pager
 *
 * @description
 * Displays a pager.
 *
 * ** Attributes **
 *  - `bns-pages` {=Integer}: The total number of pages.
 *  - `bns-page` {=Integer}: The current page number.
 *  - `bns-range` {=Integer}: The number of pages to display. Defaults to 5.
 *  - `bns-disabled` {=Integer}: When to disable the pager.
 *  - `bns-pagechange` {=Function}: A callback to execute when the page has
 *                                  changed via this component.
 *
 * @example
 * <!-- A pager in a toolbar -->
 * <md-toolbar>
 *   <bns-pager bns-pages="ctrl.totalPages" bns-page="ctrl.currentPage" bns-disabled="ctrl.busy" bns-pagechange="ctrl.onPageChange()" class="md-toolbar-tools"></bns-pager>
 * </md-toolbar>
 */
function BnsPagerDirective () {

  return {
    restrict: 'E',
    scope: {
      pages: '=bnsPages',
      page: '=bnsPage',
      range: '@bnsRange',
      disabled: '=bnsDisabled',
      onPagechange: '&bnsPagechange',
    },
    controller: 'BnsPager',
    controllerAs: 'pager',
    bindToController: true,
    templateUrl: 'views/components/pager/bns-pager.html',
  };

}

function BnsPagerController (_, $scope, $mdMedia) {

  var PAGER_DEFAULT_RANGE = 5;
  var pager = this;
  pager.visiblePages = [];
  pager.prev = prev;
  pager.next = next;
  pager.first = first;
  pager.last = last;
  pager.go = go;

  init();

  function init () {
    $scope.$mdMedia = $mdMedia;
    $scope.$watch('pager.pages', setupRange);
    $scope.$watch('pager.page', setupRange);
  }

  function setupRange () {
    if (!pager.pages) {
      return;
    }
    if (!(pager.range)) {
      pager.range = PAGER_DEFAULT_RANGE;
    }

    var halfRange = Math.floor(pager.range / 2);
    pager.rangeStart = Math.max(1, Math.min(pager.page - halfRange, pager.pages - pager.range + 1));
    pager.rangeEnd = Math.min(pager.pages, Math.max(pager.page + halfRange, pager.range));
    pager.visiblePages = _.range(pager.rangeStart, pager.rangeEnd + 1);
  }

  function prev () {
    pager.go(Math.max(1, pager.page - 1));
  }

  function next () {
    pager.go(Math.min(pager.pages, pager.page + 1));
  }

  function first () {
    pager.go(1);
  }

  function last () {
    pager.go(pager.pages);
  }

  function go (page) {
    pager.page = page;
    pager.onPagechange();
  }

}

})(angular);
