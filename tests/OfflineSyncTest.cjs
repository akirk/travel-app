const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const scripts = ['assets/js/offline-sync.js'];
const tick = () => new Promise(resolve => setImmediate(resolve));

function database() {
    const items = new Map([['pending-1', {
        id: 'pending-1', url: '/add', entries: [['title', 'Exit19']], createdAt: '2026-10-08'
    }]]);
    return {
        items,
        open() {
            const request = {};
            request.result = {
                close() {},
                transaction() {
                    const transaction = {};
                    transaction.objectStore = () => ({
                        getAll() {
                            const read = {};
                            queueMicrotask(() => {
                                read.result = Array.from(items.values());
                                read.onsuccess();
                                transaction.oncomplete();
                            });
                            return read;
                        },
                        delete(id) {
                            queueMicrotask(() => {
                                items.delete(id);
                                transaction.oncomplete();
                            });
                        }
                    });
                    return transaction;
                }
            };
            queueMicrotask(() => request.onsuccess());
            return request;
        }
    };
}

function browser(script, indexedDB, fetch, locks, pageDocument) {
    const events = {};
    const window = {
        indexedDB, isSecureContext: true, location: {reload() {}}, setTimeout() {},
        addEventListener(name, callback) { events[name] = callback; }
    };
    const navigator = {
        onLine: true, locks,
        serviceWorker: {controller: {}, addEventListener() {}}
    };
    const document = pageDocument || {querySelectorAll() {return [];}, addEventListener() {}};
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '..', script), 'utf8'), {
        window, navigator, document, fetch, Promise, URLSearchParams
    });
    return {events, navigator};
}

for (const script of scripts) {
    test(script + ': editing reads trip data from template content and populates the form', () => {
        let edit;
        let focused = false;
        const fields = Object.fromEntries([
            'trip_id', 'segment_index', '_wpnonce', 'segment_title', 'segment_details'
        ].map(name => [name, {value: ''}]));
        fields.segment_title.focus = () => {focused = true;};
        fields.segment_title.select = () => {};
        const form = {elements: fields, hasAttribute() {return false;}, setAttribute() {}};
        const view = {hidden: false};
        const panel = {
            hidden: true,
            appendChild() {},
            querySelector(selector) {return selector === 'form[data-offline-sync]' ? form : null;},
            closest() {return {querySelector() {return view;}};},
            scrollIntoView() {}
        };
        const button = {
            getAttribute() {return 'edit-segment-42';},
            addEventListener(name, callback) {edit = callback;}
        };
        const source = {textContent: '', content: {textContent: JSON.stringify({
            id: 93912, segments: [{id: 42, title: 'Train & hotel', details: 'A < B', edit_nonce: 'edit-42'}]
        })}};
        const document = {
            querySelectorAll(selector) {return selector === '[data-inline-edit-toggle]' ? [button] : [];},
            addEventListener() {},
            getElementById(id) {
                return {
                    'travel-app-trip-data': source,
                    'segment-edit-template': {content: {cloneNode() {return {};}}},
                    'edit-segment-42': panel
                }[id];
            }
        };
        browser(script, undefined, undefined, undefined, document);
        edit();
        assert.equal(panel.hidden, false);
        assert.equal(view.hidden, true);
        assert.equal(fields.trip_id.value, '93912');
        assert.equal(fields.segment_index.value, '42');
        assert.equal(fields._wpnonce.value, 'edit-42');
        assert.equal(fields.segment_title.value, 'Train & hotel');
        assert.equal(fields.segment_details.value, 'A < B');
        assert.equal(focused, true);
    });

    test(script + ': overlapping triggers submit an item once without Web Locks', async () => {
        const db = database();
        let finish;
        let calls = 0;
        const page = browser(script, db, () => {
            calls++;
            return new Promise(resolve => { finish = resolve; });
        });
        page.events.online();
        page.events['travel-app-sync']();
        await tick();
        page.events['travel-app-sync']();
        assert.equal(calls, 1);
        finish({ok: true});
        await tick();
        assert.equal(db.items.size, 0);
        page.events.online();
        await tick();
        assert.equal(calls, 1);
    });

    test(script + ': failed replay leaves the item queued and allows a later retry', async () => {
        const db = database();
        let calls = 0;
        const page = browser(script, db, () => {
            calls++;
            return calls === 1 ? Promise.reject(new Error('Network lost')) : Promise.resolve({ok: true});
        });
        await tick();
        assert.equal(calls, 1);
        assert.equal(db.items.size, 1);
        page.events.online();
        page.events['travel-app-sync']();
        await tick();
        assert.equal(calls, 2);
        assert.equal(db.items.size, 0);
    });

    test(script + ': a shared lock prevents replay by two tabs', async () => {
        const db = database();
        let tail = Promise.resolve();
        const names = [];
        const locks = {request(name, callback) {
            names.push(name);
            const result = tail.then(callback);
            tail = result.catch(() => {});
            return result;
        }};
        let calls = 0;
        let finish;
        const fetch = () => {
            calls++;
            return new Promise(resolve => {finish = resolve;});
        };
        const first = browser(script, db, fetch, locks);
        const second = browser(script, db, fetch, locks);
        first.events.online();
        second.events['travel-app-sync']();
        await tick();
        assert.equal(calls, 1);
        finish({ok: true});
        await tick();
        assert.equal(calls, 1);
        assert.equal(db.items.size, 0);
        assert.deepEqual(names, ['travel-app-offline-sync', 'travel-app-offline-sync']);
    });
}
