(function (angular) {
'use strict';

angular.module('bns.lsu.record', [
  'bns.lsu.recordDomainRow',
  'bns.lsu.domains',
  'bns.lsu.editRecordHeaderController',
])

  .directive('bnsLsuRecord', BnsLsuRecordDirective)
  .controller('BnsLsuRecord', BnsLsuRecordController)

;

/**
 * @ngdoc directives
 * @name bnsLsuRecord
 * @module bns.lsu.record
 *
 * @description
 * Displays a LSU record, for consultation or edition.
 *
 * ** Attributes **
 *  - `bnsLsuEditable` {=Boolean}: Whether record view is editable. Read-only
 *                                 once during initialization, defaults to
 *                                 false.
 *  - `bnsLsuRecord` {=Object}: The LSU Record object.
 *  - `bnsLsuModel` {=Object}: The model object to hold simple form data.
 *  - `bnsLsuDomains` {=Object}: Collection of LSU domains of the template of
 *                               the record.
 *  - `bnsLsuCommons` {=Object}: Collection of LSU domains of the common groud
 *                               of the template of the record.
 *
 * ** Output attributes **
 *  - `bnsLsuForm` {=Object}: The ng form object, to access form state. Managed
 *                            internally by the directive, should be used only
 *                            to read data.
 *  - `bnsLsuDetails` {=Object}: Collection of LSU details of the template of
 *                               the record. Managed internally by the
 *                               directive, should be used only to read data.
 *  - `bnsLsuPositions` {=Object}: Map of LSU positions of the record.
 *                                 Managed internally by the directive, should
 *                                 be used only to read data.
 *  - `bnsLsuComments` {=Object}: Collection of LSU comments of the record.
 *                                Managed internally by the directive, should be
 *                                used only to read data.
 */
function BnsLsuRecordDirective () {

  return {
    restrict: 'E',
    scope: {
      isEditable: '=bnsLsuEditable',
      record: '=bnsLsuRecord',
      model: '=bnsLsuModel',
      template: '=bnsLsuTemplate',
      domains: '=bnsLsuDomains',
      commons: '=bnsLsuCommons',
      form: '=?bnsLsuForm',
      details: '=?bnsLsuDetails',
      positions: '=?bnsLsuPositions',
      comments: '=?bnsLsuComments',
    },
    templateUrl: 'views/lsu/directives/bns-lsu-record.html',
    controller: 'BnsLsuRecord',
    controllerAs: 'ctrl',
    bindToController: true,
  };

}

function BnsLsuRecordController ($scope, $attrs, $rootScope, moment, dialog, lsuDomains) {

  $scope.moment = moment; // convenience for displaying dates

  var LSU_ACHIEVEMENTS = {
    0: 'NOT',
    1: 'PARTIAL',
    2: 'SUCCESS',
    3: 'OVERSTEP',
  };


  var ctrl = this;
  ctrl.allCourses = ['PAR_CIT', 'PAR_ART', 'PAR_SAN'];
  ctrl.allAccompanyingConditions = [
    { value: 'PAP',   label: 'LSU.LABEL_PAP' },
    { value: 'PPS',   label: 'LSU.LABEL_PPS' },
    { value: 'UPE2A', label: 'LSU.LABEL_UPE2A' },
    { value: 'PAI',   label: 'LSU.LABEL_PAI' },
    { value: 'RASED', label: 'LSU.LABEL_RASED' },
    { value: 'ULIS',  label: 'LSU.LABEL_ULIS' },
    { value: 'PPRE',  label: 'LSU.LABEL_PPRE' },
  ];
  ctrl.hasCourse = hasCourse;
  ctrl.hasPPREModel = hasPPREModel;
  ctrl.hasPPRE = hasPPRE;
  ctrl.showEditRecordHeaderDialog = showEditRecordHeaderDialog;

  init();

  function init () {
    setupDataMapping();
    setupWatchers();
  }

  function setupDataMapping () {
    // 1. update data mapping on record change
    // 2. unregister this watcher as soon as the state has changed
    // 3. unregister the rootscope unregisterer when this scope is destroyed
    $scope.$on('$destroy', $rootScope.$on('$stateChangeSuccess', $scope.$watch('ctrl.record', mapRecordData)));
    $scope.$on('$destroy', $rootScope.$on('$stateChangeSuccess', $scope.$watch('ctrl.template', mapTemplateData)));
  }

  function setupWatchers () {
    $scope.$watch('ctrl.details', filterDomains);
    $scope.$watch('ctrl.domains', filterDomains);
  }

  function mapRecordData () {
    if (!ctrl.record) {
      return;
    }
    ctrl.comments = {};
    angular.forEach(ctrl.record.lsu_comments, function (comment) {
      ctrl.comments[comment.lsu_domain.id] = comment.comment;
    });
    ctrl.positions = {};
    angular.forEach(ctrl.record.lsu_positions, function (position) {
      ctrl.positions[position.lsu_domain.id] = LSU_ACHIEVEMENTS[position.achievement];
    });
  }

  function mapTemplateData () {
    if (!ctrl.template) {
      return;
    }
    ctrl.details = {};
    angular.forEach(ctrl.template.template_domain_details, function (detail) {
      if (!ctrl.details[detail.domain_id]) {
        ctrl.details[detail.domain_id] = [];
      }
      ctrl.details[detail.domain_id].push(detail);
    });
  }

  function filterDomains () {
    if (!(ctrl.details && ctrl.domains)) {
      return;
    }

    lsuDomains.filterByDetails(ctrl.domains, ctrl.details);
  }

  function hasCourse () {
    var has = false;
    if (ctrl.record) {
      angular.forEach(ctrl.record.data, function (value, key) {
        if (ctrl.allCourses.indexOf(key) > -1 && value) {
          has = true;
        }
      });
    }
    if (ctrl.template) {
      angular.forEach(ctrl.template.data, function (value, key) {
        if (ctrl.allCourses.indexOf(key) > -1 && value) {
          has = true;
        }
      });
    }

    return has;
  }

  function hasPPREModel () {
    return ctrl.model.accompanyingCondition && ctrl.model.accompanyingCondition.indexOf('PPRE') > -1;
  }

  function hasPPRE () {
    return ctrl.record.accompanying_condition && ctrl.record.accompanying_condition.indexOf('PPRE') > -1;
  }

  function showEditRecordHeaderDialog ($event) {
    return dialog.custom({
      templateUrl:'views/lsu/edit-record-header-dialog.html',
      controller: 'LsuEditRecordHeader',
      controllerAs: 'ctrl',
      bindToController: true,
      locals: {
        record: ctrl.record,
      },
      targetEvent: $event,
      clickOutsideToClose: true,
    });
  }

}

})(angular);
