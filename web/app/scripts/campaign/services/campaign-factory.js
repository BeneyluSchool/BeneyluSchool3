(function (angular) {
  'use strict';

  /**
   * @ngdoc module
   * @name bns.campaign.campaign
   */
  angular.module('bns.campaign.campaign', [
      'restangular',
    ])

    .factory('Campaign', CampaignFactory)

  ;

  /**
   * @ngdoc service
   * @name Campaign
   * @module bns.campaign.campaign
   *
   * @description
   * Restangular wrapper for Campaign.
   *
   * @requires Restangular
   */
  function CampaignFactory (Restangular) {

    return Restangular.service('campaign');

  }

})(angular);
