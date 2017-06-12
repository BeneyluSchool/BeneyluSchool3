'use strict';

angular.module('bns.userDirectory')

  /**
   * @ngdoc service
   * @name bns.userDirectory.userDirectory
   * @kind function
   *
   * @description
   * Access service for the userDirectory module
   *
   * ** Attributes **
   * - `active {Boolean}`: Whether the user directory is currently active
   * - `debug {Boolean}`: Whether debug mode is enabled
   *
   * ** Methods **
   * - `activate()`: Activates the user directory
   * - `deactivate()`: Deactivates the user directory
   *
   * @requires $rootScope
   * @requires $state
   * @requires userDirectoryState
   * @requires userDirectoryGroups
   * @requires userDirectoryUsers
   *
   * @returns {Object} The userDirectory service
   */
  .factory('userDirectory', function ($rootScope, $state, $previousState, $timeout, userDirectoryState, userDirectoryGroups, userDirectoryUsers, userDirectoryDistributions) {
    var MODE_VIEW = 'view';
    var MODE_SELECTION = 'selection';
    var PREVIOUS_STATE_KEY = 'beforeUserDirectory';

    var service = {
      active: false,
      activate: activate,
      deactivate: deactivate,
      isView: isView,
      isSelection: isSelection,
      mode: MODE_VIEW,
      debug: false,
    };

    // quick way to make it available to the views
    $rootScope.userDirectory = service;

    return service;

    /**
     * Activates the user directory: remember invoking state before
     * transitioning to the user directory modal.
     *
     * @param  {Object} invoker an optional state, to be restored when user
     *                          directory will be deactivated. Defaults to the
     *                          previous app state
     */
    function activate (conf, invoker) {

      if (service.debug) {
        console.info('Activate userDirectory', conf);
      }

      if (service.activating || service.active) {
        if (service.debug) {
          console.warn('userDirectory already being activated');
        }
        return;
      }

      $previousState.memo(PREVIOUS_STATE_KEY);

      if (conf) {
        applyConfiguration(conf);
      }

      if ($state.includes('userDirectory')) {
        if (service.debug) {
          console.warn('already in user directory state');
        }
        service.active = true;
        return;
      }

      if (false !== invoker) {
        service.invoker = invoker || {
          state: angular.copy($state.current),
          params: angular.copy($state.params),
        };
        if (service.debug) {
          console.log('invoker' + (invoker?' (explicit)':'') + ':', service.invoker.state.name);
        }
      } else {
        service.invoker = false;
        if (service.debug) {
          console.info('disabled invoker');
        }
      }

      service.activating = true;
      $state.go('userDirectory')
        .then(function () {
          service.activating = false;
          service.active = true;
        })
      ;
    }

    /**
     * Deactivates the user directory: resets its, and go back to previous app
     * state
     */
    function deactivate () {
      if (service.debug) {
        console.info('Deactivate userDirectory');
      }

      if (!service.active) {
        if (service.debug) {
          console.warn('userDirectory is not active');
        }
        return;
      }

      reset();
      service.active = false;
      if (service.invoker && service.invoker.state.name) {
        if (service.debug) {
          console.log('go to previous state:', service.invoker.state);
        }
        $state.go(service.invoker.state.name, service.invoker.params)
          .then(function () {
            service.invoker = null;
          });
      } else {
        if (service.debug) {
          console.log('no previous state to go to');
        }

        var prev = $previousState.get(PREVIOUS_STATE_KEY);
        if (prev.state) {
          $previousState.go(PREVIOUS_STATE_KEY)
            .then(function () {
              service.invoker = null;
            });
        } else {
          $state.go('root')
            .then(function () {
              service.invoker = null;
            });
        }
      }
    }

    function isView () {
      return MODE_VIEW === service.mode;
    }

    function isSelection () {
      return MODE_SELECTION === service.mode;
    }


    /* ---------------------------------------------------------------------- *\
     *  Internals
    \* ---------------------------------------------------------------------- */

    /**
     * Resets the user directory to its default state
     */
    function reset (clearData) {
      userDirectoryState.reset();
      service.mode = MODE_VIEW;

      if (clearData) {
        userDirectoryGroups.reset();
        userDirectoryUsers.reset();
      }
    }

    /**
     * Applies the given configuration to the various user directory services.
     *
     * @param {Object} conf
     */
    function applyConfiguration (conf) {
      // view has changed since last invocation
      if (conf.view !== service.view) {
        reset(true);
      }

      if (conf.view) {
        service.view = userDirectoryState.view = conf.view;
        userDirectoryGroups.view(service.view);
      }

      if (conf.selection || conf.selectionGroup || conf.selectionDistribution || conf.selectionRole) {
        service.mode = MODE_SELECTION;

        if (conf.selection && conf.selection.length) {
          userDirectoryUsers.lookup(conf.selection, conf.view)
            .then(function (users) {
              userDirectoryState.selection.addc(users);
            });
        }

        if (conf.selectionGroup && conf.selectionGroup.length) {
          userDirectoryGroups.lookup(conf.selectionGroup)
            .then(function (groups) {
              userDirectoryState.selectionGroup.addc(groups);
            });
        }

        userDirectoryState.allowGroupSelection = !!conf.selectionGroup;

        if (conf.selectionDistribution && conf.selectionDistribution.length) {
          userDirectoryDistributions.lookup(conf.selectionDistribution)
            .then(function (lists) {
              userDirectoryState.selectionDistribution.addc(lists);
            });
        }

        if (conf.selectionRole && conf.selectionRole.length) {
          userDirectoryState.selectionRole.addc(conf.selectionRole);
        }

        userDirectoryState.allowRoleSelection = !!conf.selectionRole;

        if (conf.onSelection) {
          userDirectoryState.onSelection = conf.onSelection;
        }

        if (angular.isArray(conf.locked)) {
          userDirectoryState.locked = conf.locked;
        }

        if (angular.isArray(conf.lockedGroup)) {
          userDirectoryState.lockedGroup = conf.lockedGroup;
        }
      }

      if (conf.group) {
        userDirectoryState.intent = conf.group;
      }
    }
  })

;
