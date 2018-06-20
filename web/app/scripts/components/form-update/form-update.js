(function (angular) {
  'use strict';

  /**
   * @ngdoc module
   * @name bns.components.formUpdate
   *
   * @description
   * Allows forms choices to be updated by angular app event.
   *
   * @usage
   *  Add a form-update attribute on any ng-model node.
   */
  angular.module('bns.components.formUpdate', [])

    .directive('formUpdate', BNSFormUpdateDirective)
    .controller('BNSFormUpdateController', BNSFormUpdateController)

  ;

  /**
   * @ngdoc directive
   * @name formUpdate
   *
   * @restrict A
   *
   * @description
   *
   */
  function BNSFormUpdateDirective() {
    return {
      restrict: 'A',
      controller: 'BNSFormUpdateController',
      controllerAs: 'ctrl',
      bindToController: true,
    };
  }

  function BNSFormUpdateController ($rootScope, $scope, $parse, $attrs, _) {
    var ctrl = this;
    ctrl.formUpdateChoices = $parse($attrs.formUpdate)($scope);
    ctrl.formUpdateName = $attrs.formUpdateName;
    ctrl.formUpdateLabelProperty = $attrs.formUpdateLabelProperty || 'label';
    ctrl.formUpdateValueProperty = $attrs.formUpdateValueProperty || 'value';

    // TODO parameter for event name
    ctrl.createdCancel = $rootScope.$on('userDirectory.distribution.created', function(event, value) {
      var obj = {
        label: value[ctrl.formUpdateLabelProperty] || value.name,
        name: ctrl.formUpdateName,
        value: '' + (value[ctrl.formUpdateValueProperty] || value.id),
      };
      if (undefined === _.find(ctrl.formUpdateChoices, function(item) { return item.value === obj.value; })) {
        ctrl.formUpdateChoices.push(obj);
      }
    });

    ctrl.deletedCancel = $rootScope.$on('userDirectory.distribution.deleted', function(event, value) {
      var objectValue = '' + (value[ctrl.formUpdateValueProperty] || value.id);
      _.remove(ctrl.formUpdateChoices, function(item) { return item.value === objectValue; });
    });

    ctrl.updatedCancel = $rootScope.$on('userDirectory.distribution.updated', function(event, value) {
      var obj = {
        label: value[ctrl.formUpdateLabelProperty] || value.name,
        name: ctrl.formUpdateName,
        value: '' + (value[ctrl.formUpdateValueProperty] || value.id),
      };
      var idx = _.findIndex(ctrl.formUpdateChoices, function(item) { return item.value === obj.value; });
      if (-1 < idx) {
        // replace object to force render
        ctrl.formUpdateChoices.splice(idx, 1, obj);
      }
    });

    $scope.$on('$destroy', function(){
      ctrl.createdCancel();
      ctrl.deletedCancel();
      ctrl.updatedCancel();
    });

  }
}) (angular);

