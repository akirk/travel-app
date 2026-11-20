const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

test('map instances keep their controls local and wait for consent', () => {
    const roots = [0, 1].map(() => {
        const listeners = {};
        const load = { addEventListener(name, handler, options) { listeners[name] = { handler, options }; } };
        return {
            dataset: {}, listeners,
            querySelector(selector) {
                if (selector === '[data-route-map-data]') return { content: { textContent: '{"entries":[]}' } };
                if (selector === '[data-map-load]') return load;
                return null;
            }
        };
    });
    const context = {
        window: {},
        document: {
            querySelectorAll(selector) {
                if (selector === '[data-route-map]') return roots;
                if (selector === '[data-shared-map-toggle]') return [];
                throw new Error('Unexpected global selector: ' + selector);
            }
        }
    };
    vm.runInNewContext(fs.readFileSync('assets/js/map.js', 'utf8'), context);
    for (const root of roots) {
        assert.equal(root.dataset.mapInitialized, '1');
        assert.equal(root.listeners.click.options.once, true);
    }
    assert.notEqual(roots[0].listeners.click.handler, roots[1].listeners.click.handler);
    new context.window.TravelAppRouteMap(roots[0], {});
});

test('shared map toggle updates visibility and asks the map to resize', () => {
    let handler;
    let expanded;
    let resized = false;
    const map = { dispatchEvent(event) { resized = event.type === 'route-map-resize'; } };
    const content = { hidden: true, querySelector() { return map; } };
    const toggle = {
        addEventListener(name, callback) { handler = callback; },
        setAttribute(name, value) { expanded = value; },
        getAttribute() { return 'shared-route-map-panel'; }
    };
    vm.runInNewContext(fs.readFileSync('assets/js/map.js', 'utf8'), {
        window: {}, Event,
        document: {
            querySelectorAll(selector) { return selector === '[data-shared-map-toggle]' ? [toggle] : []; },
            getElementById() { return content; }
        }
    });
    handler();
    assert.equal(content.hidden, false);
    assert.equal(expanded, 'true');
    assert.equal(resized, true);
    handler();
    assert.equal(content.hidden, true);
    assert.equal(expanded, 'false');
});

test('fullscreen expands in place, hides the sidebar, and Escape restores the map', () => {
    let click;
    let keydown;
    let resized = 0;
    const classes = new Set();
    const bodyClasses = new Set();
    const classList = set => ({ toggle(name, enabled) { enabled ? set.add(name) : set.delete(name); } });
    const attributes = {};
    const button = {
        dataset: { expandLabel: 'Expand map', collapseLabel: 'Restore map size' },
        addEventListener(name, handler) { click = handler; },
        setAttribute(name, value) { attributes[name] = value; },
        focus() {}
    };
    const root = {
        dataset: {}, classList: classList(classes),
        querySelector(selector) {
            if (selector === '[data-route-map-data]') return { content: { textContent: '{}' } };
            if (selector === '[data-map-fullscreen]') return button;
            if (selector === '[data-map-load]') return { addEventListener() {} };
            return null;
        },
        addEventListener(name, handler) { keydown = handler; },
        dispatchEvent() { resized++; }
    };
    vm.runInNewContext(fs.readFileSync('assets/js/map.js', 'utf8'), {
        window: {}, Event,
        document: {
            body: { classList: classList(bodyClasses) },
            querySelectorAll(selector) { return selector === '[data-route-map]' ? [root] : []; }
        }
    });
    click();
    assert.equal(classes.has('is-fullscreen'), true);
    assert.equal(bodyClasses.has('has-route-map-fullscreen'), true);
    assert.equal(attributes['aria-pressed'], 'true');
    assert.equal(attributes['aria-label'], 'Restore map size');
    keydown({ key: 'Escape', preventDefault() {}, stopImmediatePropagation() {} });
    assert.equal(classes.has('is-fullscreen'), false);
    assert.equal(bodyClasses.has('has-route-map-fullscreen'), false);
    assert.equal(attributes['aria-pressed'], 'false');
    assert.equal(attributes['aria-label'], 'Expand map');
    assert.equal(resized, 2);
});
