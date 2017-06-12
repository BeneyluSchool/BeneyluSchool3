(function (angular) {
'use strict';

angular.module('bns.lsu.templateDetails', [])

  .directive('bnsLsuTemplateDetails', BNSLsuTemplateDetailsDirective)
  .controller('BNSLsuTemplateDetails', BNSLsuTemplateDetailsController)

;

function BNSLsuTemplateDetailsDirective () {

  return {
    restrict: 'E',
    scope: {
      domain: '=bnsDomain',
      template: '=bnsTemplate',
      successCount: '=bnsSuccessCount',
      pendingCount: '=bnsPendingCount',
    },
    templateUrl: 'views/lsu/directives/bns-lsu-template-details.html',
    controller: 'BNSLsuTemplateDetails',
    controllerAs: 'ctrl',
    bindToController: true,
  };

}

function BNSLsuTemplateDetailsController (_, $scope, $element, $mdUtil, Restangular, toast) {

  var ctrl = this;
  ctrl.addDetail = addDetail;
  ctrl.removeDetail = removeDetail;
  ctrl.updateDetail = $mdUtil.debounce(updateDetail, 1000);
  ctrl.getSuggestions = getSuggestions;

  init();

  function init () {
    ctrl.details = _.filter(ctrl.template.template_domain_details, { domain_id: ctrl.domain.id });
    ctrl.suggestions = _.map(ctrl.domain.suggestions, 'label');
  }

  function addDetail () {
    var localDetail = {
      label: '',
    };
    ctrl.details.push(localDetail);

    // focus and open the autocomplete, but wait for ng-repeat to update dom => next tick
    $mdUtil.nextTick(function () {
      var input = $element.find('input').last();
      if (input) {
        input.focus();
      }
    });
  }

  function removeDetail (detail) {
    // still a local object, no need for api call
    if (!detail.id) {
      return _.remove(ctrl.details, detail);
    }

    ctrl.pendingCount++;
    detail.busy = true;

    return Restangular.one('lsu/template-domain-details', detail.id).remove()
      .then(success)
      .catch(error)
      .finally(end)
    ;
    function success () {
      ctrl.successCount++;
      _.remove(ctrl.details, detail);
    }
    function error () {
      toast.error('LSU.FLASH_REMOVE_DETAIL_ERROR');
      detail.busy = false;
    }
    function end () {
      ctrl.pendingCount--;
    }
  }

  function updateDetail (detail, form) {
    if (!(form && form.$valid)) {
      form.$setTouched(true); // make the error message appear even on first interaction with the input
      return;
    }

    var request;
    if (detail.id) {
      request = Restangular.one('lsu/template-domain-details', detail.id).patch;
    } else {
      request = Restangular.one('lsu/templates', ctrl.template.id).all('domain-details').post;
    }
    ctrl.pendingCount++;

    return request({
      lsuDomain: ctrl.domain.id,
      label: detail.label,
    })
      .then(success)
      .catch(error)
      .finally(end)
    ;
    function success (response) {
      detail.id = response.id;
      ctrl.successCount++;
    }
    function error () {
      toast.error('LSU.FLASH_SAVE_DETAIL_ERROR');
    }
    function end () {
      ctrl.pendingCount--;
    }
  }

  function getSuggestions (text) {
    text = _.deburr((text || '').toLowerCase());
    var matches = _.filter(ctrl.suggestions, function (suggestion) {
      return _.deburr(suggestion.toLowerCase()).indexOf(text) > -1;
    });

    return matches;
  }

}

})(angular);
