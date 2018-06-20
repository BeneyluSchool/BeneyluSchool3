(function (angular) {
  'use strict';

  /**
   * @ngdoc module
   * @name bns.mediaLibrary.mediaPreview
   */
  angular.module('bns.main.autoLoginBox', [
    'bns.components.keyboard'
  ])

    .directive('bnsAutoLoginBox', BNSAutoLoginBoxDirective)
    .controller('BNSAutoLoginBox', BNSAutoLoginBoxController)
    // .config(BNSAutoLoginConfig)

  ;

  function BNSAutoLoginBoxDirective() {
    return {
      scope: {
        url: '=',
        samlProviders: '=?',
        isMobile: '=?'
      },
      link: function (scope, element, attrs, ctrl) {
        ctrl.init(element);
      },
      templateUrl: 'views/main/directives/bns-auto-login-box.html',
      controller: 'BNSAutoLoginBox',
      controllerAs: 'ctrl',
      bindToController: true,
    };
  }

  function BNSAutoLoginBoxController($scope, $cookies, $window, $http, $httpParamSerializer, $timeout, parameters, global, toast) {
    var ctrl = this;
    ctrl.element = null;
    ctrl.busy = true;
    ctrl.showLoginForm = false;
    ctrl.showNoCookieLogin = false;
    ctrl.noCookie = false;
    ctrl.form = {
      _username: '',
      _lastname: '',
      _password: '',
      _remember_me: false,
      login: 'fullname',
      _csrf_token: '',
    };
    ctrl.locale = global('locale') || 'fr';
    ctrl.loginUrl = null; // provided by auth
    ctrl.forgotPasswordUrl = parameters.app_base_path + '/gestion/mot-de-passe/reinitialisation';
    ctrl.forgotPasswordIncentive = false;
    ctrl.noCookieUrl = parameters.app_base_path + '/cookies';
    ctrl.disconnectUrl = parameters.app_base_path + '/disconnect';

    ctrl.init = init;
    ctrl.submitLogin = submitLogin;
    ctrl.openLoginWindow = openLoginWindow;
    ctrl.autoFocus = autoFocus;
    ctrl.samlAuth = samlAuth;

    function init(element) {
      ctrl.element = element;
      if (!checkCookiesAllowed()) {
        ctrl.noCookie = true;
        ctrl.busy = false;
      } else {
        tryLoginAuth();
      }
    }

    function autoFocus(){
      $scope.$emit('bns.autofocus');
    }

    function submitLogin() {
      ctrl.busy = true;

      var data = {
        '_username': ctrl.form._username || '',
        '_lastname': 'fullname' === ctrl.form.login ? (ctrl.form._lastname || '') : null,
        '_password': ctrl.form._password || '',
        '_csrf_token': ctrl.form._csrf_token || '',
        '_remember_me': ctrl.form._remember_me ? true : null,
      };

      $http({
        method: 'POST',
        url: ctrl.loginUrl,
        withCredentials: true,
        skipRedirect: true,
        data: $httpParamSerializer(data),
        params: {
          '_locale' : ctrl.locale,
          '_xhr_call': 1,
        },
        // headers: { 'X-Requested-With' :'XMLHttpRequest'}
        headers: {'Content-Type': 'application/x-www-form-urlencoded'}
      })
        .then(function success(response) {
          ctrl.url = response.data.redirect_url;
          tryLoginAuth();
        }, function error(response) {
          // empty password field
          ctrl.form._password = '';
          console.error('login error');
          toast.error({
            content: response.data.error_message,
            parent: ctrl.element,
            hideDelay: 8000,
          });
          ctrl.forgotPasswordIncentive = true;
          ctrl.showLoginForm = true;
          ctrl.busy = false;
        })
      ;
    }

    function tryLoginAuth() {
      ctrl.busy = true;
      ctrl.showLoginForm = false;

      $http({
        method: 'GET',
        url: ctrl.url,
        withCredentials: true,
        skipRedirect: true,
        params: {
          '_locale' : ctrl.locale,
          '_xhr_call' : 1,
        },
        // NO X-Requested-With header to prevent Options preflight
        // headers: { 'X-Requested-With' :'XMLHttpRequest'}
      })
        .then(function success(response) {
          loginApp(response.data.redirect_url);

        }, function error(response) {
          if (response.status === 401) {
            ctrl.showLoginForm = true;
            ctrl.busy = false;
            if (response.data.csrf_token) {
              ctrl.form._csrf_token = response.data.csrf_token;
            }
            if (response.data.login_url) {
              ctrl.loginUrl = response.data.login_url;
            }
            autoFocus();
          } else if (response.status === 400 && response.data && response.data.error === 'no-cookie') {
            var expirationDate = new Date();
            expirationDate.setTime(expirationDate.getTime() + (24*60*60*1000));
            var path = parameters.app_base_path || '/';
            $cookies.put('no-3rd-cookie', '1', {
              'expire': expirationDate,
              'path': path,
            });
            ctrl.showLoginForm = false;
            ctrl.showNoCookieLogin = true;
            ctrl.busy = false;
          } else {
            console.error('login auth error', response);
            // TODO handle this case
            // add option to try again
            toast.error('Login Error');
            ctrl.busy = false;
          }

        })
      ;
    }

    function loginApp(loginUrl) {
      ctrl.busy = true;
      ctrl.showLoginForm = false;

      $http({
        method: 'GET',
        url: loginUrl,
        skipRedirect: true,
        // withCredentials: true,
        headers: { 'X-Requested-With' :'XMLHttpRequest'}
      })
        .then(function success(response) {
          if (response.data && response.data.redirect_url) {
            var url = response.data.redirect_url + '';
            $window.location = url + (-1 !== url.indexOf('?') ? '&' : '?' ) + '_direct_call=1';
          } else {
            toast.error({
              content:'ERROR',
              parent: ctrl.element,
            });
            // force disconnect
            $timeout(function() {
              $window.location = ctrl.disconnectUrl;
            }, 3000);
          }

        }, function error(response) {
          if (response.data && response.data.message) {
            toast.error({
              content: response.data.message,
              parent: ctrl.element,
            });
          }
          // force disconnect
          $timeout(function() {
            $window.location = ctrl.disconnectUrl;
          }, 3000);
        })
      ;
    }

    function openLoginWindow() {
      $window.open(ctrl.url, 'login-box', 'width=500,height=400,scrollbars,resizable,left=' + $window.screenX);
    }

    function samlAuth(samlidp) {
      // auto login in full page
      $window.location = ctrl.url + '&_samlidp=' + samlidp;
    }

    function checkCookiesAllowed() {
      // TODO create a service / cache response
      try {
        // Create cookie
        $window.document.cookie = 'cookietest=1';
        var ret = $window.document.cookie.indexOf('cookietest=') !== -1;
        // Delete cookie
        $window.document.cookie = 'cookietest=1; expires=Thu, 01-Jan-1970 00:00:01 GMT';

        return ret;
      } catch (e) {
        return false;
      }
    }
  }

  // function BNSAutoLoginConfig ($bnsKeyboardProvider) {
  //   $bnsKeyboardProvider.addLayout('beneylu', {
  //     'name': 'beneylu', 'keys': [
  //       [['1', '1'], ['2', '2'], ['3', '3'], ['4', '4'], ['5', '5'], ['6', '6'], ['7', '7'], ['8', '8'], ['9', '9'], ['0', '0']],
  //       [['A'], ['Z','Z'], ['E','E'], ['R','R'], ['T','T'], ['Y','Y'], ['U','U'], ['I','I'], ['O','O'], ['P','P'], ['Bksp', 'Bksp']],
  //       [['Q', 'Q'], ['S', 'S'], ['D', 'D'], ['F', 'F'], ['G', 'G'], ['H', 'H'], ['J', 'J'], ['K', 'K'], ['L', 'L'], ['M', 'M'] ],
  //       [['W','W'], ['X','X'], ['C','C'], ['V','V'], ['B','B'], ['N','N'], ['Spacer'], ['Spacer'], ['-'], ['\''] ],
  //       [[' ', ' ', ' ', ' '] ]
  //     ], 'lang': ['fr']
  //   });
  //
  //   $bnsKeyboardProvider.defaultLayout('beneylu');
  // }

})(angular);
