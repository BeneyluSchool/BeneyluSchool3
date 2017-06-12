(function (angular) {
'use strict';

angular.module('bns.blog', [])

  .directive('bnsBlogCheck', BNSBlogCheckboxDirective)
  .controller('BNSBlogCheckbox', BNSBlogCheckboxController)

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

  $scope.selected = [];
  $scope.DeleteSelected = DeleteSelected;
  $scope.check = check;

  $editBtn.on('click', calldialog);

  button();

  function DeleteSelected () {
    angular.forEach($scope.selected, function (value) {
      var url = Routing.generate('blog_manager_article_delete', { articleId : value });
      $http.post(url);
      angular.element('#article-' + value).hide();
    });
    toast.success('Articles supprimés');
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
    }else{
      $editBtn.hide();
      $deleteBtn.hide();
    }
  }

}

})(angular);
