(function (angular) {
'use strict';

angular.module('bns.main.navbar')


  .directive('bnsNavbarGroupFavorite', BNSNavbarGroupFavoriteDirective)
  .controller('BNSNavbarGroupFavorite', BNSNavbarGroupFavoriteController)

;

/**
 * @ngdoc directive
 * @name bnsNavbarGroupFavorite
 * @module bns.main.navbar
 *
 * @description
 * Displays if given group is favorite, and allows to toggle it
 *
 * ** Attributes **
 *  - `group` (required): the group being examined
 */
function BNSNavbarGroupFavoriteDirective (){

  return {
    scope: {
      group: '=',
    },
    restrict: 'E',
    controller: 'BNSNavbarGroupFavorite',
    controllerAs: 'ctrl',
    bindToController: true,
    template: '<md-button ng-if="ctrl.me.is_adult" ng-click="ctrl.toggleFavorite()" class="md-icon-button">'+
      '<md-tooltip md-direction="bottom">{{\'NAVBAR.LABEL_SET_MAIN_GROUP\'|translate}}</md-tooltip>'+
      '<bns-icon name="ctrl.isFavorite()?\'star\':\'star-empty\'"></bns-icon>'+
    '</md-button>',
  };

}

function BNSNavbarGroupFavoriteController ($rootScope, Users, Groups) {

  var ctrl = this;
  ctrl.isFavorite = isFavorite;
  ctrl.toggleFavorite = toggleFavorite;

  init();

  function init () {
    Users.me().then(function (me) {
      ctrl.me = me;
    });
  }

  function isFavorite() {
    if (!isReady()) {
      return null;
    }

    return ctrl.me.favorite_group_id === ctrl.group.id;
  }

  function toggleFavorite () {
    if (!isReady() || ctrl.isFavorite()) {
      return;
    }

    return Groups.one(ctrl.group.id).one('favorite').patch({})
      .then(function success () {
        $rootScope.$emit('user.favorite_group', ctrl.group);
        ctrl.me.favorite_group_id = ctrl.group.id;
      })
    ;
  }

  function isReady () {
    return ctrl.me && ctrl.group;
  }

}

})(angular);
