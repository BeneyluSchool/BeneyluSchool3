(function (angular) {
'use strict';

angular.module('bns.main.userPicker', [])

  .directive('bnsUserPicker', BNSUserPickerDirective)
  .controller('BNSUserPicker', BNSUserPickerController)

;

function BNSUserPickerDirective () {

  return {
    restrict: 'E',
    scope: {
      users: '=',
      view: '=',
      load: '=',
      placeholder: '=',
      placeholderAlt: '=',
    },
    templateUrl: 'views/main/user-picker/bns-user-picker.html',
    controller: 'BNSUserPicker',
    controllerAs: 'picker',
    bindToController: true,
  };

}

function BNSUserPickerController (_, $scope, $q, $timeout, Restangular) {

  var picker = this;
  picker.getContacts = getContacts;
  picker.onSelection = onSelection;

  init();

  function init () {
    $scope.$watchCollection('picker.users', function (users) {
      picker.ids = _.map(users, 'id');
      lookupIds(users);
    });

    $scope.$watch('picker.load', function (load) {
      if (load && load.length) {
        Restangular.one('user-directory/users/lookup', {ids: load}).getList()
          .then(function success (users) {
            picker.users.splice(0, picker.users.length);
            Array.prototype.push.apply(picker.users, users);
          })
        ;
      }
    });
  }

  var pendingGetContact = null;
  function getContacts (query) {
    // wrap the debounced API calls in a promise
    return $q(function (resolve) {
      // cancel previous request, and store the new one
      if (pendingGetContact) {
        $timeout.cancel(pendingGetContact);
      }
      pendingGetContact = $timeout(function () {
        resolve(Restangular.one('user-directory/users/search').all(query).getList({ view: picker.view }));
      }, 500);
    });
  }

  function onSelection (groups, users) {
    picker.users.splice(0, picker.users.length);
    Array.prototype.push.apply(picker.users, users);
  }

  /**
   * Parses the given collection. If plain IDs are found, they are replaced by
   * user objects, from API.
   *
   * @param  {Array} coll
   */
  function lookupIds (coll) {
    var ids = [];         // ids found
    var positions = [];   // position of those ids

    angular.forEach(coll, function (item, idx) {
      if (angular.isNumber(item)) {
        ids.push(item);
        positions.push(idx);
      }
    });

    if (ids.length) {
      Restangular.all('user-directory/users/lookup').getList({ids: ids.join(',')})
        .then(function success (users) {
          angular.forEach(users, function (user) {
            var found = ids.indexOf(user.id);
            if (found > -1) {
              coll.splice(positions[found], 1, user);
            }
          });
        })
      ;
    }

  }

}

})(angular);
