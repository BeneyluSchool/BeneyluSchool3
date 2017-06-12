'use strict';

angular.module('bns.viewer.workshop.document.page', [
  'bns.core.url',
])

  /**
   * @ngdoc directive
   * @name bns.viewer.workshop.document.page.bnsWorkshopDocumentPage
   * @kind function
   *
   * @description
   * Responsible for handling visual appearance of a workshop page.
   *
   * ** Attributes **
   * - `widthRatio` : Integer, the width reference for calculating page ratio.
   *                  Optional, defaults to `210`.
   * - `heightRatio` : Integer, the height reference for calculating page ratio
   *                   Optional, defaults to `297`.
   * - `fontSizeReferenceWidth` : Integer, the width in pixels when font size
   *                              should be 100%. Optional. If not specified,
   *                              font size scaling is disabled.
   * - `viewMode` : String `read` or `write`. indicated whether to display page
   *                as read-only, or allow modifications.
   *
   * @example
   * <any bns-workshop-document-page="myPage" font-size-reference-width="960" width-ratio="21" height-ratio="29.7"></any>
   *
   * @requires url
   *
   * @returns {Object} The bnsWorkshopDocumentPage directive
   */
  .directive('bnsWorkshopDocumentPage', function (url) {
    return {
      replace: true,
      scope: {
        document: '=',
        page: '=bnsWorkshopDocumentPage',
        fontSizeReferenceWidth: '=',
        widthRatio: '=',
        heightRatio: '=',
        viewMode: '@',
      },
      templateUrl: url.view('viewer/workshop/document/directives/bns-workshop-document-page.html'),
      controller: 'WorkshopDocumentPageController',
      controllerAs: 'ctrl',
      bindToController: true,
    };
  })

  .controller('WorkshopDocumentPageController', function ($timeout, $rootScope, $scope, $window, $element, _) {
    var ctrl = this;

    var widthRatio = ctrl.widthRatio || 210;
    var heightRatio = ctrl.heightRatio || 297;
    var fontSizeReferenceWidth = ctrl.fontSizeReferenceWidth;

    init();

    function init () {
      ctrl.viewMode = ctrl.viewMode || 'read';

      ctrl.page.last = ctrl.document._embedded.pages.length == ctrl.page.position;

      $element.attr('id', 'workshop-page-' + ctrl.page.position);
      $element.addClass('page-' + ctrl.viewMode);

      fitWidth();
      resizeFont();

      // desperate hack to cope with <scrollable> that messes up dom width by
      // coming too late
      $timeout(function () {
        fitWidth();
        resizeFont();
      });

      angular.element($window).on('resize.workshopPage', function () {
        fitWidth();
        resizeFont();
      });

      $scope.$watchCollection('ctrl.document._embedded.widget_groups', function (widgetGroups) {
        ctrl.page.widgetGroups = getPageWidgetGroups(widgetGroups);
      });

      var unregisterWidgetGroupSave = $rootScope.$on('workshop.document.widgetGroup.save', function () {
        ctrl.page.widgetGroups = getPageWidgetGroups(ctrl.document._embedded.widget_groups);
      });

      $scope.$on('$destroy', function () {
        angular.element($window).off('resize.workshopPage');
        unregisterWidgetGroupSave();
      });
    }

    /**
     * Gets the widgetGroups of the current page
     *
     * @param {Array} widgetGroups
     * @returns {Array}
     */
    function getPageWidgetGroups (widgetGroups) {
      return _.filter(widgetGroups, { page_id: ctrl.page.id });
    }

    /**
     * Fits the element into the width of its container.
     */
    function fitWidth () {
      var parentWidth = $element.parent().width(),   // without padding
        newWidth,
        newHeight;

      // fit width into parent
      newWidth = parentWidth;

      // get height from ratio
      newHeight = Math.round(newWidth / widthRatio * heightRatio);

      if (!ctrl.document.is_questionnaire || (ctrl.document.is_questionnaire && !ctrl.document._embedded.widget_groups.length)) {
        $element.css({
          width: newWidth,
          height: newHeight
        });
      }

    }

    /**
     * Resizes the font size (percentage value) of the element proportionally to
     * its current width compared to its reference width (when font size should
     * be 100%)
     */
    function resizeFont () {
      if (!fontSizeReferenceWidth) {
        return;
      }

      // calculate the new font-size %
      var currentWidth = $element.width();
      var newSize = (currentWidth * 100 / fontSizeReferenceWidth).toFixed(2) + '%';

      $element.css({
        'font-size': newSize
      });
    }

  });
