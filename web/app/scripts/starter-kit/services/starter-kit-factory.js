(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.starterKit.service
 */
angular.module('bns.starterKit.service', [
  'bns.starterKit.dialogControllers',
  'bns.starterKit.requestInterceptor',
  'bns.starterKit.state',
  'bns.starterKit.store',
  'bns.starterKit.utils',
])

  .factory('StarterKit', StarterKitFactory)
  .provider('starterKit', StarterKitProvider)

;

/**
 * @ngdoc service
 * @name StarterKit
 * @module bns.starterKit.service
 *
 * @description
 * Factory for StarterKit managers.
 *
 * @requires $rootScope
 * @requires $state
 * @requires $log
 * @requires dialog
 * @requires starterKitUtils
 * @requires StarterKitRequestInterceptor
 * @requires StarterKitState
 * @requires StarterKitStore
 */
function StarterKitFactory ($rootScope, $window, $state, $log, $q, dialog, global, $timeout, $mdPanel, parameters, starterKitUtils,
  StarterKitRequestInterceptor, StarterKitState, StarterKitStore) {

  /**
   * Builds a new starter kit manager, optionally watching for changes and
   * triggering steps automatically.
   *
   * @param {Boolean} watch
   */
  function StarterKit (watch) {
    this.interceptor = new StarterKitRequestInterceptor(this);
    this.utils = starterKitUtils;
    this.reset();

    if (watch) {
      var sk = this;
      $rootScope.$watch(function () {
        return sk.enabled ? ('' + sk.level + sk.index) : false;
      }, function () {
        if (sk.enabled) {
          sk.checkStep();
        }
      });
    }
  }

  StarterKit.prototype.reset = function () {
    this.enabled = false;   // Whether starter kit is enabled
    this.maxLevel = 0;      // Max level reached by user
    this.app = null;        // The current starter kit app (module unique name)
    this.level = 0;         // Starter kit current level
    this.index = null;      // Step index within current level
    this.steps = [];        // Array of levels, which are arrays of steps
    this.state = null;      // Starter kit state api object
    this._cleaners = [];    // Array of callables to execute when transitioning
                            // to another step
  };

  /**
   * Checks whether starter kit for the given app is available.
   *
   * @param {String} app
   * @returns {Boolean}
   */
  StarterKit.prototype.has = function (app) {
    var has = parameters['has_starter_kit_'+app.toLowerCase()];
    if (angular.isArray(has)) {
      has = has.indexOf(global('locale')) > -1;
    }

    return !!has;
  };

  /**
   * Starts the starter kit for the given app
   *
   * @param {String} app
   * @returns {Promise} Resolved at the end of the boot sequence, whether it was
   *                    successful or not.
   */
  StarterKit.prototype.boot = function (app) {
    if (!this.has(app)) {
      $log.info('Starter kit for '+app+' is disabled');

      return $q.resolve(false);
    }

    // TODO: check if teacher

    var sk = this;
    sk.state = StarterKitState.one(app);

    return sk.state.get().then(success).catch(failure);

    function success (state) {
      // got starter kit state, now chain with another promise to get steps
      // configuration
      return StarterKitStore.getSteps(app).then(setupSteps);

      function setupSteps (steps) {
        sk.app = app;
        sk.steps = steps;
        sk.enabled = state.enabled;
        sk.maxLevel = state.max_level;
        if (sk.enabled) {
          sk.setStep(state.last_step);
          sk.current = sk.getStep();
        }
      }
    }

    function failure () {
      // swallow error: let app lifecycle continue
      return $log.warn('Starter kit boot failed');
    }
  };

  /**
   * Gets the steps for current level
   *
   * @returns {Array}
   */
  StarterKit.prototype.getSteps = function () {
    return this.steps[this.level];
  };

  /**
   * Gets the current step, if any
   *
   * @returns {Object}
   */
  StarterKit.prototype.getStep = function () {
    return this.steps[this.level] && this.steps[this.level][this.index] ?
      this.steps[this.level][this.index] :
      null
    ;
  };

  /**
   * Sets the step from the given step code (ie '1-2.3' for step 2.3 of level 1)
   *
   * @param {String} step The step code
   */
  StarterKit.prototype.setStep = function (step) {
    if (step) {
      var parts = step.split('-');
      this.level = parseInt(parts[0], 10);
      for (var i = 0; i < this.steps[this.level].length; i++) {
        if (this.steps[this.level][i].step === step) {
          this.index = i;
          return;
        }
      }

      return $log.warn('Step not found:', step);
    } else {
      this.level = 0;
      this.index = 0;
    }
  };

  StarterKit.prototype.enable = function () {
    var sk = this;

    return sk.state.patch({enabled: true}).then(function () {
      sk.enabled = true;
    });
  };

  /**
   * Suspends the starter kit for the current app.
   *
   * @param {Boolean} saveNextStep Whether to save next step, so that the next
   *                               boot goes to this step instead of the current
   *                               one. Defaults to false, ie. boot resume
   *                               current step.
   * @param {Boolean} linkUrl
   * @returns {Promise} A chained promise that resolves when starter kit is
   *                    successfuly suspended and cleaned up
   */
  StarterKit.prototype.suspend = function (saveNextStep, linkUrl) {
    var sk = this;

    var data = {
      enabled: false,
    };
    if (saveNextStep) {
      var nextStep = sk.getNextStep();
      if (nextStep) {
        data.last_step = nextStep.step;
      }
    }

    if (linkUrl) {
      // open link early should be in the same flow as the user click to prevent popup blocker
      $window.open(linkUrl, '_blank');
    }

    return sk.state.patch(data).then(function () {
      sk.enabled = false;
      sk.current = null;

      return sk.cleanup().then(function () {
        if (linkUrl) {
          return ;
        }
        if ($state.current && $state.current.name) {
          $state.go($state.current.name, $state.current.params, {reload: true});
        }
      });
    });
  };

  StarterKit.prototype.prev = function () {
    var level = this.level,
      index = this.index
    ;
    if (index > 0) {
      index--;
    }

    return this.navigate(this.steps[level][index]);
  };

  StarterKit.prototype.getNextStep = function () {
    var level = this.level,
      index = this.index
    ;
    if (this.steps[level].length - 1 === index) {
      this.current = null;
      if (this.steps[level + 1]) {
        $log.info('End of starter kit level', level);
        level++;
        index = 0;
      } else {
        $log.info('End of starter kit ;)');

        return this.finish();
      }
    } else {
      if (this.current && this.current.skip) {
        $log.info('Skip to step', this.current.skip);
        // skip to designated step
        var i = index,
          found = false
        ;
        while (i < this.steps[level].length) {
          if (this.steps[level][i].step === this.current.skip) {
            index = i;
            found = true;
            break;
          }
          i++;
        }
        if (!found) {
          return $log.warn('Skip step not found', this.current.skip);
        }
      } else {
        index++;
      }
    }

    return this.steps[level][index];
  };

  StarterKit.prototype.next = function () {
    return this.navigate(this.getNextStep());
  };

  StarterKit.prototype.navigate = function (step) {
    var sk = this;

    return this.cleanup().then(function navigate () {
      sk.setStep(step.step||step); // update level and index

      // save current step
      var newStep = sk.getStep();
      if (newStep) {
        sk.state.patch({ last_step: newStep.step });
      }
    });
  };

  /**
   * Checks the current step and launch necessary actions.
   */
  StarterKit.prototype.checkStep = function () {
    var step = this.current = this.getStep();
    var sk = this;

    if (!step) {
      return $log.warn('No starter kit step found');
    }

    // navigate to requested state
    if (step.state) {
      if (($state.current.name !== step.state) || step.reload) {
        return $state.go(step.state, {}).then(function () {
          sk.doStep(step);
        });
      }
    }

    this.doStep(step);
  };

  /**
   * Displays starter kit UI elements for the given step.
   *
   * @param {Object} step The step configuration
   */
  StarterKit.prototype.doStep = function (step) {
    var sk = this;
    $rootScope.$emit('starterKit.'+this.app+'.step', step);

    switch (step.type) {
      case 'start':
        this.showDialog({
          templateUrl: 'views/starter-kit/dialogs/start.html',
          controller: 'StarterKitStartDialog',
          panelClass: 'starter-kit-start-dialog',
        });
        break;
      case 'introduction':
        this.showDialog({
          templateUrl: 'views/starter-kit/dialogs/introduction.html',
          panelClass: 'starter-kit-introduction-dialog',
        });
        break;
      case 'achievement':
        this.showDialog({
          templateUrl: 'views/starter-kit/dialogs/achievement.html',
          panelClass: 'starter-kit-achievement-dialog',
        });
        break;
      case 'conclusion':
        this.showDialog({
          templateUrl: 'views/starter-kit/dialogs/conclusion.html',
          controller: 'StarterKitConclusionDialog',
          panelClass: 'starter-kit-conclusion-dialog',
        });
        break;
      case 'explanation':
        var options = {
          controller: 'StarterKitExplanationDialog',
          locals: {
            target: null,
          },
        };
        if (step.target) {
          this.utils.getElementAsync(step.target).then(function (target) {
            if (!target.closest('html').length) {
              // can happen in case of redirect from parent state to default
              // child state with params: dom from parent state is quickly
              // parsed
              return $log.warn('Explanation element is no longer present in the document');
            }

            sk.utils.scrollIntoView(target);
            sk.frame(target, !step.frozen);
            if (step.frozen) {
              sk.freeze(target);
            }

            // attach dialog to the given parent (defaults to the document body)
            sk.utils.getElementAsync(step.parent || 'body').then(doShowDialog);

            function doShowDialog (parent) {
              sk.showDialog(angular.extend(options, {
                parent: parent,
                locals: {
                  target: target,
                },
                panelClass: 'has-target',
              }));
              if (step.validate) {
                sk.watchValidate(target.scope(), step.validate, null, target);
              }
            }
          });
        } else if (step.data && step.data.accept) {
          this.showDialog(options);
        }
        break;
      case 'validate':
        if (step.target) {
          this.utils.getElementAsync(step.target).then(function (target) {
            sk.activate(target);
            sk.createBackdrop(null, target.parent());
            if (step.scope) {
              sk.utils.getElementAsync(step.scope).then(function (scopeElement) {
                sk.watchValidate(scopeElement.scope(), step.validate, null, target);
              });
            } else {
              sk.watchValidate(target.scope(), step.validate, null, target);
            }
          });
        } else if (step.scope) {
          this.utils.getElementAsync(step.scope).then(function (scopeElement) {
            sk.watchValidate(scopeElement.scope(), step.validate);
          });
        } else {
          $log.warn('Validate without target or scope');
        }
        break;
      case 'pointer':
        if (step.data && step.data.content) {
          this.showDialog({
            templateUrl: 'views/starter-kit/dialogs/pointer.html',
          });
        } else {
          this.createBackdrop();
        }
        this.displayPointers(step);
        break;
      case 'stepper':
        if (step.validate && step.scope) {
          this.utils.getElementAsync(step.scope).then(function (scopeElement) {
            sk.watchValidate(scopeElement.scope(), step.validate);
          });
        }
        if (step.target) {
          this.utils.getElementAsync(step.target).then(function (target) {
            sk.activate(target);
            sk.createBackdrop(null, target.parent());
          });
        }
        // handled by directive
        break;
    }
  };

  /**
   * Gets the step main section, ie first and second "levels".
   *
   * @example
   * '1-1'      (level 1, step 1) => '1-1'
   * '1-2.3'    (level 1, step 2, section 3) => '1-2.3'
   * '1-3.4.5'  (level 1, step 3, section 4, part 5) => '1-3.4'
   *
   * @param {Object} step
   * @returns {String}
   */
  StarterKit.prototype.getStepSection = function (step) {
    return (step.step && step.step.substring(0, 5));  // 1-2.3
  };

  StarterKit.prototype.finish = function () {
    var sk = this;

    return this.state.patch({
      done: true,
    })
      .then(function success () {
        return sk.suspend();
      })
    ;
  };

  /**
   * Wrapper for starterKitUtils.watchValidate with auto cleanup at the end of
   * current step.
   *
   * @param {Scope} scope
   * @param {String} expression
   * @param  {Function} callback
   * @param {Element} element
   */
  StarterKit.prototype.watchValidate = function (scope, expression, callback, element) {
    var sk = this;
    var cleaner = this.utils.watchValidate(scope, expression, function () {
      if (angular.isFunction(callback)) {
        callback();
      }
      sk.next();
    }, element);
    this.addCleaner(cleaner);
  };

  /**
   * Wrapper for starterKitUtils.createBackdrop with auto cleanup at the end of
   * current step.
   *
   * @param {Scope} scope
   * @param {Element} parent
   */
  StarterKit.prototype.createBackdrop = function (scope, parent) {
    var cleaner = this.utils.createBackdrop(scope, parent);
    this.addCleaner(cleaner);
  };

  /**
   * Wrapper for starterKitUtils.activate with auto cleanup at the end of
   * current step.
   *
   * @param {Element} element
   */
  StarterKit.prototype.activate = function (element) {
    var cleaner = this.utils.activate(element, true);
    this.addCleaner(cleaner);
  };

  /**
   * Wrapper for starterKitUtils.frame with auto cleanup at the end of
   * current step.
   *
   * @param {Element} element
   */
  StarterKit.prototype.frame = function (element, clickable) {
    var cleaner = this.utils.frame(element, clickable);
    this.addCleaner(cleaner);
  };

  /**
   * Wrapper for starterKitUtils.freeze with auto cleanup at the end of
   * current step.
   *
   * @param {Element} element
   */
  StarterKit.prototype.freeze = function (element) {
    var cleaner = this.utils.freeze(element, true);
    this.addCleaner(cleaner);
  };

  /**
   * Utility to display dialogs without collision (if triggered multiple times)
   * and auto cleanup ad the end of current step.
   *
   * @param {Object} conf dialog configuration
   */
  StarterKit.prototype.showDialog = function (conf) {
    var sk = this;
    if (sk._hasDialog) {
      // can happen if multiple directives are present, ie in a form proxy
      $log.warn('starter-kit dialog already showing', sk.current);
    } else {
      sk._hasDialog = true;
      if (sk.current.position && !sk.current.target && !conf.position) {
        conf.position = sk.current.position;
      }
      var cleaner = sk.utils.showDialog(conf);
      sk.addCleaner(function hideDialog () {
        sk._hasDialog = false;

        return cleaner();
      });
    }
  };

  /**
   * Displays pointers for the given step.
   *
   * @param {Object} step
   */
  StarterKit.prototype.displayPointers = function (step) {
    var sk = this;
    var pointerCleaners = [];
    var unwatch = $rootScope.$watchCollection(function () {
      return step.data ? step.data._pointerElements : null;
    }, function (pointerElements) {
      angular.forEach(pointerElements, function (element, name) {
        if (element.shown) {
          return;
        }

        var text = step.data ? (step.data.pointers ? step.data.pointers[name] : '') : '';
        pointerCleaners.push(sk.utils.showPointer(element, text));
      });
    });

    this.addCleaner(function () {
      unwatch();
      angular.forEach(pointerCleaners, function (cleaner) {
        cleaner();
      });
    });
  };

  /**
   * Adds a cleanup function, to be executed when current step is finished.
   *
   * @param {Function} cleaner
   */
  StarterKit.prototype.addCleaner = function (cleaner) {
    this._cleaners.push(cleaner);
  };

  /**
   * Executes all registered cleaner functions, then removes them.
   *
   * @returns {Promise} A promise resolved when all cleaners have finished
   */
  StarterKit.prototype.cleanup = function () {
    var sk = this;

    return sk.utils.wrapInPromises(sk._cleaners, 1000).then(function () {
      sk._cleaners = [];
    });
  };

  /**
   * Proxy to the configured request interceptor.
   *
   * @param {Object} element
   * @param {String} operation
   * @param {String} what
   * @param {String} url
   * @param {Object} headers
   * @param {Object} params
   * @param {Object} httpConfig
   * @returns {Object}
   */
  StarterKit.prototype.interceptRequest = function (element, operation, what, url, headers, params, httpConfig) {
    return this.interceptor.handle(element, operation, what, url, headers, params, httpConfig);
  };

  return StarterKit;

}

/**
 * @ngdoc provider
 * @name starterKit
 * @module bns.starterKit.service
 *
 * @description
 * The global starter kit manager
 */
function StarterKitProvider () {

  this.instance = null;

  this.$get = ['StarterKit', function (StarterKit) {
    return (this.instance = new StarterKit(true));
  }];

}

})(angular);
