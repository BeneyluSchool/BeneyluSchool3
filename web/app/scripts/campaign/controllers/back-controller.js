(function (angular) {
  'use strict';

  angular.module('bns.campaign.backController', [
      'bns.campaign.campaign'
    ])

    .controller('CampaignBackContent', CampaignBackContentController)
    .controller('CampaignBackSidebar', CampaignBackSidebarController)
    .controller('CampaignBackActionbar', CampaignBackActionbarController)
    .factory('campaignBackState', CampaignBackStateFactory)

  ;

  function CampaignBackContentController ($rootScope, $scope, $mdUtil, toast, Restangular, $window, campaignBackState, groupId /*, arrayUtils*/) {

    var ctrl = this;
    ctrl.groupId = groupId;
    ctrl.shared = campaignBackState;
    ctrl.search = '';               // search query
    ctrl.hasSearch = false;
    ctrl.hasFilter = false;

    ctrl.doSearch = $mdUtil.debounce(doSearch, 500);
    ctrl.loadMore = loadMore;
    ctrl.busy = true;


    var searchUrl = Restangular.one('groups', ctrl.groupId).one('campaigns');

    var dataContainer = angular.element('#infiniteList');
    dataContainer.on('scroll', function () {
      /* global Event */
      var scrollEvent = new Event('scroll');
      $window.dispatchEvent(scrollEvent);
    });

    init();

    $rootScope.$on( 'delete' , init);
    $rootScope.$on( 'filter' , doSearch);

    function init() {
      ctrl.busy = false;
      ctrl.items = [];
      ctrl.page = 0;
      ctrl.pages = -1;
      ctrl.loadMore();
    }

    function selectCampaign () {
      $scope.selected = [];
      $scope.toggle = function (campaign, list) {
        var idx = list.indexOf(campaign);
        if (idx > -1) { list.splice(idx, 1);}
        else { list.push(campaign);}


        ctrl.shared.ids = $scope.selected;
      };
      $scope.exists = function (campaign, list) {
        return list.indexOf(campaign) > -1;
      };

    }

    function doSearch(newSearch, oldSearch) {
      if (newSearch === oldSearch) {
        return;
      }

      if (ctrl.shared.status.length === 0) {
        ctrl.shared.status = 'empty';
      }
      if (ctrl.shared.type.length === 0) {
        ctrl.shared.type = 'empty';
      }
      if (!ctrl.search) {
        ctrl.search = '%';
      }

      searchUrl = Restangular.one('groups', ctrl.groupId).one('campaigns')
        .one('search', ctrl.search).one('types', ctrl.shared.type).one('status', ctrl.shared.status);

      if (ctrl.search === '%') {
        delete ctrl.search;
      }

      init();

      ctrl.hasSearch = !!ctrl.search;
      ctrl.hasFilter = ctrl.shared.status !== 'empty' || ctrl.shared.type !== 'empty';
    }


    function loadMore() {
      if (ctrl.busy) { return; }

      if (ctrl.page >= ctrl.pages && ctrl.pages !== -1) {
        return;
      }
      ctrl.busy = true;
      ctrl.page++;
      searchUrl.getList('', {page: ctrl.page} )
      .then(function success(response) {
        if (response.pager) {
          ctrl.pages = response.pager.pages;
        }
        for (var i = 0; i < response.length; i++) {
          ctrl.items.push(response[i]);
          ctrl.numLoaded ++;
        }
      }).catch(function error(response) {
        toast.error('CAMPAIGN.GET_LOG_ERROR');
        throw response;
      })
      .finally(function end() {
        ctrl.busy = false;

        $scope.$watch('ctrl.search', ctrl.doSearch);
        $scope.$watch('ctrl.campaign.id', selectCampaign);
      });
    }
  }

  function CampaignBackSidebarController (Restangular, Routing, $rootScope, $scope, campaignBackState, groupId, toast) {

    var ctrl = this;
    ctrl.groupId = groupId;
    ctrl.credit = undefined;
    ctrl.shared = campaignBackState;
    ctrl.refreshCredit = refreshCredit;
    ctrl.reloadCreditUrl = Routing.generate('BNSAppSpotBundle_front', {'code':'SMS', 'origin' : 'sms'});

    $scope.types = ['SMS', 'EMAIL', 'MESSAGING'];
    $scope.status = ['SENT', 'SCHEDULED', 'PENDING', 'WAITING', 'DRAFT'];

    $scope.$watch('ctrl.campaign.type', selectType);
    $scope.$watch('ctrl.campaign.status', selectStatus);

    init();

    function init() {
      ctrl.busy = true;

      ctrl.refreshCredit();
    }

    function selectType () {
      $scope.selected = [];
      $scope.toggle = function (type, list) {
        var idx = list.indexOf(type);
        if (idx > -1) { list.splice(idx, 1);}
        else { list.push(type);}

        ctrl.shared.type = $scope.selected;
        $rootScope.$emit('filter', ctrl.shared.type );
      };
      $scope.exists = function (type, list) {
        return list.indexOf(type) > -1;
      };
    }

    function selectStatus () {
      $scope.statusSelected = [];
      $scope.toggleStat = function (status, list) {
        var idx = list.indexOf(status);
        if (idx > -1) {
          list.splice(idx, 1);
        }
        else {
          list.push(status);
        }
        ctrl.shared.status = $scope.statusSelected;
        $rootScope.$emit('filter', ctrl.shared.status );
      };
      $scope.existsStat = function (status, list) {
        return list.indexOf(status) > -1;
      };
    }

    function refreshCredit () {
      Restangular.one('groups', ctrl.groupId).all('campaigns').one('credit').get()
        .then(function success(credit) {
          ctrl.credit = credit;
          ctrl.reloadCreditUrl = Routing.generate('BNSAppSpotBundle_front', {'code': 'SMS_' + credit.country, 'origin' : 'sms'});
        })
        .catch(function error(response) {
          toast.error('CAMPAIGN.GET_CREDIT_ERROR');
          throw response;
        })
        .finally (function end() {
          ctrl.busy = false;

        });
    }
  }

  function CampaignBackActionbarController ($rootScope, Restangular, campaignBackState, groupId, $state, toast) {

    var ctrl = this;
    ctrl.groupId = groupId;
    ctrl.shared = campaignBackState;
    ctrl.deleteCampaign = deleteCampaign;
    ctrl.newCampaign = newCampaign;


    function deleteCampaign() {
      ctrl.busy = true;

      Restangular.one('groups', ctrl.groupId).one('campaigns').remove({'ids[]' : ctrl.shared.ids})
      .finally (function end() {
        ctrl.busy = false;
        $rootScope.$emit('delete', ctrl.busy );

        ctrl.shared.ids = [];
      });
    }

    function newCampaign(param) {
      ctrl.busy = true;

      Restangular.one('groups', ctrl.groupId).all('campaigns').post({type: param})
        .then(function success(newCampaign) {
          $state.go('app.campaign.back.edit', { id: newCampaign.id});
        })
        .catch(function error(response) {
          toast.error('CAMPAIGN.CREATE_ERROR');
          throw response;
        })
        .finally (function end() {
          ctrl.busy = false;

        });

    }
  }


  function CampaignBackStateFactory () {

    return {
      ids: [],
      type: [],
      status: []
    };

  }

})(angular);
