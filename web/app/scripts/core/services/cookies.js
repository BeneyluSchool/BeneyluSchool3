(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.core.cookies
 */
angular.module('bns.core.cookies', [])

  .constant('BNS_3RD_PARTY_COOKIES_CHECK_DOMAIN', 'https://3p.bns.ovh/init.html')
  .constant('BNS_3RD_PARTY_COOKIES_STORAGE_KEY', 'bns-3pc-check')
  .factory('bnsCookies', BNSCookiesFactory)
  .directive('bns3pcSrc', BNS3pcSrcDirective)

;

/**
 * @ngdoc service
 * @name bnsCookies
 * @module bns.core.cookies
 *
 * @description
 * Handles cookies checks.
 *
 * ** Methods **
 * - `supports3rdParty` {Promise}: Returns a promise that is resolved if browser
 *                                 supports 3rd party cookies, and fails
 *                                 otherwise. Test result is persisted for 24
 *                                 hours.
 *
 * @requires $cookies
 * @requires $q
 * @requires $timeout
 * @requires $window
 * @requires moment
 * @requires BNS_3RD_PARTY_COOKIES_CHECK_DOMAIN
 * @requires BNS_3RD_PARTY_COOKIES_STORAGE_KEY
 */
function BNSCookiesFactory ($cookies, $q, $timeout, $window, moment, BNS_3RD_PARTY_COOKIES_CHECK_DOMAIN, BNS_3RD_PARTY_COOKIES_STORAGE_KEY) {

  var bnsCookies = {
    _promise: null,
    _has3pc: null,
    supports3rdParty: supports3rdParty,
  };
  var hasInjectedIframe = false;
  var thirdPartyCheckDeferred;

  setup();

  return bnsCookies;

  function setup () {
    // setup a promise that will be resolved (or rejected) upon third party
    // cookies check completion
    thirdPartyCheckDeferred = $q.defer();
    bnsCookies._promise = thirdPartyCheckDeferred.promise;

    // read previously stored value, if any
    var storedValue = $cookies.getObject(BNS_3RD_PARTY_COOKIES_STORAGE_KEY);
    if (undefined !== storedValue) {
      setHasThirdPartyCookies(storedValue);
    }

    // listen to iframe check results
    angular.element($window).on('message', handleMessage);

    function handleMessage (event) {
      setHasThirdPartyCookies(event.originalEvent.data === 'BNS:3PC:YES', true);
    }

    function setHasThirdPartyCookies (value, persist) {
      bnsCookies._has3pc = !!value;
      if (persist) {
        var expirationDate = moment().add(24, 'h').toDate();
        $cookies.put(BNS_3RD_PARTY_COOKIES_STORAGE_KEY, bnsCookies._has3pc, {expires: expirationDate});
      }

      if (bnsCookies._has3pc) {
        thirdPartyCheckDeferred.resolve(true);
      } else {
        thirdPartyCheckDeferred.reject(false);
      }
    }
  }

  function supports3rdParty () {
    if (!hasInjectedIframe && null === bnsCookies._has3pc) {
      insertIframe(BNS_3RD_PARTY_COOKIES_CHECK_DOMAIN);
      hasInjectedIframe = true;
    }

    return bnsCookies._promise;
  }

  function insertIframe (domain) {
    angular.element('body').append('<iframe src="'+domain+'" style="display:none" />');

    // auto fail the promise after some delay, if iframe check could not load
    $timeout(function () {
      thirdPartyCheckDeferred.reject(false);
    }, 10000);
  }

}

/**
 * @ngdoc directive
 * @name bns3pcSrc
 * @module bns.core.cookies
 *
 * @description
 * A `ng-src`-like attribute directive that checks for third party cookies
 * support before settings the actual src attributes, and fallback to a button
 * opening the link in a new browser tab.
 *
 * ** Attributes **
 * - `bns3pcSrc`: {String} Interpolatable url.
 * - `bns3pcFallbackText`: {String} Interpolatable text to display in the
 *                                  fallback button.
 * - `bns3pcForceFallback`: {Button} Whether to force fallback, as if 3rd party
 *                                   cookies were not supported. Defaults to
 *                                   false.
 *
 * @example
 * <iframe bns-3pc-src="https://mydomain.com"></iframe>
 *
 * @requires $compile
 * @requires $log
 * @requires bnsCookies
 */
function BNS3pcSrcDirective ($compile, $log, bnsCookies) {

  var FALLBACK_BTN_TEMPLATE = '<md-button href="%src%" target="_blank" class="md-raised">' +
      '<md-icon>open_in_new</md-icon>' +
      '<span>%text%</span>' +
    '</md-button>';

  return {
    restrict: 'A',
    link: postLink,
  };

  function postLink (scope, element, attrs) {
    if (element[0].tagName !== 'IFRAME') {
      return $log.warn('Third party cookies check must be performed on an iframe element');
    }

    attrs.$observe('bns3pcSrc', function (value) {
      if (!value) {
        return;
      }

      if (scope.$eval(attrs.bns3pcForceFallback)) {
        return addFallbackBtn(value);
      }

      bnsCookies.supports3rdParty()
        .then(function () {
          attrs.$set('src', value);
        })
        .catch(function () {
          addFallbackBtn(value);
        })
      ;
    });

    function addFallbackBtn (src) {
      var btnText = attrs.bns3pcFallbackText || 'Open in new tab';
      var btn = angular.element(FALLBACK_BTN_TEMPLATE.replace('%src%', src).replace('%text%', btnText));
      btn = $compile(btn)(scope);
      var container = angular.element('<div></div>').attr({
        class: (element.attr('class')||'') + ' bns-iframe-3pc-fallback',
      });
      container.append(btn);
      element.replaceWith(container);
    }
  }

}

})(angular);
