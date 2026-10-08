const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const scripts = ['assets/js/offline-sync.js', 'themes/command-ledger/assets/js/offline-sync.js'];
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

function browser(script, indexedDB, fetch, locks) {
    const events = {};
    const window = {
        indexedDB, isSecureContext: true, location: {reload() {}}, setTimeout() {},
        addEventListener(name, callback) { events[name] = callback; }
    };
    const navigator = {
        onLine: true, locks,
        serviceWorker: {controller: {}, addEventListener() {}}
    };
    const document = {querySelectorAll() {return [];}, addEventListener() {}};
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '..', script), 'utf8'), {
        window, navigator, document, fetch, Promise, URLSearchParams
    });
    return {events, navigator};
}

for (const script of scripts) {
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
