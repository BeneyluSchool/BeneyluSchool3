'use strict';

angular.module('bns.viewer.workshop.document.pageLayoutZone', [
  'bns.core.url',
])

  /**
   * @ngdoc directive
   * @name bns.viewer.workshop.document.pageLayoutZone.bnsWorkshopDocumentPageLayoutZone
   * @kind function
   *
   * @description
   * Responsible for handling visual appearance of a zone of the layout of a
   * workshop page.
   *
   * @example
   * <any bns-workshop-document-page-layout-zone></any>
   *
   * @returns {Object} The bnsWorkshopDocumentPageLayoutZone directive
   */
  .directive('bnsWorkshopDocumentPageLayoutZone', function (url) {
    return {
      replace: true,
      require: ['^bnsWorkshopDocumentPage', 'bnsWorkshopDocumentPageLayoutZone'],
      link: link,
      scope: {
        zone: '=bnsWorkshopDocumentPageLayoutZone',
        last: '='
      },
      templateUrl: url.view('viewer/workshop/document/directives/bns-workshop-document-page-layout-zone.html'),
      controller: 'WorkshopDocumentPageLayoutZoneController',
      controllerAs: 'ctrl',
      bindToController: true,
    };

    function link (scope, element, attrs, controllers) {
      var pageCtrl = controllers[0];
      var zoneCtrl = controllers[1];

      zoneCtrl.page = pageCtrl.page;
      zoneCtrl.isWrite = 'write' === pageCtrl.viewMode;
      zoneCtrl.document = controllers[0].document;
      zoneCtrl.isCompetition = pageCtrl.isCompetition;
    }
  })

  .controller('WorkshopDocumentPageLayoutZoneController', function ($rootScope, $scope, $window, $state, byZoneFilter, orderByFilter, _, Restangular) {
    var ctrl = this;
    ctrl.zoneClassPrefix = 'workshop-layout-zone-';
    ctrl.zoneClasses = [];
    ctrl.score = 0;

    init();
    $scope.$on('questionnaire.check_score', function (event) {
      event.stopPropagation();
    });



    function init () {
      _.each(ctrl.zone.code.split('-'), function (type) {
        ctrl.zoneClasses.push(ctrl.zoneClassPrefix + type);
      });
      // keep only widgetGroups contained in the current zone, and order them
      // properly
      $scope.$watchCollection('ctrl.page.widgetGroups', function (widgetGroups) {
        ctrl.zone.widgetGroups = getZoneWidgetGroups(widgetGroups);
      });

      var unregisterWidgetGroupSave = $rootScope.$on('workshop.document.widgetGroup.save', function () {
        ctrl.zone.widgetGroups = getZoneWidgetGroups(ctrl.page.widgetGroups);
      });

      $scope.$on('$destroy', function () {
        unregisterWidgetGroupSave();
      });
    }

    /**
     * Gets the widgetGroups of the current zone
     *
     * @param {Array} widgetGroups
     * @returns {Array}
     */
    function getZoneWidgetGroups (widgetGroups) {
      return orderByFilter(byZoneFilter(widgetGroups, ctrl.zone.numbers), 'position');
    }

  })

  // A simple filter that keep only widgetGroups whose zone number is one of
  // those contained in the given zones.
  .filter('byZone', function (_) {
    return function (widgetGroups, zones) {
      return _.filter(widgetGroups, function (widgetGroup) {
        return _.contains(zones, widgetGroup.zone);
      });
    };
  })

;
