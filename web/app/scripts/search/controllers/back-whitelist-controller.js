(function (angular) {
  'use strict';

  angular.module('bns.search.backWhitelistController', [
      'bns.search.search'
    ])

    .controller('SearchBackWhitelist', SearchBackWhitelistController)
  ;

  var DialogController = ['$rootScope', 'Restangular', '$mdDialog', 'toast', function($rootScope, Restangular, $mdDialog, toast) {
    var ctrl = this;
    ctrl.addLink = addLink;
    ctrl.editLink = editLink;

    function addLink() {
      ctrl.busy = true;
      if (!(ctrl.link)) {
        return;
      }
      Restangular.all('search').all('url').post({url: ctrl.link})
        .then(function success(link) {
          $mdDialog.hide();
          $rootScope.$emit('search.new-link', link );
        })
        .catch(function error(response) {
          ctrl.busy = false;
          if (response && response.data && response.data.error) {
            toast.error(response.data.error);
          } else {
            toast.error('SEARCH.GET_WHITE_LIST_ERROR');
          }
          throw response;
        })
      ;
    }

    function editLink(link) {
      ctrl.busy = true;
      if (!(ctrl.link)) {
        return;
      }
      var data = {
        'media_id': link.id,
        'media_value': link.media_value
      };
      return Restangular.one('search/url', link.id).patch(data)
        .then(function success() {
          $mdDialog.hide();
          toast.success('SEARCH.EDIT_DONE');
        })
        .catch(function error(response) {
          ctrl.busy = false;
          if (response && response.data && response.data.error) {
            toast.error(response.data.error);
          } else {
            toast.error('SEARCH.EDIT_ERROR');
          }
          throw response;
        })
        ;
    }
  }];


  function SearchBackWhitelistController ($rootScope, Restangular, dialog, Search, toast, $q) {

    var ctrl = this;
    ctrl.switch = true;
    ctrl.link = '';

    ctrl.showDialog = showDialog;
    ctrl.DialogController = DialogController;
    ctrl.getSwitchManager = getSwitchManager;
    ctrl.managers = {};
    ctrl.deleteLink = deleteLink;
    ctrl.showEditDialog = showEditDialog;
    ctrl.showDeleteDialog= showDeleteDialog;

    init();


    function init() {
      ctrl.busy = true;

      Search.one('white-list').get()
        .then(function success(search) {
          ctrl.search = search;
        })
        .catch(function error(response) {
          toast.error('SEARCH.GET_WHITE_LIST_ERROR');
          throw response;
        })
        .finally(function end() {
          ctrl.busy = false;

        });
    }


    function getSwitchManager (media) {
      if (!ctrl.managers[media.id]) {
        ctrl.managers[media.id] = buildInterface(media);
      }
      return ctrl.managers[media.id];

      function buildInterface(param) {
        return {
          getStatus: function () {
            return $q.when({
              status: param.search_status
            });
          },
          toggle: function (status) {
            return Restangular.all('search').one('media', param.id).all('toggle').post({toggle: status})
              .then(function (data) {
                param.search_status = !!data.status;
                return {status: param.search_status};
              });
          }
        };
      }
    }

    function showDialog() {
      return dialog.custom({
        templateUrl: 'views/search/back/new-link-dialog.html',
        locals: {
          close: dialog.hide,
        },
        controller: DialogController,
        controllerAs: 'ctrl',
        bindToController: true,
        clickOutsideToClose: true,
      });
    }

    function showDeleteDialog(link) {
      return dialog.custom({
        templateUrl: 'views/search/back/delete-link.html',
        locals: {
          close: dialog.hide,
          link: link,
          delete: ctrl.deleteLink
        },
        controller: DialogController,
        controllerAs: 'ctrl',
        bindToController: true,
        clickOutsideToClose: true,
      });
    }

    function showEditDialog(link) {

      return dialog.custom({
        templateUrl: 'views/search/back/edit-link-dialog.html',
        locals: {
          close: dialog.hide,
          link: link
        },
        controller: DialogController,
        controllerAs: 'ctrl',
        bindToController: true,
        clickOutsideToClose: true,
      });
    }

    function addLink(event, link) {
      ctrl.search.links.push(link);
    }

    $rootScope.$on( 'search.new-link' , addLink);


    function deleteLink(link) {
      Restangular.one('media-library').one('media', link.id).remove()
        .then(function success() {
          dialog.hide();
          toast.success('SEARCH.SUCCESS_DELETE');
          init();
        });

  }
    function editLink(event, link) {
      ctrl.search.links.push(link);
    }

    $rootScope.$on('search.edit-link', editLink);

}





})(angular);
