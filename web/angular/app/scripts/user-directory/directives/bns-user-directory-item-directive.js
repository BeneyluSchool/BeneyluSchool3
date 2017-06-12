'use strict';

angular.module('bns.userDirectory.item', [
  'bns.user.avatar',
  'bns.userDirectory.groupImage',
])

  .directive('bnsUserDirectoryItem', function () {
    return {
      scope: {
        item: '=bnsUserDirectoryItem',
      },
      controller: 'UserDirectoryItemController',
      controllerAs: 'ctrl',
      bindToController: true,
    };
  })

  .controller('UserDirectoryItemController', function ($compile, $element, $scope) {
    var ctrl = this;

    var template = '<span class="user-directory-item">';

    if (ctrl.item && ctrl.item.full_name) {
      // user
      template += '<span bns-user-avatar="ctrl.item"></span> {{ ctrl.item.full_name }}';
    } else if (ctrl.item && ctrl.item.label) {
      // group
      template += '<span bns-group-image="ctrl.item"></span> {{ ctrl.item.label }}';
    }

    template += '</span>';

    $element.html(template);
    $compile($element.contents())($scope);
  })

;
