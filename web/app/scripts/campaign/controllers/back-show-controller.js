(function (angular) {
  'use strict';

  angular.module('bns.campaign.back.showController', [
      'bns.campaign.campaign',
      'bns.campaign.backController'
    ])

    .controller('CampaignBackShowContent', CampaignBackShowContentController)
    .controller('CampaignBackShowSidebar', CampaignBackShowSidebarController)
    .controller('CampaignBackShowActionbar', CampaignBackShowActionbarController)
    .controller('Dialog', DialogController)

    .factory('campaignBackShowState', CampaignBackShowStateFactory);


  function CampaignBackShowContentController($stateParams, $timeout, $scope, Restangular, campaignBackShowState, toast) {

    var ctrl = this;
    ctrl.shared = campaignBackShowState;
    ctrl.campaignId = $stateParams;

    init();

    function init () {

      ctrl.busy = true;

      Restangular.one('campaigns', ctrl.campaignId.id).get()
        .then(function success(campaign) {
          ctrl.campaign = campaign;
          ctrl.shared.campaign = ctrl.campaign;
          getGroupsDetails();
        })
        .catch(function error(response) {
          toast.error('CAMPAIGN.GET_LOG_ERROR');
          throw response;
        })
        .finally(function end() {
          ctrl.busy = false;
        });
    }

    function getGroupsDetails() {
      ctrl.busyRecipient = true;
      Restangular.one('campaigns', ctrl.campaignId.id).all('details').getList()
        .then(function success(groups) {
          ctrl.shared.groupsAndLists = groups;
        })
        .catch(function error(response) {
          toast.error('CAMPAIGN.DELETE_RECIPIENT_ERROR');
          throw response;
        })
        .finally(function end() {
          ctrl.busyRecipient = false;
          refreshExpander();
        });
    }

    function refreshExpander() {
      $timeout(function(){
        $scope.$broadcast('track.height');
      },100, true);
    }

  }

  function CampaignBackShowSidebarController(campaignBackShowState) {

    var ctrl = this;
    ctrl.shared = campaignBackShowState;

  }

  function CampaignBackShowActionbarController (groupId, campaignBackShowState, Restangular, dialog, $state, toast) {
    var ctrl = this;
    ctrl.groupId = groupId;
    ctrl.showDialog = showDialog;
    ctrl.showSubmitDialog = showSubmitDialog;
    ctrl.copyCampaign = copyCampaign;
    ctrl.shared = campaignBackShowState;

    function copyCampaign() {
      ctrl.busy = true;

      Restangular.one('campaigns', ctrl.shared.campaign.campaign.id).one('copy').post()
        .then(function success(campaign) {
          toast.success('CAMPAIGN.COPY_SUCCESS');
          $state.go('app.campaign.back.edit', { id: campaign.campaign_id});
        })
        .catch(function error(response) {
          toast.error('CAMPAIGN.COPY_ERROR');
          throw response;
        })
        .finally (function end() {
          ctrl.busy = false;
        });
    }

    function showDialog() {
      return dialog.custom({
        templateUrl: 'views/campaign/back/delete-dialog.html',
        locals: {
          close: dialog.hide,
          groupId: ctrl.groupId
        },
        controller: DialogController,
        controllerAs: 'ctrl',
        bindToController: true,
        clickOutsideToClose: true,
      });
    }

    function showSubmitDialog() {
      return dialog.custom({
        templateUrl: 'views/campaign/back/send-dialog.html',
        locals: {
          close: dialog.hide,
          groupId: ctrl.groupId
        },
        controller: DialogController,
        controllerAs: 'ctrl',
        bindToController: true,
        clickOutsideToClose: true,
      });
    }
  }

  function DialogController(groupId, Restangular, $mdDialog, $state, campaignBackShowState, toast) {
    var ctrl = this;
    ctrl.groupId = groupId;
    ctrl.shared = campaignBackShowState;
    ctrl.deleteCampaign = deleteCampaign;
    ctrl.submit = submit;

    function deleteCampaign() {
      ctrl.busy = true;

      Restangular.one('groups', ctrl.groupId).one('campaigns').remove({'ids[]': ctrl.shared.campaign.campaign.id})
        .finally (function end() {
          ctrl.busy = false;
          $mdDialog.hide();
          $state.go('app.campaign.back.list');
        });
    }

    function submit() {
      ctrl.busy = true;
      var data = {
        name : ctrl.shared.campaign.campaign.name,
        title : ctrl.shared.campaign.campaign.title,
        message : ctrl.shared.campaign.campaign.message,
        scheduled_at : ctrl.shared.campaign.campaign.scheduled_at
      };


      Restangular.one('campaigns', ctrl.shared.campaign.campaign.id).patch(data)
        .then(function success() {
          Restangular.one('campaigns', ctrl.shared.campaign.campaign.id).all('send').post()
            .then(function success() {
              toast.success('CAMPAIGN.SEND_SUCCESS');
            })
            .catch(function error(response) {
              toast.error('CAMPAIGN.SEND_ERROR');
              throw response;
            });
        })
        .catch(function error(response) {
          toast.error('CAMPAIGN.FORM_ERROR');
          $mdDialog.hide();
          ctrl.shared.error = response.data.errors.children;
          throw response;
        })
        .finally (function end() {
          ctrl.busy = false;
          $mdDialog.hide();
          $state.go('app.campaign.back.show', { id: ctrl.shared.campaign.campaign.id}, {
            reload: true
          });
        });
    }
  }


    function CampaignBackShowStateFactory () {

    return {
      campaign: '',
      groupsAndLists: [],
      error: '',
      user: ''
    };
  }

})(angular);
