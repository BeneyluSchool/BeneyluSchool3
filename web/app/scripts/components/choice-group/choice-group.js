(function (angular) {
'use strict';

angular.module('bns.components.choiceGroup', [
  'bns.core.day',
  'bns.core.status',
])

  .controller('ChoiceGroupController', ChoiceGroupController)
  .factory('choiceGroupDecorator', ChoiceGroupDecoratorFactory)

;

function ChoiceGroupDecoratorFactory ($controller) {

  return {
    decorateTemplate: decorateTemplate,
    // decorateCompile: decorateCompile,
    decorateLink: decorateLink,
    decorateController: decorateController,
  };

  function decorateTemplate (element, attrs) {
    // Clone the element into an attribute. By prepending the attribute name
    // with '$', Angular won't write it into the DOM. The cloned element
    // propagates to the link function via the attrs argument, where various
    // contained-elements can be consumed.
    attrs.$bnsChoiceTemplateElement = element.clone();
  }

  // function decorateCompile (element, attrs) {
  //   var bnsChoiceTemplateElement = attrs.$bnsChoiceTemplateElement;
  //   attrs.$bnsChoiceTemplateElement = null;
  // }

  function decorateLink (scope, attrs, group) {
    // 2-way data-binding without isolated scope
    scope.$watch(attrs.choices, function (choices) {
      group.choices = choices;
    });
    scope.$watch(attrs.bnsDisabled, function (disabled) {
      group.disabled = disabled;
    });

    // Grab the user template from attr (set by directive template)
    group.choiceLabelTemplate = getTemplateByQuery(attrs.$bnsChoiceTemplateElement, 'bns-choice-label');
    group.choiceSecondaryTemplate = getTemplateByQuery(attrs.$bnsChoiceTemplateElement, 'bns-choice-secondary');
  }

  function getTemplateByQuery (sourceElement, query) {
    var element = sourceElement[0].querySelector(query);

    return element && element.outerHTML;
  }

  function decorateController (scope, attrs, group) {
    $controller('ChoiceGroupController', {
      $scope: scope,
      $attrs: attrs,
      group: group,
    });
  }

}

function ChoiceGroupController ($scope, $attrs, bnsStatus, bnsDay, group) {

  group.groupByIcon = $scope.$eval($attrs.bnsGroupByIcon);
  group.hasVanillaInput = angular.isDefined($attrs.bnsVanillaInput) && $scope.$eval($attrs.bnsVanillaInput);
  group.addChoice = addChoice;
  group.getExtraClass = getExtraClass;

  // build a string that will be evaluated once by ng-attr-ng-style, it will
  // result in a ng-style expression only if necessary
  if (angular.isDefined($attrs.bnsColor)) {
    group.style = '{ \'border-left-color\': choice.' + ($attrs.bnsColor || 'color') + ' }';
  }

  /**
   * Adds a choice to the list of POSSIBLE choices.
   *
   * @param {Object} choice
   * @returns {Object} the created choice template
   */
  function addChoice (choice) {
    if (!group.choices) {
      return console.warn('No choices collection set');
    }

    var template = {};
    if ($attrs.name) {
      template.name = $attrs.name;
    }
    if ($attrs.id) {
      template.id = $attrs.id + '_' + group.choices.length;
    }
    group.choices.push(angular.extend(template, choice));

    return template;
  }

  /**
   * Gets the css class for the status of the given choice
   *
   * @param {Object} choice
   * @returns {String}
   */
  function getExtraClass (choice) {

    var res = '';

    if (angular.isDefined($attrs.bnsStatus)) {
      var found = bnsStatus.guess(choice, $attrs.bnsStatus || 'value');

      res += found ? 'bns-status-'+found : '';
    }

    if (angular.isDefined($attrs.bnsDay)) {
      var foundDay = bnsDay.guess(choice, $attrs.bnsDay || 'value');

      res += foundDay ? 'bns-day-'+foundDay : '';
    }

    return res;
  }

}

})(angular);
