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
      groups: '=groups',
      view: '=',
      load: '=',
      placeholder: '=',
      placeholderAlt: '=',
      label: '=',
      filterSelected: '=?',
      filterSelectedGroups: '=?',
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
  picker.all = [];
  picker.remove = remove;
  picker.add = add;
  picker.transformChip = transformChip;
  picker.selectedItem = null;
  init();

  function init () {
    picker.filterSelected = angular.isUndefined(picker.filterSelected) ? true : !!picker.filterSelected;

    $scope.$watchCollection('picker.users', function (users) {
      picker.ids = _.map(users, 'id');
      lookupIds(users, 'users');
      refreshExpander();
      concatAllRecipients(users);
      if ( angular.isUndefined(picker.users)) {
        picker.users = [];
      }
    });
    $scope.$watchCollection('picker.groups', function (groups) {
      picker.groupIds = _.map(groups, 'id');
      lookupIds(groups, 'groups');
      refreshExpander();
      concatAllRecipients(groups);
      if ( angular.isUndefined(picker.groups)) {
        picker.groups = [];
      }
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
    return  $q(function (resolve) {
      // cancel previous request, and store the new one
      if (pendingGetContact) {
        $timeout.cancel(pendingGetContact);
      }
      pendingGetContact = $timeout(function () {
        var data = { view: picker.view };
        if (picker.filterSelected) {
          data['exclude_ids[]'] = _.map(picker.users, 'id');
        }

        var dataGroup = { view: picker.view };
        if (picker.filterSelected) {
          data['exclude_ids[]'] = _.map(picker.groups, 'id');
        }
        if (angular.isDefined(picker.groups)) {
          resolve($q.all({
              users: Restangular.one('user-directory/users/search').all(query).getList(data),
              groups: Restangular.one('user-directory/groups/search').all(query).getList(dataGroup)
            }
          ).then(function (results) {
            return results.users.concat(results.groups);
          }));
        } else {
          resolve(Restangular.one('user-directory/users/search').all(query).getList(data));
        }
      }, 500);
    });
  }

  function onSelection (groups, users) {
    picker.users.splice(0, picker.users.length);
    Array.prototype.push.apply(picker.users, users);
    picker.groups.splice(0, picker.groups.length);
    Array.prototype.push.apply(picker.groups, groups);
  }


  function refreshExpander() {
    $timeout(function(){
      $scope.$emit('track.height');
    },100, true);

  }

  /**
   * Parses the given collection. If plain IDs are found, they are replaced by
   * user objects, from API.
   *
   * @param  {Array} coll
   * @param  {String} type
   */
  function lookupIds (coll, type) {
    var ids = [];         // ids found
    var positions = [];   // position of those ids

    angular.forEach(coll, function (item, idx) {
      if (angular.isNumber(item)) {
        ids.push(item);
        positions.push(idx);
      }
    });

    if (ids.length) {
      Restangular.all('user-directory/'+ type +'/lookup').getList({ids: ids.join(',')})
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

  function concatAllRecipients(usersOrGroups) {
    angular.forEach(usersOrGroups, function (item) {
      if (angular.isObject(item)) {
        var pos = picker.all.map(function(e) { return e.id; }).indexOf(item.id);
        if (pos === -1) {
          picker.all.push(item);
        }
      }
    });
  }

  function remove(chip) {
    var userPosition = picker.users.map(function(e) { return e.id; }).indexOf(chip.id);
    var groupPosition = picker.groups.map(function(e) { return e.id; }).indexOf(chip.id);
    if( userPosition > -1) {
      picker.users.splice(userPosition, 1);
    }
    if (groupPosition > -1 )  {
      picker.groups.splice(groupPosition, 1);
    }
  }

  function add(chip) {
    if (angular.isDefined(chip.full_name)) {
      picker.users.push(chip);
    } else {
      picker.groups.push(chip);
    }
  }


  function transformChip(chip) {
    // If it is an object, it's already a known chip
    if (angular.isObject(chip)) {
      return chip;
    }

    // Otherwise, create a new one
    return { name: chip, type: 'new' };
  }

}

})(angular);
