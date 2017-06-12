(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.lsu.recordDomainRow
 */
angular.module('bns.lsu.recordDomainRow', [])

  .directive('bnsLsuRecordDomainRow', BNSLsuRecordDomainRowDirective)
  .controller('BNSLsuRecordDomainRow', BNSLsuRecordDomainRowController)

;

/**
 * @ngdoc directive
 * @name bnsLsuRecordDomainRow
 * @module bns.lsu.recordDomainRow
 *
 * @description
 * Display directive for a lsu record row.
 *
 * ** Attributes **
 * - `bnsLsuEditable` {=Boolean}: Whether record row is editable. Read-only once
 *                                during initialization, defaults to false.
 * - `bnsLsuRecordDomainRow` {object}: The related LSU domain, complete with its
 *                                     subdomains.
 * - `bnsLsuComment`: Data-bound expression of the row comment.
 * - `bnsLsuPosition`: Data-bound expression of the row position.
 */
function BNSLsuRecordDomainRowDirective () {

  return {
    restrict: 'A',
    scope: {
      domain: '=bnsLsuRecordDomainRow',
      details: '=?bnsLsuDetails',
      comment: '=?bnsLsuComment',
      position: '=?bnsLsuPosition',
      isEditable: '=?bnsLsuEditable',
    },
    templateUrl: 'views/lsu/directives/bns-lsu-record-domain-row.html',
    controller: 'BNSLsuRecordDomainRow',
    controllerAs: 'row',
    bindToController: true,
  };

}

function BNSLsuRecordDomainRowController ($scope, $attrs) {

  var row = this;
  row.isCommon = angular.isDefined($attrs.bnsLsuCommon);
  row.isRoot = 2 === row.domain.tree_level;
  row.nbSubdomains = row.domain.subdomains ? row.domain.subdomains.length : 0;
  row.hasComment = angular.isDefined($attrs.bnsLsuComment);
  row.hasControls = row.isCommon || !(row.isRoot && row.nbSubdomains); // controls are shown for every rows except roots with children
  row.setPosition = setPosition;

  function setPosition (position) {
    if (row.position === position) {
      return (row.position = null);
    }

    row.position = position;
  }

}

})(angular);
