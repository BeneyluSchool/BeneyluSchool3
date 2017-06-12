'use strict';

angular

  .module('bns.userDirectory.editableList', [
    'bns.core.url',
    'bns.core.collectionMap',
    'bns.userDirectory.users',
    'bns.userDirectory.item',
  ])

  /**
   * @ngdoc directive
   * @name bns.userDirectory.editableList
   * @kind function
   *
   * @requires url
   *
   * @return {Object} the bnsUserDirectoryEditableList directive
   */
  .directive('bnsUserDirectoryEditableList', function (url) {
    return {
      templateUrl: url.view('user-directory/directives/bns-user-directory-editable-list.html'),
      scope: {
        source: '=bnsUserDirectoryEditableList',
        ids: '=',
        store: '=',
        state: '=',
        configurator: '=',
        emptyText: '@',
        wholeSelector: '=',
        editable: '=',
        view: '=',
      },
      compile: compile,
      controller: 'UserDirectoryEditableListController',
      controllerAs: 'ctrl',
      bindToController: true,
    };

    function compile (element, attrs) {
      if (angular.isUndefined(attrs.editable)) {
        attrs.editable = 'true';
      }
    }
  })

  .controller('UserDirectoryEditableListController', function ($injector, $scope, _, CollectionMap) {
    var ctrl = this;

    ctrl.collection = null;
    ctrl.display = display;
    ctrl.remove = remove;
    ctrl.busy = false;
    ctrl.dataStore = null;
    ctrl.isLocked = isLocked;

    if (ctrl.state) {
      ctrl.state.busy = false;
    }

    init();

    function init () {
      if (ctrl.source) {
        // simply create a collection from source array
        ctrl.collection = new CollectionMap(ctrl.source);
      } else if (ctrl.ids) {
        // load the configured datastore
        ctrl.dataStore = $injector.get(ctrl.store);

        // configure the store
        (ctrl.configurator || angular.noop)(ctrl.dataStore);

        // maintain both a new collection and the ids array
        ctrl.collection = new CollectionMap([]);

        // ids have changed: update the collection
        $scope.$watchCollection('ctrl.ids', function (ids) {
          ctrl.busy = true;
          if (ctrl.state) {
            ctrl.state.busy = true;
          }
          ctrl.dataStore.lookup(ids, ctrl.view).then(function (items) {
            ctrl.collection.reset();
            ctrl.collection.addc(items);
          }).finally(function () {
            ctrl.busy = false;
            if (ctrl.state) {
              ctrl.state.busy = false;
            }
          });
        });
      }

      if (ctrl.state) {
        ctrl.state.ready = true;
      }
    }

    function display (item) {
      return item.full_name || item.label;
    }

    function remove (item) {
      if (ctrl.isLocked(item)) {
        return;
      }

      // sync ids
      if (ctrl.ids) {
        _.pull(ctrl.ids, item.id);
      }

      ctrl.collection.remove(item);
    }

    function isLocked (item) {
      if (ctrl.state && ctrl.state.locked) {
        return ctrl.state.locked[item.id] || _.contains(ctrl.state.locked, item.id);
      }

      return false;
    }
  })

;
