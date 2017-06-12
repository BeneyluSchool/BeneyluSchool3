(function (angular) {
'use strict';

angular.module('bns.messaging.box', [
  'bns.core.collectionMap',
  'bns.user.users',
  'bns.messaging.folders',
])

  .directive('bnsMessagingBox', BNSMessagingBoxDirective)
  .directive('bnsMessagingBoxItem', BNSMessagingBoxItemDirective)
  .controller('BNSMessagingBox', BNSMessagingBoxController)
  .factory('MessagingBox', MessagingBoxFactory)
  .value('THROTTLE_MILLISECONDS', 500)

;

var MODE_INFINITE = 'infinite';
var MODE_VIRTUAL = 'virtual';

function BNSMessagingBoxItemDirective () {

  return {
    templateUrl: 'views/messaging/directives/bns-messaging-box-item.html',
  };

}

function BNSMessagingBoxDirective ($window, $timeout, $mdUtil) {

  return {
    restrict: 'EA',
    transclude: true,
    templateUrl: function (element, attrs) {
      if ('infinite' === attrs.mode) {
        return 'views/messaging/directives/bns-messaging-box-infinite.html';
      }

      return 'views/messaging/directives/bns-messaging-box.html';
    },
    scope: {
      mode: '@',
      name: '@',
      items: '=box',
      conversations: '=',
      originalSelection: '=selection',
      showAvatars: '=',
      showStatus: '=',
    },
    controller: 'BNSMessagingBox',
    controllerAs: 'box',
    bindToController: true,
    compile: compile,
  };

  function compile (element, attrs) {
    if (!element.attr('id')) {
      element.attr('id', 'messaging-box-'+$mdUtil.nextUid());
    }
    element.addClass('mode-'+(attrs.mode || MODE_VIRTUAL));

    if (MODE_INFINITE === attrs.mode) {
      return postLinkInfinite;
    }

    return postLinkVirtual;
  }

  function postLinkInfinite () {}

  function postLinkVirtual (scope, element) {
    var window = angular.element($window);
    var updateBoxHeight;
    // md animations typically take 300-350ms
    var ANIMATION_DELAY = 500;
    // Do not attempt to update more often than this duration (ms)
    var UPDATE_BOX_DEBOUNCE = 250;
    // Box container
    var container = element.find('md-virtual-repeat-container');
    // Box container controller. This may be a little dirty...
    var containerCtrl = container.controller('mdVirtualRepeatContainer');
    // Previous height of the box
    var previousHeight = 0;
    // Is a box update forced
    var mustUpdate = false;

    /*
    Watch for changes on anything, and register to update the box height after
    a delay (account for animations).
     */
    scope.$watch(function () {
      updateBoxHeight(ANIMATION_DELAY);
      return container.height();
    }, function (newHeight) {
      mustUpdate = true;
      previousHeight = newHeight;
    });

    /*
    Also watch for window resize events
     */
    window.on('resize', updateBoxHeight);
    scope.$on('$destroy', function () {
      window.off('resize', updateBoxHeight);
    });

    /**
     * Debounced function to update the box height. An optional delay can be
     * specified to postpone the operation.
     *
     * @param {Integer} delay The throttle delay in ms.
     */
    updateBoxHeight = $mdUtil.debounce(function (delay) {
      if (parseInt(delay, 10)) {
        $timeout(function() {
          if (mustUpdate || previousHeight !== container.height() || true) {
            containerCtrl.updateSize();
            previousHeight = container.height();
            mustUpdate = false;
          }
        }, delay, false);
      } else {
        if (mustUpdate || previousHeight !== container.height() || true) {
          containerCtrl.updateSize();
          previousHeight = container.height();
          mustUpdate = false;
        }
      }
    }, UPDATE_BOX_DEBOUNCE, null, false);
  }

}

function BNSMessagingBoxController ($rootScope, $scope, $element, $attrs, CollectionMap, Users, messagingConstant, MessagingBox) {

  var box = this;
  box.getAvatarUrl = getAvatarUrl;
  box.getStatus = getStatus;

  init();

  function init () {
    $scope.boxId = $element.attr('id');

    box.wide = MODE_INFINITE === $attrs.mode;

    if (!box.items) {
      box.items = new MessagingBox(box.name);
    }

    // if a selection array is given, build a map from it
    if (box.originalSelection) {
      box.selection = new CollectionMap(box.originalSelection);
    }

    Users.me().then(function (user) {
      box.me = user;
    });

    var cleanup = $rootScope.$on('messaging.box.refresh', function () {
      box.items.init();
    });

    $scope.$on('$destroy', cleanup);
  }

  function getAvatarUrl (conversation) {
    if (!box.me) {
      return null;
    }

    var user = box.me.id !== conversation.user_id ?
      conversation._embedded.user :
      conversation._embedded.user_with ;

    if (!user) {
      return null;
    }

    return user.avatar_url;
  }

  function getStatus (item) {
    if (!item) {
      return;
    }

    if (item._embedded && item._embedded.last_message) {
      return messagingConstant.CONVERSATION.STATUS_READABLE[item.status];
    } else {
      return messagingConstant.MESSAGE.STATUS_READABLE[item.status];
    }
  }

}

function MessagingBoxFactory ($rootScope, $timeout, MessagingFolders) {

  function MessagingBox (name) {
    /**
     * The total number of items. Defaults to null (not initialized, this is
     * different from 0, when we're sure there are no items).
     *
     * @type {Integer}
     * @readOnly
     */
    this.total = null;

    /**
     * Query parameters
     *
     * @type {Object}
     */
    this.params = {};

    this.store_ = MessagingFolders.one(name);
    this.init();
  }

  MessagingBox.prototype.init = function () {
    this.total = null;
    this.numLoaded_ = 0;
    this.toLoad_ = 0;
    this.page_ = 0;
    this.limit_ = 10;
    this.items_ = [];
    this.busy_ = false;

    $timeout(angular.bind(this, function () {
      $rootScope.$emit('messaging.box.refreshed', this);
    }), 0);
  };

  /**
   * Gets the number of items.
   * Required by mdVirtualRepeatModel.
   *
   * @return {Integer}
   */
  MessagingBox.prototype.getLength = function () {
    if (null !== this.total) {
      return Math.min(this.total, this.numLoaded_ + this.limit_);
    }

    return this.numLoaded_ + this.limit_;
  };

  /**
   * Gets the item at the given index. If not found, triggers a load from remote
   * store.
   * Required by mdVirtualRepeatModel.
   *
   * @param  {Integer} index
   * @return {Object}
   */
  MessagingBox.prototype.getItemAtIndex = function (index) {
    if (index >= this.numLoaded_) {
      this.fetchMoreItems_(index);
      return null;
    }

    return this.items_[index];
  };

  /**
   * Loads another batch of items in the collection.
   * Used by infinite scroll.
   *
   * @return {Object}
   */
  MessagingBox.prototype.loadMore = function () {
    return this.fetchMoreItems_(this.numLoaded_ + this.limit_);
  };

  MessagingBox.prototype.fetchMoreItems_ = function (index) {
    if (this.busy_) {
      return;
    }

    if (this.toLoad_ < index) {
      this.busy_ = true;
      this.toLoad_ += this.limit_;
      this.page_ ++;
      var params = angular.merge({}, this.params, { page: this.page_, limit: this.limit_ });
      this.store_.getList('', params)
        .then(angular.bind(this, function success (response) {
          if (response.pager) {
            this.total = response.pager.total;
          }
          for (var i = 0; i < response.length; i++) {
            this.items_.push(response[i]);
            this.numLoaded_ ++;
          }
        }))
        .finally(angular.bind(this, function end () {
          this.busy_ = false;
        }))
      ;
    }
  };

  return MessagingBox;

}

})(angular);
