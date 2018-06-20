(function (angular) {
'use strict';

  angular.module('bns.blog', [])

    .directive('bnsBlogCheck', BNSBlogCheckboxDirective)
    .directive('bnsBlogArticleView', BNSBlogArticleViewDirective)
    .controller('BNSBlogCheckbox', BNSBlogCheckboxController)
    .controller('BNSBlogArticleViewController', BNSBlogArticleViewController)

  ;

function BNSBlogCheckboxDirective () {

  return {
    controller : 'BNSBlogCheckbox',
    scope : true
  };

}

function BNSBlogCheckboxController ($scope, $sce ,$attrs, $http, toast, dialog, Routing) {
  var $editBtn = angular.element('#edit-selected');
  var $deleteBtn = angular.element('#delete-selected');
  var $toModeration = angular.element('#to-moderation');
  var $refuseComments = angular.element('#refuse-comments');
  var $acceptComments = angular.element('#accept-comments');

  $scope.selected = [];
  $scope.DeleteSelected = DeleteSelected;
  $scope.check = check;

  $editBtn.on('click', calldialog);
  $toModeration.on('click', moderateSelected);
  $refuseComments.on('click', refuseSelected);
  $acceptComments.on('click', acceptSelected);

  button();

  function DeleteSelected () {
    angular.forEach($scope.selected, function (value) {
      var url = Routing.generate('blog_manager_article_delete', { articleId : value });
      $http.post(url);
      angular.element('#article-' + value).hide();
    });
    $scope.selected.length = 0;
    button();
    toast.success('Articles supprimés');
  }

  function acceptSelected() {
    toModerationSelected('VALIDATED');
  }

  function refuseSelected() {
    toModerationSelected('REFUSED');
  }

  function moderateSelected() {
    toModerationSelected('PENDING_VALIDATION');
  }

  function toModerationSelected(state) {
    angular.forEach($scope.selected, function (value) {
      var url = Routing.generate('comment_manager_status_update');
      $http({
        url: url,
        method: 'POST',
        headers: { 'X-Requested-With' :'XMLHttpRequest' },
        data: {
          namespace: 'dBtW7k7mItAZmKtqstT+ahHujH5sCsvI77TkMkdOOfIQ5/TDoIBhIW7RkTXDoXgttIh1VS9pJWt9+mPtgeRuyw==',
          status: state,
          id: value,
          page: 1,
          editRoute: 'blog_manager_comment_moderation_edit'
        },
      });
      angular.element('#comment-' + value).hide();
    });
    $scope.selected.length = 0;
    button();
    toast.success('BLOG.FLASH_COMMENTS_STATUS_MODIFIED');
  }

  function check (item, list) {
    var idx = list.indexOf(item);
    if (idx > -1) {
      list.splice(idx, 1);
    } else {
      list.push(item);
    }
    button();
  }

  function calldialog() {
    var values = {
      ariaLabel : 'true',
      title: 'Editer',
      content : $sce.trustAsHtml(angular.element('#content-edit').html()),
      ok:'Annuler'
    };
    dialog.show(
      dialog.alert(values)
    );
  }

  function button() {
    if ($scope.selected.length > 0){
      $editBtn.show();
      $deleteBtn.show();
      $toModeration.show();
      $acceptComments.show();
      $refuseComments.show();
    }else{
      $editBtn.hide();
      $deleteBtn.hide();
      $toModeration.hide();
      $acceptComments.hide();
      $refuseComments.hide();
    }
  }

}


  function BNSBlogArticleViewDirective() {

    return {
      controller: 'BNSBlogArticleViewController',
    };

  }

  function BNSBlogArticleViewController($scope, $element, Restangular, $rootScope) {
    var seen = [];
    var thisId = $element.attr('data-article-id');

    var unlisten = $rootScope.$on('duScrollspy:becameActive', function ($event, $element) {
      var activeId = $element.attr('data-article-id');
      if (activeId === thisId) {
          view(activeId);
      }
    });

    $scope.$on('$destroy', function cleanup() {
      seen = [];
      unlisten();
    });

    function view(id) {
      if (seen.indexOf(id) === -1) {
        seen.push(id);
        Restangular.all('blog').one('article', id).all('views').post();
      }
    }

  }
})(angular);
