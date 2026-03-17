'use strict';

const assert = require('node:assert');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function createClassList(initial = []) {
  const set = new Set(initial);
  return {
    add(value) {
      set.add(value);
    },
    remove(value) {
      set.delete(value);
    },
    contains(value) {
      return set.has(value);
    }
  };
}

function loadMilkSelect() {
  const sourcePath = path.resolve(__dirname, '../../../../milkadmin/Theme/Plugins/MilkSelect/milkselect.js');
  const source = fs.readFileSync(sourcePath, 'utf8');

  const context = {
    console,
    setTimeout,
    clearTimeout,
    Event: class Event {
      constructor(type, init = {}) {
        this.type = type;
        this.bubbles = !!init.bubbles;
      }
    },
    CustomEvent: class CustomEvent {
      constructor(type, init = {}) {
        this.type = type;
        this.detail = init.detail;
      }
    },
    document: {
      readyState: 'complete',
      body: {},
      addEventListener() {},
      removeEventListener() {},
      getElementById() {
        return null;
      }
    },
    window: {},
    MutationObserver: undefined
  };

  context.window = context;

  vm.createContext(context);
  vm.runInContext(`${source}\nthis.__MilkSelect = MilkSelect;`, context);

  return {
    MilkSelect: context.__MilkSelect,
    context
  };
}

function createBareInstance(MilkSelect) {
  const instance = Object.create(MilkSelect.prototype);

  instance.options = [];
  instance.optionItems = [];
  instance.valueMap = {};
  instance.saveByValue = false;
  instance.selectedValues = [];
  instance.selectedKeys = [];
  instance.activeIndex = -1;
  instance.currentInput = {
    value: '',
    classList: createClassList(),
    focus() {},
    setAttribute() {},
    removeAttribute() {}
  };
  instance.initialDisplayValue = null;
  instance.isFirstOpen = true;
  instance.placeholder = 'Test';
  instance.isFloating = false;
  instance.apiUrl = null;
  instance.fetchTimeout = null;
  instance.isLoading = false;
  instance.isEditing = false;
  instance.isReadonly = false;
  instance.isDestroyed = false;
  instance.isMultiple = false;
  instance.isRequired = false;

  instance.hiddenInput = {
    value: '',
    dataset: {},
    classList: createClassList(),
    dispatchEvent() {},
    setAttribute() {},
    removeAttribute() {}
  };

  instance.dropdown = {
    innerHTML: '',
    classList: {
      contains() {
        return false;
      },
      add() {},
      remove() {}
    },
    querySelectorAll() {
      return [];
    },
    appendChild() {}
  };

  instance.wrapper = {
    classList: createClassList(),
    addEventListener() {},
    removeEventListener() {},
    querySelector() {
      return null;
    },
    querySelectorAll() {
      return [];
    }
  };

  instance.container = {
    events: [],
    dispatchEvent(event) {
      this.events.push(event);
    }
  };

  instance.validationInput = null;
  instance.removeClearButton = () => {};
  instance.hideDropdown = () => {};
  instance.showDropdownIfNeeded = () => {};
  instance.filterOptions = () => {};
  instance.isFormValidated = () => false;
  instance.setContainerInvalid = () => {};

  return instance;
}

