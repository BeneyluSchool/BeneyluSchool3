(function (angular) {
'use strict';

angular.module('bns.embed.tour.controllers', [])

  .controller('EmbedTour', EmbedTourController)
  .controller('EmbedTourMenu', EmbedTourMenuController)
  .controller('EmbedTourMenuActivity', EmbedTourMenuActivityController)

;

function EmbedTourController ($sce, item, bottomSheet, Users, $stateParams, _) {

  // -- same as the base controller

  var ctrl = this;
  ctrl.item = item;
  //To have route param for analitics
  //add or replace extra utm_* params
  _.forIn($stateParams, function(value, key) {
    if (angular.isDefined(value) && 0 === key.indexOf('utm_')) {
      var separator = '&';
      if ( -1 === ctrl.item.href.indexOf('?')) {
        separator = '?';
      }
      if ( -1 !== ctrl.item.href.indexOf('?' + key + '=') || -1 !== ctrl.item.href.indexOf('&' + key + '=')) {
        var exp = new RegExp('(' + key + '=).*?(&)');
        ctrl.item.href = ctrl.item.href.replace(exp,'$1' + value + '$2');
      } else {
        ctrl.item.href += separator + key + '=' + value;
      }
    }
  });

  ctrl.item.href = $sce.trustAsResourceUrl(ctrl.item.href);

  // -- custom tour stuff

  ctrl.toggleMenu = toggleMenu;
  ctrl.showActivityMenu = showActivityMenu;

  init();

  function init () {
    return Users.hasCurrentRight('TOUR_ACTIVATION')
      .then(function success (hasRight) {
        ctrl.canManage = hasRight;
      })
    ;
  }

  function toggleMenu () {
    if (ctrl.menuShown) {
      bottomSheet.hide();
    } else {
      ctrl.menuShown = true;
      bottomSheet.show({
        templateUrl: 'views/embed/tour/menu.html',
        controller: 'EmbedTourMenu',
        controllerAs: 'menu',
      })
      .then(function (target) {
        if ('activity' === target) {
          return showActivityMenu();
        }
      })
      .finally(function menuClosed () {
        ctrl.menuShown = false;
      });
    }
  }

  function showActivityMenu () {
    ctrl.menuShown = true;

    return bottomSheet.show({
      templateUrl: 'views/embed/tour/menu-activity.html',
      controller: 'EmbedTourMenuActivity',
      controllerAs: 'menu',
    })
    .finally(function menuClosed () {
      ctrl.menuShown = false;
    });
  }

}

function EmbedTourMenuController (bottomSheet) {

  var menu = this;
  menu.close = bottomSheet.hide;

}

function EmbedTourMenuActivityController (bottomSheet) {

  var menu = this;
  menu.close = bottomSheet.hide;

}

})(angular);
