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
    }
  })

  .controller('WorkshopDocumentPageLayoutZoneController', function ($rootScope, $scope, $window, byZoneFilter, orderByFilter, _) {
    var ctrl = this;
    ctrl.zoneClassPrefix = 'workshop-layout-zone-';
    ctrl.zoneClasses = [];
    ctrl.resetQuestionnaire = resetQuestionnaire;

    init();

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
      return orderByFilter(orderByFilter(orderByFilter(byZoneFilter(widgetGroups, ctrl.zone.numbers), 'position'), 'zone'), 'page_id');
    }


    function resetQuestionnaire () {
      $scope.$emit('questionnaire.reset.click');
      var offset = 0;
      var duration = 0;
      var position =  1;

      var target = angular.element($window.document.getElementById('workshop-page-' + position));
      var container = angular.element($window.document.getElementById('workshop-document')).closest('.nano-content');

      if (target.length && container.length) {
        var targetY = target.offset().top - target.parent().offset().top + offset;
        container.scrollTo(0, targetY, duration);
      }
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
