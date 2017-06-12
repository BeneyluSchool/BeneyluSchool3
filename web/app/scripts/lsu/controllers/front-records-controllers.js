(function (angular) {
'use strict';

angular.module('bns.lsu.frontRecordsControllers', [])

  .controller('LsuFrontRecordsContent', LsuFrontRecordsContentController)

;

function LsuFrontRecordsContentController (_, Restangular, toast, navbar) {

  var ctrl = this;
  ctrl.group = null;
  ctrl.templates = null;
  ctrl.recordsByUser = null;
  ctrl.users = {};
  ctrl.busy = false;

  init();

  function init () {
    return navbar.getOrRefreshGroup()
      .then(function (group) {
        ctrl.group = group;
        loadRecords();
      })
    ;
  }

  function loadRecords () {
    ctrl.busy = true;

    return Restangular.one('groups', ctrl.group.id).all('lsu').getList()
      .then(success)
      .catch(error)
      .finally(end)
    ;
    function success (records) {
      if (!records.length) {
        ctrl.hasNoRecord = true;
      }

      ctrl.recordsByUser = _.groupBy(records, 'user.id');
      angular.forEach(ctrl.recordsByUser, function (userRecords, userId) {
        ctrl.recordsByUser[userId] = _.sortByOrder(userRecords, function (record) {
          return record.lsu_template.started_at;
        }, false);
        ctrl.users[userId] = userRecords[0].user;
      });
    }
    function error () {
      ctrl.error = 'LSU.FLASH_LOAD_RECORDS_ERROR';
    }
    function end () {
      ctrl.busy = false;
    }
  }


}

})(angular);
