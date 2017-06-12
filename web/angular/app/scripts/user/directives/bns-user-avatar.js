'use strict';

angular.module('bns.user.avatar', [
  'bns.core.url',
])

  .directive('bnsUserAvatar', function () {
    return {
      replace: true,
      scope: {
        user: '=bnsUserAvatar',
      },
      controller: 'UserAvatarController',
      controllerAs: 'ctrl',
      bindToController: true,
    };
  })

  .controller('UserAvatarController', function ($scope, $element, $compile, url) {
    var ctrl = this;

    init();

    function init () {
      var template;
      if (ctrl.user.avatar_url) {
        template = '<span class="user-avatar">'+
          '<img ng-src="{{::ctrl.user.avatar_url}}">'+
        '</span>';
      } else {
        template = '<span bns-media-preview class="user-avatar"';
        if (ctrl.user.avatar) {
          template += ' media="ctrl.user.avatar"';
        }

        // add a default avatar, used if no actual media or as fallback if error
        var defaultAvatar = ctrl.user.main_role;
        switch (ctrl.user.main_role) {
          case 'pupil':
            defaultAvatar += '-' + ctrl.user.gender;
            break;
          case 'parent':
          case 'teacher':
            break;
          default:
            defaultAvatar = 'teacher';
        }
        template += ' default-url="\'' + url.image('user/avatars/' + defaultAvatar + '.png') + '\'"';

        template += '></span>';
      }

      $element.html(template);
      var compiledEl = $compile($element.contents())($scope);
      $element.replaceWith(compiledEl);
    }
  })

;
