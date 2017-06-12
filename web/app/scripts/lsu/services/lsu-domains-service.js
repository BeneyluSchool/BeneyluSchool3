(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.lsu.domains
 */
angular.module('bns.lsu.domains', [])

  .service('lsuDomains', LsuDomainsService)

;

/**
 * @ngdoc service
 * @name lsuDomains
 * @module bns.lsu.domains
 *
 * @description
 * Handles LSU domains manipulations.
 *
 * @requires _
 * @requires Restangular
 */
function LsuDomainsService (_, Restangular) {

  var lsuDomains = {
    version: 'v2016',
    getByCycle: getByCycle,
    filterByDetails: filterByDetails,
  };

  return lsuDomains;

  function getByCycle (cycle, version) {
    return Restangular.all('lsu/domains').getList({
      cycle: cycle,
      version: version || lsuDomains.version,
    })
      .then(success)
    ;

    function success (allDomains) {
      // keep only domains of the relevant cycle and version
      allDomains = _.sortBy(_.filter(allDomains, {
        cycle: cycle,
        version: version || lsuDomains.version
      }), 'tree_left');

      // start with root domains
      var domains = _.filter(allDomains, function (domain) {
        return domain.tree_level === 2 && domain.code;
      });

      // for each root domain, get all its subdomains
      angular.forEach(domains, function (domain) {
        domain.subdomains = _.filter(allDomains, function (d) {
          return d.code && d.tree_level === 3 && d.tree_left > domain.tree_left && d.tree_right < domain.tree_right;
        });
      });

      return domains;
    }
  }

  /**
   * Keeps only domains that have at least one custom detail.
   *
   * @param domains {=Array} Hierarchy of domains/subdomains.
   * @param details {=Object} Map of details by domain id
   */
  function filterByDetails (domains, details) {
    _.remove(domains, function (domain) {
      if (domain.subdomains.length) {
        _.remove(domain.subdomains, function (subdomain) {
          return !details[subdomain.id];
        });
        return !domain.subdomains.length;
      } else {
        return !details[domain.id];
      }
    });
  }

}

})(angular);
