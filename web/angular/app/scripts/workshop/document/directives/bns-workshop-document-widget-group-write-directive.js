'use strict';

angular.module('bns.workshop.document.widgetGroupWrite', [
  'ui.router',
  'bns.core.url',
  'bns.user.users',
  'bns.workshop.document.deleteWidgetGroupModal',
  'bns.workshop.document.state',
  'bns.workshop.document.lockManager',
  'bns.workshop.document.widgetGroupLockOverlay',
])

  /**
   * @ngdoc directive
   * @name bns.workshop.document.widgetGroupWrite.bnsWorkshopDocumentWidgetGroupWrite
   * @kind function
   *
   * @description
   * Directive for handling widgetGroup edition. It dynamically adds all edition
   * UI components.
   *
   * @example
   * <any bns-workshop-document-widget-group-write></any>
   *
   * @returns {Object} The bnsWorkshopDocumentWidgetGroupWrite directive
   */
  .directive('bnsWorkshopDocumentWidgetGroupWrite', function () {
    return {
      link: function (scope, element, attrs, controller) {
        // can't have isolated scope here
        var widgetGroup = scope.$eval(attrs.bnsWorkshopDocumentWidgetGroupWrite);
        controller.bind(element, widgetGroup);
      },
      controller: 'WorkshopDocumentWidgetGroupWriteController',
      controllerAs: 'ctrl',
    };
  })

  .controller('WorkshopDocumentWidgetGroupWriteController', function ($compile, $rootScope, $scope, $state, _, url, Users, workshopDocumentDeleteWidgetGroupModal, WorkshopDocumentState, workshopDocumentLockManager, WorkshopRestangular , message) {
    var ctrl = this;
    ctrl.bind = bind;
    ctrl.remove = remove;
    ctrl.duplicate = duplicate;

    function bind (element, widgetGroup) {
      ctrl.element = element;
      ctrl.widgetGroup = widgetGroup;

      // dynamically add a toolbar
      addToolbar();
      addLockOverlay();
      setupEvents();

      // track edited widget
      $scope.$watch(function () {
        return WorkshopDocumentState.editedWidgetGroup;
      }, function (editedWidgetGroup) {
        if (editedWidgetGroup && editedWidgetGroup.id === ctrl.widgetGroup.id) {
          ctrl.element.addClass('editing');
        } else {
          ctrl.element.removeClass('editing');
        }
      });

      // track locked widget
      Users.me().then(function (user) {
        $scope.$watchCollection(function () {
          return workshopDocumentLockManager._locks.list;
        }, function () {
          if (workshopDocumentLockManager.isWidgetGroupLockedForUser(ctrl.widgetGroup, user)) {
            ctrl.lock = workshopDocumentLockManager.getWidgetGroupLock(ctrl.widgetGroup);
            ctrl.element.addClass('locked');
          } else {
            ctrl.element.removeClass('locked');
            ctrl.lock = null;
          }
        });
      });
    }

    /**
     * Adds a toolbar to the current element
     */
    function addToolbar () {
      var toolbarUrl = url.view('workshop/widget/widget-group-toolbar.html');
      ctrl.element.prepend('<div ng-include="\'' + toolbarUrl + '\'"></div>');

      // 'ctrl' in this template will refer to this (write) controller, not to
      // the original 'read' controller
      $compile(ctrl.element.children().first())($scope);
    }

    function addLockOverlay () {
      var lockHtml = '<div bns-workshop-document-widget-group-lock-overlay widget-group="ctrl.widgetGroup"></div>';
      ctrl.element.append(lockHtml);

      $compile(ctrl.element.children().last())($scope);
    }

    function setupEvents () {
      ctrl.element.on('click.widgetGroup', clickHandler);

      function clickHandler () {
        Users.me().then(function (user) {
          if (workshopDocumentLockManager.isWidgetGroupLockedForUser(ctrl.widgetGroup, user)) {
            return;
          }

          var widgetEdit = false;
          angular.forEach(ctrl.widgetGroup._embedded.widgets, function(widget) {
            if (widget.type !== 'page-break') {
              widgetEdit = true;
            }
          });

          if (widgetEdit) {
            $state.go('app.workshop.document.base.kit.edit', { widgetGroupId: ctrl.widgetGroup.id });
          }
        });
      }
    }

    /**
     * Removes this widgetGroup
     */
    function remove () {
      workshopDocumentDeleteWidgetGroupModal.widgetGroup = ctrl.widgetGroup;
      workshopDocumentDeleteWidgetGroupModal.activate();
    }

    function duplicate (){

      return WorkshopRestangular.one('widget-groups', ctrl.widgetGroup.id).all('duplicate').post()
        .then(function (widgetGroup) {
          message.success('WORKSHOP.DOCUMENT.WIDGET_DUPLICATED');
          WorkshopDocumentState.document._embedded.widget_groups.push(widgetGroup);
        })
        .catch(function (response) {
          console.error(response);
        });
    }
  })

;
