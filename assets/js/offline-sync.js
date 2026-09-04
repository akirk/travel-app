(function() {
    var config = window.travelerPwa || {};
    var messages = config.messages || {};
    var dbName = 'traveler-offline';
    var storeName = 'mutations';
    var offlineState = {
        connection: navigator.onLine ? 'Online' : 'Offline',
        worker: 'Checking',
        cache: 'Checking',
        files: 'Checking',
        queue: '0 pending'
    };
    var workerState = 'Checking';
    var workerVersion = '';

    function updateOfflinePanel() {
        document.querySelectorAll('[data-offline-panel]').forEach(function(panel) {
            Object.keys(offlineState).forEach(function(key) {
                panel.querySelectorAll('[data-offline-' + key + ']').forEach(function(target) {
                    target.textContent = offlineState[key];
                });
            });
        });
    }

    function setOfflineState(key, value) {
        offlineState[key] = value;
        updateOfflinePanel();
    }

    function setWorkerState(value) {
        workerState = value;
        offlineState.worker = workerVersion ? workerState + ', ' + workerVersion : workerState;
        updateOfflinePanel();
    }

    function setWorkerVersion(value) {
        workerVersion = value ? String(value).replace(/^traveler-/, '') : '';
        offlineState.worker = workerVersion ? workerState + ', ' + workerVersion : workerState;
        updateOfflinePanel();
    }

    function offlineCacheUrl(element) {
        var url = element.getAttribute('href') || element.getAttribute('src') || element.getAttribute('data-offline-cache-url');
        return url ? new URL(url, window.location.href).href : '';
    }

    function offlineCacheElements() {
        return Array.prototype.slice.call(document.querySelectorAll('[data-offline-cache-url]'));
    }

    function ensureAttachmentIndicator(element) {
        var indicator = element.querySelector('[data-offline-attachment-indicator]');
        if (indicator) {
            return indicator;
        }

        var icon = element.querySelector('span:first-child') || element;
        icon.classList.add('attachment-download-icon');
        indicator = document.createElement('span');
        indicator.className = 'attachment-offline-indicator';
        indicator.setAttribute('data-offline-attachment-indicator', '');
        indicator.setAttribute('aria-label', 'Available offline');
        indicator.setAttribute('title', 'Available offline');
        indicator.textContent = '✓';
        indicator.hidden = true;
        element.insertBefore(indicator, icon.nextSibling);
        return indicator;
    }

    function updateAttachmentAvailability(cachedUrls) {
        var cached = {};
        (cachedUrls || []).forEach(function(url) {
            cached[url] = true;
        });

        offlineCacheElements().forEach(function(element) {
            var indicator = ensureAttachmentIndicator(element);
            var available = !!cached[offlineCacheUrl(element)];
            element.toggleAttribute('data-offline-available', available);
            indicator.hidden = !available;
        });
    }

    function openDb() {
        return new Promise(function(resolve, reject) {
            if (!window.indexedDB) {
                reject(new Error('IndexedDB is unavailable.'));
                return;
            }

            var request = window.indexedDB.open(dbName, 1);
            request.onupgradeneeded = function() {
                request.result.createObjectStore(storeName, { keyPath: 'id' });
            };
            request.onsuccess = function() {
                resolve(request.result);
            };
            request.onerror = function() {
                reject(request.error || new Error('Could not open offline queue.'));
            };
        });
    }

    function withStore(mode, callback) {
        return openDb().then(function(db) {
            return new Promise(function(resolve, reject) {
                var transaction = db.transaction(storeName, mode);
                var store = transaction.objectStore(storeName);
                var result = callback(store);

                transaction.oncomplete = function() {
                    db.close();
                    resolve(result);
                };
                transaction.onerror = function() {
                    db.close();
                    reject(transaction.error || new Error('Offline queue failed.'));
                };
            });
        });
    }

    function putMutation(mutation) {
        return withStore('readwrite', function(store) {
            store.put(mutation);
        });
    }

    function deleteMutation(id) {
        return withStore('readwrite', function(store) {
            store.delete(id);
        });
    }

    function getMutations() {
        return withStore('readonly', function(store) {
            return new Promise(function(resolve, reject) {
                var request = store.getAll();
                request.onsuccess = function() {
                    resolve(request.result || []);
                };
                request.onerror = function() {
                    reject(request.error || new Error('Could not read offline queue.'));
                };
            });
        }).then(function(result) {
            return result || [];
        });
    }

    function setStatus(message, isError) {
        document.querySelectorAll('[data-offline-status]').forEach(function(target) {
            target.textContent = message || '';
            target.classList.toggle('error', !!isError);
            target.hidden = !message;
        });
    }

    function reportError(message) {
        setStatus(message || (messages.syncFailed || 'Some offline changes could not sync yet.'), true);
    }

    function setFormStatus(form, message, isError) {
        var target = form.querySelector('[data-offline-form-status]');

        if (!target) {
            target = document.createElement('div');
            target.className = 'offline-status';
            target.setAttribute('data-offline-form-status', '');
            target.setAttribute('role', 'status');
            target.setAttribute('aria-live', 'polite');
            form.appendChild(target);
        }

        target.textContent = message || '';
        target.classList.toggle('error', !!isError);
        target.hidden = !message;
    }

    function refreshQueueStatus() {
        return getMutations().then(function(mutations) {
            var count = mutations.length;
            setOfflineState('queue', count === 1 ? '1 pending' : count + ' pending');
            return count;
        }).catch(function() {
            setOfflineState('queue', 'Unavailable');
            return 0;
        });
    }

    function formToMutation(form) {
        var data = new FormData(form);
        var entries = [];
        var action = form.getAttribute('action') || window.location.href;
        var method = form.getAttribute('method') || 'POST';

        data.forEach(function(value, key) {
            if (value instanceof File) {
                return;
            }
            entries.push([key, value]);
        });

        return {
            id: String(Date.now()) + '-' + Math.random().toString(16).slice(2),
            url: new URL(action, window.location.href).href,
            method: method.toUpperCase(),
            entries: entries,
            createdAt: new Date().toISOString(),
            returnTo: window.location.href
        };
    }

    function mutationBody(mutation) {
        var body = new URLSearchParams();
        mutation.entries.forEach(function(entry) {
            body.append(entry[0], entry[1]);
        });
        return body.toString();
    }

    function replayMutation(mutation) {
        return fetch(mutation.url, {
            method: mutation.method || 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: mutationBody(mutation)
        }).then(function(response) {
            if (!response.ok) {
                throw new Error('Sync failed with HTTP ' + response.status + '.');
            }
            return deleteMutation(mutation.id);
        });
    }

    function flushQueue() {
        if (!navigator.onLine) {
            return Promise.resolve(false);
        }

        return getMutations().then(function(mutations) {
            if (!mutations.length) {
                setStatus('');
                setOfflineState('queue', '0 pending');
                return false;
            }

            mutations.sort(function(a, b) {
                return String(a.createdAt).localeCompare(String(b.createdAt));
            });
            setStatus(messages.syncing || 'Syncing offline changes...');

            return mutations.reduce(function(chain, mutation) {
                return chain.then(function() {
                    return replayMutation(mutation);
                }).then(function() {
                    return refreshQueueStatus();
                });
            }, Promise.resolve()).then(function() {
                setStatus(messages.synced || 'Offline changes synced.');
                window.setTimeout(function() {
                    window.location.reload();
                }, 600);
                return true;
            });
        }).catch(function() {
            setStatus(messages.syncFailed || 'Some offline changes could not sync yet.', true);
            return false;
        });
    }

    function bindPwaRuntime() {
        if (!window.isSecureContext) {
            setWorkerState('Needs HTTPS');
            setOfflineState('cache', 'Unavailable');
            setOfflineState('files', 'Unavailable');
            return;
        }

        if (!('serviceWorker' in navigator)) {
            setWorkerState('Not supported');
            setOfflineState('cache', 'Unavailable');
            setOfflineState('files', 'Unavailable');
            return;
        }

        setWorkerState(navigator.serviceWorker.controller ? 'Active' : 'Registering');

        window.addEventListener('traveler-sync', function() {
            flushQueue();
        });

        window.addEventListener('traveler-cache-status', function(event) {
            var detail = event.detail || {};
            setOfflineState('cache', detail.ok ? 'Ready offline' : 'Not cached');
            if (typeof detail.cachedCount === 'number' && typeof detail.totalCount === 'number') {
                setOfflineState('files', detail.cachedCount + ' of ' + detail.totalCount + ' files');
            }
            updateAttachmentAvailability(detail.cachedUrls || []);
        });

        window.addEventListener('traveler-version', function(event) {
            var detail = event.detail || {};
            setWorkerState(navigator.serviceWorker.controller ? 'Active' : 'Ready');
            setWorkerVersion(detail.version || '');
        });

        navigator.serviceWorker.addEventListener('controllerchange', function() {
            setWorkerState('Active');
        });

        window.addEventListener('load', function() {
            window.setTimeout(function() {
                if (!window.wpAppPwa) {
                    setWorkerState('Unavailable');
                    setOfflineState('cache', 'Unavailable');
                    setOfflineState('files', 'Unavailable');
                    return;
                }

                if (navigator.serviceWorker.controller) {
                    setWorkerState('Active');
                }
            }, 1000);
        });
    }

    function requestBackgroundSync() {
        if (!('serviceWorker' in navigator) || !('SyncManager' in window)) {
            return Promise.resolve();
        }

        return navigator.serviceWorker.ready.then(function(registration) {
            return registration.sync.register('traveler-sync');
        }).catch(function() {});
    }

    function queueForm(form) {
        setFormStatus(form, 'Saving offline...');

        return Promise.resolve().then(function() {
            return putMutation(formToMutation(form));
        }).then(function() {
            setStatus(messages.offlineQueued || 'Saved offline. Changes will sync when you are back online.');
            setFormStatus(form, messages.offlineQueued || 'Saved offline. Changes will sync when you are back online.');
            refreshQueueStatus();
            requestBackgroundSync();
        }).catch(function(error) {
            var message = error && error.message ? error.message : (messages.syncFailed || 'Some offline changes could not sync yet.');
            reportError(message);
            setFormStatus(form, message, true);
        });
    }

    function isOfflineSyncForm(element) {
        return element && element.nodeType === 1 && element.matches && element.matches('form[data-offline-sync]');
    }

    function canQueueForm(form) {
        return !form.enctype || form.enctype.toLowerCase() !== 'multipart/form-data';
    }

    function bindOfflineSubmitHandler() {
        document.addEventListener('submit', function(event) {
            var form = event.target;

            if (event.defaultPrevented || !isOfflineSyncForm(form)) {
                return;
            }

            if (!canQueueForm(form)) {
                if (!navigator.onLine) {
                    event.preventDefault();
                    setFormStatus(form, 'Uploading attachments requires an online connection.', true);
                }
                return;
            }

            if (navigator.onLine) {
                setFormStatus(form, '');
                return;
            }

            event.preventDefault();
            queueForm(form);
        });
    }

    function bindOfflineForm(form) {
        if (form.hasAttribute('data-offline-sync-bound')) {
            return;
        }

        form.setAttribute('data-offline-sync-bound', '1');
    }

    function bindOfflineForms() {
        document.querySelectorAll('form[data-offline-sync]').forEach(function(form) {
            bindOfflineForm(form);
        });
    }

    function getTripData() {
        var source = document.getElementById('traveler-trip-data');
        if (!source || !source.textContent) {
            return null;
        }

        try {
            return JSON.parse(source.textContent);
        } catch (error) {
            return null;
        }
    }

    function findSegment(tripData, id) {
        var segments = tripData && Array.isArray(tripData.segments) ? tripData.segments : [];
        id = String(id || '');

        return segments.find(function(segment) {
            return String(segment.id || '') === id;
        }) || null;
    }

    function setField(form, name, value) {
        var field = form.elements[name];
        if (!field) {
            return;
        }

        field.value = value == null ? '' : String(value);
    }

    function populateSegmentForm(form, tripData, segment) {
        setField(form, 'trip_id', tripData.id || '');
        setField(form, 'segment_index', segment.id || '');
        setField(form, '_wpnonce', segment.edit_nonce || '');
        setField(form, 'segment_title', segment.title || '');
        setField(form, 'segment_type', segment.type || 'other');
        setField(form, 'segment_url', segment.url || '');
        setField(form, 'segment_location', segment.location || '');
        setField(form, 'segment_end_location', segment.end_location || '');
        setField(form, 'segment_date', segment.date || '');
        setField(form, 'segment_time', segment.time || '');
        setField(form, 'segment_end_date', segment.end_date || '');
        setField(form, 'segment_end_time', segment.end_time || '');
        setField(form, 'segment_details', segment.details || '');

        var preview = segment.url_preview || {};
        setField(form, 'segment_url_preview_title', preview.title || '');
        setField(form, 'segment_url_preview_image', preview.image || '');
        setField(form, 'segment_url_preview_description', preview.description || '');
    }

    function populateDeleteSegmentForm(form, tripData, segment) {
        setField(form, 'trip_id', tripData.id || '');
        setField(form, 'segment_index', segment.id || '');
        setField(form, '_wpnonce', segment.delete_nonce || '');
    }

    function replaceContent(element, content) {
        while (element.firstChild) {
            element.removeChild(element.firstChild);
        }

        if (content) {
            element.appendChild(content);
        }
    }

    function renderInlineEditor(panel, segmentId) {
        var template = document.getElementById('segment-edit-template');
        var tripData = getTripData();
        var segment = findSegment(tripData, segmentId);

        if (!template || !tripData || !segment) {
            return null;
        }

        replaceContent(panel, template.content.cloneNode(true));
        var form = panel.querySelector('form[data-offline-sync]');
        if (!form) {
            return null;
        }

        populateSegmentForm(form, tripData, segment);
        bindOfflineForm(form);

        var deleteForm = panel.querySelector('.delete-segment-form');
        if (deleteForm) {
            populateDeleteSegmentForm(deleteForm, tripData, segment);
            deleteForm.id = 'delete-segment-form-' + String(segment.id || '');

            var deleteButton = panel.querySelector('.delete-item-link');
            if (deleteButton) {
                deleteButton.setAttribute('form', deleteForm.id);
            }
        }

        return form;
    }

    function bindInlineEditors() {
        function scrollBehavior() {
            return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';
        }

        document.querySelectorAll('[data-inline-edit-toggle]').forEach(function(button) {
            button.addEventListener('click', function() {
                var id = button.getAttribute('aria-controls');
                var panel = id ? document.getElementById(id) : null;
                if (!panel) {
                    return;
                }

                var wrap = panel.closest('.timeline-item-wrap') || panel.parentElement;
                var view = wrap ? wrap.querySelector('[data-inline-edit-view]') : null;
                var segmentId = id.replace(/^edit-segment-/, '');
                var form = renderInlineEditor(panel, segmentId);
                if (!form) {
                    return;
                }

                if (view) {
                    view.hidden = true;
                }
                panel.hidden = false;
                panel.scrollIntoView({ behavior: scrollBehavior(), block: 'nearest' });
                var titleInput = form.elements.segment_title;
                if (titleInput) {
                    titleInput.focus();
                    titleInput.select();
                }
            });
        });

        document.addEventListener('click', function(event) {
            var button = event.target.closest('[data-inline-edit-cancel]');
            if (!button) {
                return;
            }

            var panel = button.closest('[data-inline-edit-panel]');
            if (!panel) {
                return;
            }

            var wrap = panel.closest('.timeline-item-wrap') || panel.parentElement;
            var view = wrap ? wrap.querySelector('[data-inline-edit-view]') : null;
            replaceContent(panel);
            panel.hidden = true;
            if (view) {
                var editToggle = view.querySelector('[data-inline-edit-toggle]');
                view.hidden = false;
                view.scrollIntoView({ behavior: scrollBehavior(), block: 'nearest' });
                if (editToggle) {
                    editToggle.focus();
                }
            }
        });
    }

    updateOfflinePanel();
    bindOfflineSubmitHandler();
    bindOfflineForms();
    bindInlineEditors();
    bindPwaRuntime();
    refreshQueueStatus();
    window.addEventListener('online', function() {
        setOfflineState('connection', 'Online');
        flushQueue();
    });
    window.addEventListener('offline', function() {
        setOfflineState('connection', 'Offline');
    });
    window.addEventListener('error', function(event) {
        reportError(event.message ? 'JavaScript error: ' + event.message : 'JavaScript error.');
    });
    window.addEventListener('unhandledrejection', function(event) {
        var reason = event.reason;
        var message = reason && reason.message ? reason.message : 'Unhandled JavaScript promise rejection.';
        reportError('JavaScript error: ' + message);
    });
    flushQueue();
})();
