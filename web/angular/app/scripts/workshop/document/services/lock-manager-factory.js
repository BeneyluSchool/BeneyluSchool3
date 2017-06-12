'use strict';

angular.module('bns.workshop.document.lockManager', [
  'bns.core.collectionMap',
  'bns.workshop.restangular',
])

  .factory('workshopDocumentLockManager', function (_, CollectionMap, WorkshopRestangular) {
    var service = {
      _locks: null,
      _widgetGroups: null,
      init: init,
      add: add,
      remove: remove,
      getWidgetGroupLock: getWidgetGroupLock,
      isWidgetGroupLockedForUser: isWidgetGroupLockedForUser,
      isPageLockedForUser: isPageLockedForUser,
    };

    return service;

    /**
     * Initializes the service, for the given locks and widgetgroups
     * collections.
     *
     * @param {Array} lockCollection
     * @param {Array} widgetGroupCollection
     */
    function init (lockCollection, widgetGroupCollection) {
      service._locks = new CollectionMap(lockCollection, 'widget_group_id');
      service._widgetGroups = widgetGroupCollection;
    }

    /**
     * Adds a lock to the given WidgetGroup.
     *
     * @param {Object} widgetGroup
     * @returns {Object} A promise
     */
    function add (widgetGroup) {
      return WorkshopRestangular.one('widget-groups', widgetGroup.id).all('lock').post()
        .catch(function (response) {
          console.error('[POST widget-group lock]', response);

          throw 'WORKSHOP.DOCUMENT.ADD_LOCK_ERROR';
        })
      ;
    }

    /**
     * Removes a lock from the given WidgetGroup.
     *
     * @param {Object} widgetGroup
     * @returns {Object} A promise
     */
    function remove (widgetGroup) {
      return WorkshopRestangular.one('widget-groups', widgetGroup.id).all('lock').remove()
        .catch(function (response) {
          console.error('[DELETE widget-group lock]', response);

          throw 'WORKSHOP.DOCUMENT.DELETE_LOCK_ERROR';
        })
      ;
    }

    /**
     * Gets the WidgetGroupLock for the given WidgetGroup, if any.
     *
     * @param {Object} widgetGroup
     * @returns {Object}
     */
    function getWidgetGroupLock (widgetGroup) {
      return service._locks.get(widgetGroup.id);
    }

    /**
     * Checks whether the given WidgetGroup is locked, for the given User. It
     * means that a lock exists, and it originates from a different user.
     *
     * @param {Object} widgetGroup
     * @param {Object} user
     * @returns {Boolean}
     */
    function isWidgetGroupLockedForUser (widgetGroup, user) {
      var lock = service.getWidgetGroupLock(widgetGroup);

      return lock && lock.user_id !== user.id;
    }

    /**
     * Checks whether the given Page is locked, for the given User. It means
     * that at least one WidgetGroup in the Page is locked by another User.
     *
     * @param {Object} page
     * @param {Object} user
     * @returns {Boolean}
     */
    function isPageLockedForUser (page, user) {
      var pageWidgetGroups = _.filter(service._widgetGroups, function (widgetGroup) {
        return widgetGroup.page_id === page.id;
      });

      return _.any(pageWidgetGroups, function (widgetGroup) {
        return service.isWidgetGroupLockedForUser(widgetGroup, user);
      });
    }
  })

;