function runTests() {
  const { MilkSelect, context } = loadMilkSelect();

  {
    const instance = createBareInstance(MilkSelect);
    instance.setOptions([
      { value: 'config.id', text: 'id', group: 'config' }
    ]);

    const added = instance.addOption({ value: 'config.ANNO_SCOL', text: 'ANNO_SCOL', group: 'config' });
    assert.strictEqual(added, true, 'addOption should return true for valid options');
    assert.strictEqual(instance.optionItems.length, 2, 'addOption should append a new option');
    assert.strictEqual(instance.optionItems[1].value, 'config.ANNO_SCOL');
    assert.strictEqual(instance.optionItems[1].group, 'config');
    assert.strictEqual(instance.container.events.at(-1).type, 'optionAdded');
  }

  {
    const instance = createBareInstance(MilkSelect);
    instance.setOptions([
      { value: 'config.id', text: 'id', group: 'config' },
      { value: 'config.ANNO_SCOL', text: 'ANNO_SCOL', group: 'config' }
    ]);
    instance.selectedKeys = ['config.id'];
    instance.selectedValues = ['id'];
    instance.hiddenInput.value = 'config.id';
    instance.currentInput.value = 'id';
    instance.currentInput.classList.add('has-value');

    const removed = instance.removeOption('config.id');
    assert.strictEqual(removed, true, 'removeOption should return true when an option is removed');
    assert.strictEqual(instance.optionItems.length, 1, 'removeOption should remove the matching option');
    assert.strictEqual(instance.selectedKeys.length, 0, 'removeOption should clear single selected key if removed');
    assert.strictEqual(instance.selectedValues.length, 0, 'removeOption should clear single selected display value if removed');
    assert.strictEqual(instance.hiddenInput.value, '', 'removeOption should sync hidden input after selection removal');
    assert.strictEqual(instance.currentInput.value, '', 'removeOption should clear visible input if selected option is removed');
    assert.strictEqual(instance.container.events.at(-1).type, 'optionRemoved');
  }

  {
    const instance = createBareInstance(MilkSelect);
    instance.filterOptions = () => {};
    instance.dropdown.classList.remove = () => {};

    instance.setOptions([
      { value: 'config.id', text: 'id', group: 'config' },
      { value: 'config.ANNO_SCOL', text: 'ANNO_SCOL', group: 'config' }
    ]);
    instance.selectedKeys = ['config.ANNO_SCOL'];
    instance.selectedValues = ['ANNO_SCOL'];
    instance.hiddenInput.value = 'config.ANNO_SCOL';
    instance.currentInput.value = 'ANNO_SCOL';

    instance.updateOptions([
      { value: 'config.id', text: 'id', group: 'config' }
    ], true);

    assert.strictEqual(instance.selectedKeys.length, 0, 'updateOptions(syncSelection=true) should remove stale selected key');
    assert.strictEqual(instance.selectedValues.length, 0, 'updateOptions(syncSelection=true) should remove stale selected text');
    assert.strictEqual(instance.hiddenInput.value, '', 'updateOptions(syncSelection=true) should sync hidden input');
    assert.strictEqual(instance.container.events.at(-1).type, 'optionsUpdated');
  }

  {
    const instance = createBareInstance(MilkSelect);
    let removedFromDom = false;
    let listenersRemoved = false;

    instance.fetchTimeout = setTimeout(() => {}, 2000);
    instance.container = {
      parentNode: {
        removeChild(node) {
          if (node === instance.container) {
            removedFromDom = true;
          }
        }
      }
    };
    instance.hiddenInput = {
      dataset: { milkselectInitialized: 'true' },
      milkSelectInstance: instance,
      setAttribute(name, value) {
        this[name] = value;
      }
    };
    instance.isRequired = true;
    instance.hideDropdown = () => {};
    instance.removeEventListeners = () => {
      listenersRemoved = true;
    };

    instance.destroy();

    assert.strictEqual(instance.isDestroyed, true, 'destroy should mark instance as destroyed');
    assert.strictEqual(removedFromDom, true, 'destroy should remove generated container from DOM');
    assert.strictEqual(listenersRemoved, true, 'destroy should detach listeners');
    assert.strictEqual(instance.hiddenInput.dataset.milkselectInitialized, 'false', 'destroy should reset initialized flag');
    assert.strictEqual(Object.prototype.hasOwnProperty.call(instance.hiddenInput, 'milkSelectInstance'), false, 'destroy should remove instance reference');
  }

  {
    const instance = createBareInstance(MilkSelect);
    let showDropdownCalls = 0;
    let focused = false;
    instance.showDropdown = () => {
      showDropdownCalls += 1;
    };
    instance.currentInput.focus = () => {
      focused = true;
    };

    instance.clearSingleValue(false);
    assert.strictEqual(showDropdownCalls, 0, 'clearSingleValue(false) must not reopen dropdown');
    assert.strictEqual(focused, false, 'clearSingleValue(false) must not force focus');

    instance.clearSingleValue(true);
    assert.strictEqual(showDropdownCalls, 1, 'clearSingleValue(true) should reopen dropdown once');
    assert.strictEqual(focused, true, 'clearSingleValue(true) should focus input before reopening');
  }

  {
    const instance = createBareInstance(MilkSelect);
    let showDropdownCalls = 0;
    instance.showDropdown = () => {
      showDropdownCalls += 1;
    };
    instance.apiUrl = null;

    instance.handleSingleFocus({});
    instance.handleMultipleFocus();

    assert.strictEqual(showDropdownCalls, 0, 'focus handlers must not auto-open dropdown');
  }

  {
    let destroyCalled = false;
    const fakeInstance = {
      destroy() {
        destroyCalled = true;
      }
    };

    context.document.getElementById = (id) => {
      if (id === 'milkselect-test-id') {
        return { milkSelectInstance: fakeInstance };
      }
      return null;
    };

    assert.strictEqual(MilkSelect.destroy('milkselect-test-id'), true, 'static destroy should return true when instance exists');
    assert.strictEqual(destroyCalled, true, 'static destroy should call instance destroy()');
    assert.strictEqual(MilkSelect.remove('milkselect-missing-id'), false, 'static remove should return false when instance does not exist');
  }
}

try {
  runTests();
  console.log('milkselect.runtime.test.js: OK');
} catch (error) {
  console.error('milkselect.runtime.test.js: FAILED');
  console.error(error.stack || error.message || error);
  process.exit(1);
}
