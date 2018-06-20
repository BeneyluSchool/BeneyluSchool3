(function (angular) {
'use strict';

/**
 * @ngdoc module
 * @name bns.main.choiceCreate
 */
angular.module('bns.main.choiceCreate', [])

  .directive('bnsChoiceCreate', BNSChoiceCreateDirective)

;

/**
 * @ngdoc directive
 * @name bnsChoiceCreate
 * @module bns.main.choiceCreate
 *
 * @description
 * Works with the bnsCheckboxGroup or bnsRadioGroup directives to allow creation
 * of new choices directly in the widget. The directive adds an input to the
 * checkbox/radio group.
 *
 * ** Attributes **
 *  - `bnsChoiceCreate` (required): API endpoint where new choices are POSTed.
 *    Should return the newly-created object with at least an 'id' and a 'title'
 *    property. Data-conversion is handled internally to work with the
 *    checkbox/radio group.
 *  - `bnsAutoSelect` (optional): Whether to automatically select the created
 *                             choices. Defaults to true.
 *
 * @example
 * <!-- Add choice creation to a checkbox group -->
 * <bns-checkbox-group bns-choice-create="myApiUrl"></bns-checkbox-group>
 *
 * <!-- Add choice creation to a radio group, and disable auto-selection of the
 * newly-created choice -->
 * <md-radio-group bns-radio-group bns-choice-create="myApiUrl" bns-auto-select="false"></md-radio-group>
 *
 * @requires $compile
 * @requires $http
 * @requires toast
 */
function BNSChoiceCreateDirective ($compile, $http, toast) {

  var inputTemplate = '<form ng-submit="createChoice($event)">'+
    '<md-input-container md-no-float>'+
      '<input type="text" ng-model="value" placeholder="{{createLabel|translate}}">'+
      '<md-button type="submit" class="md-primary md-raised btn-xs btn-addon">'+
        '<md-icon>add</md-icon>'+
        '<span ng-bind-html="\'MAIN.BUTTON_ADD\'|translate|buttonize"></span>'+
      '</md-button>'+
    '</md-input-container>'+
  '</form>';

  return {
    restrict: 'A',
    require: ['?bnsCheckboxGroup', '?bnsRadioGroup'],
    link: postLink,
  };

  function postLink (scope, element, attrs, ctrls) {
    var checkboxGroupCtrl = ctrls[0];
    var radioGroupCtrl = ctrls[1];
    var url = attrs.bnsChoiceCreate;
    var autoSelect = angular.isDefined(attrs.bnsAutoSelect) ? !!scope.$eval(attrs.bnsAutoSelect) : true;

    if (!((checkboxGroupCtrl || radioGroupCtrl) && url)) {
      return console.warn('Missing group or url to create choices', checkboxGroupCtrl, radioGroupCtrl, url);
    }

    init();

    function init () {
      scope.createLabel = attrs.createLabel || 'MAIN.PLACEHOLDER_NEW_ELEMENT';
      scope.createChoice = createChoice;
      scope.value = '';

      element.prepend($compile(inputTemplate)(scope));
    }

    function createChoice (event) {
      event.preventDefault();

      $http({
        method: 'POST',
        url: url,
        data: {
          title: scope.value,
        }
      })
        .then(function success (response) {
          scope.value = '';
          var choice = {
            value: response.data.id,
            label: response.data.title,
          };

          if (checkboxGroupCtrl) {
            checkboxGroupCtrl.addChoice(choice);
            if (autoSelect) {
              checkboxGroupCtrl.add(choice.value);
            }
          } else {
            radioGroupCtrl.addChoice(choice);
            if (autoSelect && radioGroupCtrl.model) {
              radioGroupCtrl.model.$setViewValue(choice.value);
              radioGroupCtrl.model.$render();
            }
          }

          return choice;
        })
        .catch(function error (response) {
          toast.error('MAIN.FLASH_CREATE_CHOICE_ERROR');
          console.error(response);
          throw response;
        })
      ;

      return false;
    }
  }

}

})(angular);
