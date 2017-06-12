'use strict';

angular.module('bns.workshop.document')

  .controller('WorkshopDocumentsPanelCtrl', function (workshopThemePreviewModal, flash, $scope, $rootScope, WorkshopRestangular) {
    var ctrl = this;

    this.init = function () {
      // load existing layouts
      WorkshopRestangular.all('layouts').getList().then(function (layouts) {
        $scope.layouts = layouts;
        $scope.layoutTypes = ctrl.loadLayoutTypes();
      }, function error (result) {
        flash.error = "Erreur lors du chargement des mise en page"; // @todo translate
        console.error('GET layouts', result);
      });

      // load existing themes
      WorkshopRestangular.all('themes').getList().then(function (themes) {
        $scope.themes = themes;
      }, function error (result) {
        flash.error = "Erreur lors du chargement des thèmes"; // @todo translate
        console.error('GET themes', result);
      });

      // load existing widget configurations
      WorkshopRestangular.all('widget-configurations').getList().then(function (widgetConfigurations) {
        $scope.widgetConfigurations = widgetConfigurations;
      }, function error (result) {
        flash.error = "Erreur lors du chargement des widgets"; // @todo translate
        console.error('GET widget-configurations', result);
      });

      // prepare the document form
      $scope.form = {
        resource: {
          label: $scope.shared.document.label
        }
      };
    };

    this.loadLayoutTypes = function () {
      var layoutTypes = {};
      angular.forEach($scope.layouts, function (layout) {
        var typeCode = layout.type.code;
        if (!layoutTypes[typeCode]) {
          layoutTypes[typeCode] = angular.copy(layout.type);
        }
      });

      return layoutTypes;
    };

    /**
     * Submits the document form
     */
    $scope.submit = function () {
      var success = function () {
        flash.success = "Document mis à jour"; // @todo translate
      };
      var error = function (result) {
        flash.error = "Erreur lors de la mise à jour du document"; // @todo translate
        console.error('$scope.submit', result);
      };
      $scope.shared.document.patch($scope.form).then(success, error);
    };

    /**
     * Changes the layout of the current page for the one with given id
     * @param  {Integer} id
     */
    $scope.changeLayout = function (layout) {
      if (!$scope.shared.page) {
        flash.error = "Erreur : pas de page"; // @todo translate
        console.error('"changeLayout": no current page');
        return;
      }

      var success = function () {
        $rootScope.$broadcast('layout.changed', layout);
        flash.success = "Mise en page changée"; // @todo translate
      };
      var error = function (result) {
        flash.error = "Erreur lors du changement de mise en page"; // @todo translate
        console.error('$scope.changeLayout', result);
      };
      $scope.shared.page.patch({ layout_code: layout.code }).then(success, error);
    };

    $scope.previewTheme = function (theme) {
      if (theme.code === $scope.shared.theme.code) {
        return;
      }

      workshopThemePreviewModal.theme = theme;
      workshopThemePreviewModal.activate();
    };

    this.init();
  });
