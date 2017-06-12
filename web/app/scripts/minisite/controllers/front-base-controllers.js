(function (angular) {
  'use strict';

  angular.module('bns.minisite.front.baseControllers', [
    'ui.router',
  ])

  .controller('MinisiteFrontBase', MinisiteFrontBaseController)
;


function MinisiteFrontBaseController (_, Routing, $rootScope, $scope, $state, $stateParams, Restangular, toast, global) {
  var ctrl = this;
  var slug = $stateParams.slug;
  $scope.root = {};

  if (global('anonymous')) {
    ctrl.loginUrl = Routing.generate('home');
  }

  Restangular.one('minisite').one(slug).get()
    .then(function success(minisite) {
      angular.forEach(minisite.widgets, function (widget) {
        if (widget.type === 'RSS') {
          widget.busyRSS = true;
          Restangular.one('minisite').one(slug).one('widget', widget.id).get()
            .then(function success(rss) {
              widget.rss = rss;
            })
            .catch(function error(response) {
              console.error(response);
            })
            .finally(function end() {
              widget.busyRSS = false;
            });
        }
      });
      ctrl.minisite = minisite;
      ctrl.group = ctrl.minisite.group;
      $rootScope.title = ctrl.minisite.minisite.title;
      // strip tags like a boss
      $rootScope.description = angular.element(ctrl.minisite.minisite.description).text();

      // redirect to home page when no page is selected
      $scope.$watch(function () {
        // Do not use $stateParams since they do not change during controller lifecycle
        // Test current state since it can be something else than minisite (app change for example)
        return $state.includes('app.minisite.front') && !$state.params.page_slug;
      }, function redirectToHome (needsRedirect) {
        if (needsRedirect) {
          var home = _.find(ctrl.minisite.pages, { is_home: true });
          if (home) {
            return $state.go('app.minisite.front.page', {'slug': slug, page_slug: home.slug});
          }
        }
      });
    })
    .catch(function error(response) {
      console.error(response);
      toast.error('MINISITE.FLASH_LOAD_ERROR');

      throw response;
    })
    .finally(function end() {
      ctrl.busy = false;
    });

}


})(angular);
