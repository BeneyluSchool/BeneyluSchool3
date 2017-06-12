(function (angular) {
'use strict';

angular.module('bns.main.loginController', [])

  .controller('LoginController', LoginController)
  .controller('RegisterController', RegisterController)

;

/**
 * @ngdoc controller
 * @name LoginController
 * @module bns.main.loginController
 *
 * @description
 * Main controller on the login page
 *
 * @requires $cookies
 * @requires dialog
 */
function LoginController ($cookies, dialog) {
  var ctrl = this;
  ctrl.showRegisterDialog = showRegisterDialog;
  ctrl.acceptCookies = acceptCookies;

  function showRegisterDialog ($event) {
    return dialog.custom({
      templateUrl: 'teacher-register-dialog.html',
      targetEvent: $event,
      clickOutsideToClose: true,
      controller: 'RegisterController',
      controllerAs: 'ctrl',
      focusOnOpen: false,
    });
  }

  function acceptCookies () {
    var expirationDate = new Date();
    expirationDate.setTime(expirationDate.getTime() + (365*24*60*60*1000));
    $cookies.put('hide-cookies-alert', '1', {
      expires: expirationDate,
    });
    ctrl.hasAcceptedCookies = true;
  }

}

/**
 * @ngdoc controller
 * @name RegisterController
 * @module bns.main.loginController
 *
 * @description
 * Controller of the register dialog on login page.
 *
 * @requires $http
 * @requires $window
 * @requires $document
 * @requires toast
 */
function RegisterController ($http, $window, $document, $mdDialog, toast) {

  var ctrl = this;
  ctrl.handleSubscription = handleSubscription;
  ctrl.busy = false;
  ctrl.abort = function () { return $mdDialog.cancel(); };

  function handleSubscription (event) {
    event.preventDefault();
    var form = $document[0].getElementById('user_registration_form');
    var $form = angular.element(form);
    var origin = $form.attr('data-origin');
    ctrl.busy = true;

    return $http.post($form.attr('action'), {
      email: ctrl.email,
      origin: origin,
    }, {
      transformResponse: prependTransform($http.defaults.transformResponse, normalizeAPIResponse),
    })
      .then(success)
      .catch(error)
      .finally(end)
    ;

    function success (response) {
      $window.location = response.data.url;
    }

    function error (response) {
      if (response.data && response.data.message) {
        toast.error(response.data.message);
      }
    }

    function end () {
      ctrl.busy = false;
    }

    function prependTransform (defaults, transform) {
      // ensure we're working on an array copy of the original defaults, to have
      // this transformation only on the current request
      defaults = angular.isArray(defaults) ? defaults.slice() : [defaults];
      defaults.unshift(transform);

      return defaults;
    }

    function normalizeAPIResponse (response) {
      try {
        // proper json object, do nothing
        JSON.parse(response);
      } catch (e) {
        // string of text sent inline, use it as message
        return {
          message: response,
        };
      }

      return response;
    }

  }

}

}) (angular);
