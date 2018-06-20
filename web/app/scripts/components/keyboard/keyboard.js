(function (angular) {
  'use strict';

  /**
   * @ngdoc module
   * @name bns.components.keyboard
   * @description The Virtual Keyboard Module module
   */
  angular.module('bns.components.keyboard', [
  ])

    .directive('bnsKeyboard', BNSKeyboardDirective)
    .directive('bnsUseKeyboard', BNSUseKeyboardDirective)
    .directive('bnsKeyboardInput', BNSKeyboardIputDirective)
    .controller('Keyboard', BNSKeyboardController)
    .controller('KeyboardInput', BNSKeyboardInputController)
    .factory('bnsKeyboard', BNSKeyboardService)

  ;

  function BNSKeyboardDirective($mdBottomSheet, bnsKeyboard) {
    return {
      restrict: 'EA',
      link: function postLink(scope, element) {
        bnsKeyboard.element = element;

        // When navigation force destroys an interimElement, then
        // listen and $destroy() that interim instance...
        scope.$on('$destroy', function () {
          $mdBottomSheet.cancel();
        });
      }
    };
  }

  function BNSKeyboardService (_) {
    var layouts = [];
    var currentLayout;
    var inputs = [];
    var currentInput;
    var currentModel;
    var keyboardBottomSheet;

    var service = {
      addLayout: addLayout,
      getLayout: getLayout,
      getCurrentLayout: getCurrentLayout,
      setLayout: setLayout,
      addInput: addInput,
      getNextInput: getNextInput,
      getPreviousInput: getPreviousInput,
      setInput: setInput,
      getInput: getInput,
      removeInput: removeInput,
      isOpen: false,
      currentModel: currentModel,
      keyboardBottomSheet: keyboardBottomSheet,
    };

    init();

    return service;

    function init() {
      // init layouts
      addLayout('beneylu', [
        [ ['Spacer'], ['1'], ['2'], ['3'], ['4'], ['5'], ['6'], ['7'], ['8'], ['9'], ['0'], ['Spacer'] ],
        [ {value:'a', label:'A'}, {value:'z', label:'Z'}, {value:'e', label:'E'}, {value:'r', label:'R'}, {value:'t', label:'T'}, {value:'y', label:'Y'}, {value:'u', label:'U'}, {value:'i', label:'I'}, {value:'o', label:'O'}, {value:'p', label:'P'}, { value: 'Bksp', label: '' }],
        [ ['Spacer'], {value:'q', label:'Q'}, {value:'s', label:'S'}, {value:'d', label:'D'}, {value:'f', label:'F'}, {value:'g', label:'G'}, {value:'h', label:'H'}, {value:'j', label:'J'}, {value:'k', label:'K'}, {value:'l', label:'L'}, {value:'m', label:'M'}, ['Spacer'] ],
        [ {value:'w', label:'W'}, {value:'x', label:'X'}, {value:'c', label:'C'}, {value:'v', label:'V'}, {value:'b', label:'B'}, {value:'n', label:'N'}, ['spacer'], ['spacer'], ['-'], ['\''], ['Spacer'], ['Spacer'], ],
        [['Spacer'], [' '], ['Spacer'] ]
      ]);

      addLayout('beneylu_num_disabled', [
        _.map([ ['Spacer'], ['1'], ['2'], ['3'], ['4'], ['5'], ['6'], ['7'], ['8'], ['9'], ['0'], ['Spacer'] ], _disableKey),
        [ {value:'a', label:'A'}, {value:'z', label:'Z'}, {value:'e', label:'E'}, {value:'r', label:'R'}, {value:'t', label:'T'}, {value:'y', label:'Y'}, {value:'u', label:'U'}, {value:'i', label:'I'}, {value:'o', label:'O'}, {value:'p', label:'P'}, ['Bksp'] ],
        [ ['Spacer'], {value:'q', label:'Q'}, {value:'s', label:'S'}, {value:'d', label:'D'}, {value:'f', label:'F'}, {value:'g', label:'G'}, {value:'h', label:'H'}, {value:'j', label:'J'}, {value:'k', label:'K'}, {value:'l', label:'L'}, {value:'m', label:'M'}, ['Spacer'] ],
        [ {value:'w', label:'W'}, {value:'x', label:'X'}, {value:'c', label:'C'}, {value:'v', label:'V'}, {value:'b', label:'B'}, {value:'n', label:'N'}, ['Spacer'], ['Spacer'], ['-'], ['\''], ['Spacer'], ['Spacer'] ],
        [ ['Spacer'], [' '], ['Spacer'] ]
      ]);

      addLayout('beneylu_num_only', [
        [ ['Spacer'], ['1'], ['2'], ['3'], ['4'], ['5'], ['6'], ['7'], ['8'], ['9'], ['0'], ['Spacer'] ],
        [ {value:'a', label:'A', disabled: true}, {value:'z', label:'Z', disabled: true}, {value:'e', label:'E', disabled: true}, {value:'r', label:'R', disabled: true}, {value:'t', label:'T', disabled: true}, {value:'y', label:'Y', disabled: true}, {value:'u', label:'U', disabled: true}, {value:'i', label:'I', disabled: true}, {value:'o', label:'O', disabled: true}, {value:'p', label:'P', disabled: true}, ['Bksp']],
        _.map([ ['Spacer'], {value:'q', label:'Q'}, {value:'s', label:'S'}, {value:'d', label:'D'}, {value:'f', label:'F'}, {value:'g', label:'G'}, {value:'h', label:'H'}, {value:'j', label:'J'}, {value:'k', label:'K'}, {value:'l', label:'L'}, {value:'m', label:'M'}, ['Spacer'] ], _disableKey),
        _.map([ {value:'w', label:'W'}, {value:'x', label:'X'}, {value:'c', label:'C'}, {value:'v', label:'V'}, {value:'b', label:'B'}, {value:'n', label:'N'}, ['Spacer'], ['Spacer'], ['-'], ['\''], ['Spacer'], ['Spacer'] ], _disableKey),
          [ ['Spacer'], _disableKey([' ']), ['Spacer'] ],
      ]);

      addLayout('beneylu_password', angular.copy(getLayout('beneylu_num_disabled')));
      addLayout('beneylu_password_num', angular.copy(getLayout('beneylu_num_only')));

      setLayout('beneylu');

    }

    function getLayout (name) {
      if (name && layouts[name]) {
        return layouts[name];
      } else if (currentLayout && layouts[currentLayout]) {
        return layouts[currentLayout];
      } else {
        return layouts[0] || [];
      }
    }

    function getCurrentLayout () {
      return currentLayout;
    }

    function setLayout (name) {
      if (layouts[name]) {
        currentLayout = name;
      }
    }

    function addLayout (name, keys) {
      var rows = [];
      _.each(keys, function(row){
        var newRow = [];
        _.each(row, function(key) {
          var item = {
            value: key.value || key[0]
          };
          item.shiftValue = key.shiftValue || key[1] || item.value;
          item.label = key.label || item.value;
          item.shiftLabel = key.shiftLabel || item.shiftValue;
          item.icon = _getIcon(item.label);
          item.spacer = item.value && item.value.toLowerCase() === 'spacer';
          item.disabled = !!key.disabled;

          newRow.push(item);
        });
        rows.push(newRow);
      });

      layouts[name] = rows;
    }

    function _getIcon (keyValue) {
      switch (keyValue ? keyValue.toLowerCase() : keyValue) {
        case 'bksp':
          return 'keyboard_backspace';
        case 'tab':
          return 'keyboard_tab';
        case 'caps':
          return 'keyboard_capslock';
        case 'enter':
          return 'keyboard_return';
        case 'shift':
          return 'keyboard_arrow_up';
        default:
          return false;
      }
    }

    function _disableKey (key) {
      key.disabled = true;
      return key;
    }

    function addInput (element, position) {
      if (angular.isDefined(position) && inputs.length >= position && position >= 0) {
        inputs.splice(position, 0, element);
      } else {
        inputs.push(element);
      }
    }

    function getNextInput (element) {
      var index = _.indexOf(inputs, element);
      if (index >= 0 && inputs[index + 1]) {
        return inputs[index + 1];
      }

      return false;
    }

    function getPreviousInput (element) {
      var index = _.indexOf(inputs, element);
      if (index > 0) {
        return inputs[index - 1];
      }

      return false;
    }

    function setInput (element) {
      currentInput = element;
    }

    function removeInput (element) {
      if (element === currentInput) {
        currentInput = null;
      }
      _.pull(inputs, element);
    }

    function getInput () {
      if (!currentInput && inputs[0]) {
        setInput(inputs[0]);
      }

      return currentInput;
    }
  }

  function BNSUseKeyboardDirective ($compile, $mdMedia, $mdBottomSheet, bnsKeyboard, parameters, $timeout) {
    return {
      restrict: 'A',
      require: '?ngModel',
      link: postLink,
    };

    function postLink (scope, element, attrs, ngModelCtrl) {
      if (!parameters.has_virtual_keyboard) {
        return;
      }

      // requires ngModel silently
      if (!ngModelCtrl) {
        return;
      }

      // Don't show virtual keyboard in mobile devices (default)
      if ($mdMedia.hasTouch) {
        return;
      }

      if (attrs.bnsKeyboardOpen) {
        addKeyboardOpen();
      }

      scope.keyboardOpen = false;
      scope.openKeyboard = openKeyboard;
      scope.showKeyboard = showKeyboard;
      scope.hideKeyboard = hideKeyboard;

      bnsKeyboard.addInput(element, attrs.bnsKeyboardPosition);

      // open keyboard on event bns.keyboard.open
      element
        .on('bns.keyboard.open', showKeyboard)
        .on('bns.keyboard.close', hideKeyboard)
        .on('$destroy', function () {
          bnsKeyboard.removeInput(element);
        })
      ;

      function openKeyboard(event) {
        if (bnsKeyboard.isOpen) {
          hideKeyboard(event);
        } else {
          showKeyboard(event);
        }
      }

      function showKeyboard(event) {
        if (event) {
          event.stopPropagation();
        }

        bnsKeyboard.currentModel = ngModelCtrl;
        bnsKeyboard.setLayout(attrs.bnsUseKeyboard);
        bnsKeyboard.setInput(element);

        if (!bnsKeyboard.isOpen) {
          // no keyboard active, so add new
          bnsKeyboard.keyboardBottomSheet = $mdBottomSheet.show({
            templateUrl: 'views/components/keyboard/keyboard.html',
            disableBackdrop: false,
            controller: 'Keyboard',
            controllerAs: 'keyboard',
            bindToController: true,
            clickOutsideToClose: true,
            disableParentScroll: false,
          });
          scope.keyboardOpen = true;
          bnsKeyboard.isOpen = true;
          bnsKeyboard.keyboardBottomSheet.finally(function(){
            scope.keyboardOpen = false;
            bnsKeyboard.isOpen = false;
            bnsKeyboard.getInput().triggerHandler('bns.keyboardInput.close');
          });
        }
        element.focus();
        $timeout(function() {
          element[0].selectionStart = element[0].selectionEnd = 10000;
        }, 0);
        element.triggerHandler('bns.keyboardInput.open');
      }

      function hideKeyboard () {
        if (bnsKeyboard.keyboardBottomSheet) {
          $mdBottomSheet.cancel();
        }
      }

      function addKeyboardOpen () {
        var toggler = angular.element('<md-button tabindex="-1" class="password-toggle md-icon-button" ng-click="openKeyboard($event)" ng-href=""><md-icon>{{ keyboardOpen ? "keyboard_hide" : "keyboard" }}</md-icon></md-button>');
        $compile(toggler)(scope);
        element.parent().addClass('bns-password-toggle-container').append(toggler);
      }
    }
  }

  function BNSKeyboardController ($scope, bnsKeyboard) {
    var keyboard = this;
    keyboard.pressed = triggerKey;
    keyboard.getKeyClass = getKeyClass;

    function getKeyClass (key) {
      var k = (key.value || ' ').toLowerCase();
      var keys = ['bksp', 'tab', 'caps', 'enter', 'shift', 'alt', 'altgr', 'altlk'];

      // space bar
      if (k === ' ') {
        k = 'space';
      }
      // special key
      else if (keys.indexOf(k) < 0) {
        k = 'char';
      }
      // spacer helper element
      else if (k === 'spacer') {
        return k;
      }

      return 'key-' + k;
    }

    function triggerKey ($event, key) {
      $event.preventDefault();

      switch ($scope.caps || $scope.capsLocked ? key.shiftValue : key.value) {
        case 'Caps':
          $scope.capsLocked = !$scope.capsLocked;
          $scope.caps = false;
          break;

        case 'Shift':
          $scope.caps = !$scope.caps;
          break;

        case 'Alt':
        case 'AltGr':
        case 'AltLk':
          // modify input, visualize
          //self.VKI_modify(type);
          break;

        case 'Tab':
          // TODO: handle text selection

          bnsKeyboard.currentModel.$setViewValue((bnsKeyboard.currentModel.$viewValue || '') + '\t');
          bnsKeyboard.currentModel.$validate();
          bnsKeyboard.currentModel.$render();

          break;

        case 'Bksp':
          // TODO: handle text selection

          bnsKeyboard.currentModel.$setViewValue((bnsKeyboard.currentModel.$viewValue || '').slice(0, -1));
          bnsKeyboard.currentModel.$validate();
          bnsKeyboard.currentModel.$render();

          break;

        case 'Enter':
            bnsKeyboard.currentModel.$setViewValue((bnsKeyboard.currentModel.$viewValue || '') + '\n');
            bnsKeyboard.currentModel.$validate();
            bnsKeyboard.currentModel.$render();

          break;

        default:
          bnsKeyboard.currentModel.$setViewValue((bnsKeyboard.currentModel.$viewValue || '') + ($scope.caps || $scope.capsLocked ? key.shiftValue : key.value));
          bnsKeyboard.currentModel.$validate();
          bnsKeyboard.currentModel.$render();

          $scope.caps = false;
      }
    }

    function init () {
      keyboard.keys = bnsKeyboard.getLayout();
    }

    $scope.$watch(function() {
      return bnsKeyboard.currentModel.$viewValue;
    }, function (newValue) {
      if (bnsKeyboard.getCurrentLayout() === 'beneylu_password' || bnsKeyboard.getCurrentLayout() === 'beneylu_password_num') {
        if (/^[a-z]{6}/.test(newValue)) {
          bnsKeyboard.setLayout('beneylu_password_num');
          init();
        } else {
          bnsKeyboard.setLayout('beneylu_password');
          init();
        }
      }
    });

    init();
  }

  function BNSKeyboardIputDirective (bnsKeyboard, $mdPanel, $timeout, $window) {
    return {
      restrict: 'A',
      require: '?ngModel',
      link: postLink,
    };

    function postLink (scope, element, attrs, ngModelCtrl) {
      var parent = element.parent();
      var position = $mdPanel.newPanelPosition()
          .relativeTo(parent)
          .addPanelPosition($mdPanel.xPosition.OFFSET_START, $mdPanel.yPosition.CENTER)
          .addPanelPosition($mdPanel.xPosition.OFFSET_END, $mdPanel.yPosition.CENTER)
          .addPanelPosition($mdPanel.xPosition.CENTER, $mdPanel.yPosition.ABOVE)
        ;
      var panelRef;
      element
        .on('click', function(event){
          event.stopPropagation();
        })
        .on('bns.keyboardInput.open', showInput)
        .on('bns.keyboardInput.close', hideInput)
        .on('$destroy', hideInput)
        .on('bns.submit', function() {
          scope.ctrl.submitLogin();
        })
      ;

      function updatePosition () {
        if (panelRef) {
          panelRef.updatePosition($mdPanel.newPanelPosition()
            .relativeTo(parent)
            .addPanelPosition($mdPanel.xPosition.OFFSET_START, $mdPanel.yPosition.CENTER)
            .addPanelPosition($mdPanel.xPosition.OFFSET_END, $mdPanel.yPosition.CENTER)
            .addPanelPosition($mdPanel.xPosition.CENTER, $mdPanel.yPosition.ABOVE));
        }
      }

      function showInput () {
        parent.addClass('bns-highlight-input');
        angular.element($window)
          .off('resize', updatePosition)
          .on('resize', updatePosition)
        ;

        // scroll if element is near top of the screen
        if (element[0].getBoundingClientRect().top < 100) {
          $window.scrollBy(0, element[0].getBoundingClientRect().top - 100);
        }

        // scroll if element is near end of viewport (340px is roughly the height of keyboard)
        if (element[0].getBoundingClientRect().bottom > $window.innerHeight - 340) {
          $window.scrollBy(0, element[0].getBoundingClientRect().bottom - ($window.innerHeight - 340));
        }

        if (bnsKeyboard.isOpen) {


          var config = {
            attachTo: angular.element($window.document.body),
            controller: 'KeyboardInput',
            controllerAs: 'keyboardInput',
            position: position,
            panelClass: 'keyboard-input-panel',
            locals: {
              label: attrs.bnsKeyboardLabel,
            },
            templateUrl: 'views/components/keyboard/keyboard-inputs.html',
            hasBackdrop: false,
            clickOutsideToClose: false,
            escapeToClose: false,
            focusOnOpen: false,
            transformTemplate: function (template) {
              return '<div class="md-panel-outer-wrapper bns-keyboard-input-panel-oute-wrapper">' +
              '  <div class="md-panel" style="left: -9999px;">' + (template||'') + '</div>' +
              '</div>';
            },
          };

          $mdPanel.open(config)
            .then(function(result){
              panelRef = result;
            })
          ;

          ngModelCtrl.$validators.customValidator = function (modelValue, viewValue) {
            if (ngModelCtrl.$isEmpty(modelValue)) {
              // consider empty models to be invalid
              return false;
            }

            switch (attrs.bnsKeyboardValidation) {
              case 'username':
              case 'name':
                if (viewValue.length >= 2) {
                  // it is valid
                  return true;
                }
                break;
              case 'password':
                if (/^[a-z]{6}[0-9]{3}/.test(viewValue)) {
                  // it is valid
                  return true;
                }
                break;
            }

            // it is invalid
            return false;
          };

          if (ngModelCtrl.$dirty) {
            ngModelCtrl.$validate();
          }
        }
      }

      function hideInput () {
        $timeout(function () {
          ngModelCtrl.$validators.customValidator = function() { return true;};
          ngModelCtrl.$validate();
          if (panelRef) {
            panelRef.close();
          }
          if (parent) {
            parent.removeClass('bns-highlight-input');
          }
          angular.element($window).off('resize', updatePosition);
        });
      }

    }

  }

  function BNSKeyboardInputController (bnsKeyboard) {
    var keyboardInput = this;
    keyboardInput.next = next;
    keyboardInput.previous = previous;
    keyboardInput.submit = submit;
    keyboardInput.currentModel = bnsKeyboard.currentModel;

    init();

    function init () {
      keyboardInput.hasNext = !!bnsKeyboard.getNextInput(bnsKeyboard.getInput());
      keyboardInput.hasPrevious = !!bnsKeyboard.getPreviousInput(bnsKeyboard.getInput());
      keyboardInput.hasSubmit = !keyboardInput.hasNext;
      keyboardInput.canNext = true;
      keyboardInput.canSubmit = true;
    }

    function next () {
      // prevent the keyboard to close
      var current = bnsKeyboard.getInput();
      if (current) {
        current.triggerHandler('bns.keyboardInput.close');
        var nextInput = bnsKeyboard.getNextInput(current);
        if (nextInput) {
          nextInput.triggerHandler('bns.keyboard.open');
        }
      }
    }

    function submit () {
      // prevent the keyboard to close
      var current = bnsKeyboard.getInput();
      current.triggerHandler('bns.keyboardInput.close');
      current.triggerHandler('bns.keyboard.close');
      current.triggerHandler('bns.submit');
    }

    function previous () {
      // prevent the keyboard to close
      var current = bnsKeyboard.getInput();
      if (current) {
        current.triggerHandler('bns.keyboardInput.close');
        var prevInput = bnsKeyboard.getPreviousInput(current);
        if (prevInput) {
          prevInput.triggerHandler('bns.keyboard.open');
        }
      }
    }
  }

})(angular);
