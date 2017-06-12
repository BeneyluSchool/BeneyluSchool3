'use strict';

angular.module('bns.workshop.document.panelKitController', [
  'ui.router',
  'bns.core.message',
  'bns.workshop.document.state',
  'bns.workshop.document.widgetConfigurations',
])

  .controller('WorkshopDocumentPanelKitController', function ($rootScope, $scope, $state, _, message, WorkshopDocumentState, workshopDocumentWidgetConfigurations) {
    var ctrl = this;
    ctrl.sortableConf = {};

    init();

    function init () {
      // load existing widget configurations
      workshopDocumentWidgetConfigurations.getList().then(function (widgetConfigurations) {
        ctrl.widgetConfigurations = widgetConfigurations;
      });

      ctrl.sortableConf = {
        scroll: false,
        sort: false,
        group: {
          name: 'widget-configuration',
          pull: 'clone',
          put: false,
        },
        onStart: onStart,
        onEnd: onEnd,
      };

      // redirect to newly-created widgetgroup
      var unwatchRootEvent = $rootScope.$on('workshop.document.widgetGroup.created', function (evt, id) {
        // check if new widgetgroup is already present
        var newWidgetGroup = _.find(WorkshopDocumentState.document._embedded.widget_groups, { id: id });
        if (newWidgetGroup) {
          $state.go('app.workshop.document.base.kit.edit', {
            widgetGroupId: id,
          });
        } else {
          // watch the widgetgroup collection for when the new one arrives
          var unwatchColl = $rootScope.$watchCollection('WorkshopDocumentState.document._embedded.widget_groups', function (coll) {
            var newWidgetGroup = _.find(coll, { id: id });
            if (newWidgetGroup) {
              unwatchColl();
              $state.go('app.workshop.document.base.kit.edit', {
                widgetGroupId: id,
              });
            }
          });
        }
      });

      $scope.$on('$destroy', function () {
        unwatchRootEvent();
      });

      function onStart () {
        angular.element('.workshop-page-view').addClass('dragging-configuration');
      }

      function onEnd () {
        angular.element('.workshop-page-view').removeClass('dragging-configuration');
      }
    }
  });
