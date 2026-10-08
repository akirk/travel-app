    (function() {
        document.addEventListener('submit', function(event) {
            var form = event.target && event.target.closest ? event.target.closest('form[data-confirm]') : null;
            var message = form ? form.getAttribute('data-confirm') : '';

            if (message && !window.confirm(message)) {
                event.preventDefault();
            }
        });
    })();

    (function() {
        var button = document.querySelector('[data-trip-title-edit]');
        var form = document.getElementById('trip-title-form');

        if (!button || !form) {
            return;
        }

        button.addEventListener('click', function() {
            var titleInput = form.querySelector('input[name="trip_title"]');
            var isHidden = form.hasAttribute('hidden');

            if (isHidden) {
                form.removeAttribute('hidden');
                button.setAttribute('aria-expanded', 'true');

                if (titleInput) {
                    titleInput.focus();
                    titleInput.select();
                }

                return;
            }

            form.setAttribute('hidden', '');
            button.setAttribute('aria-expanded', 'false');
        });
    })();

    (function() {
        var button = document.querySelector('[data-add-item-toggle]');
        var form = document.getElementById('add-item-form');

        if (!button || !form) {
            return;
        }

        function openAddItemForm(focusTitle) {
            var titleInput = form.querySelector('input[name="segment_title"]');
            form.removeAttribute('hidden');
            button.setAttribute('aria-expanded', 'true');

            if (focusTitle && titleInput) {
                titleInput.focus();
            }
        }

        button.addEventListener('click', function() {
            if (form.hasAttribute('hidden')) {
                openAddItemForm(true);
                return;
            }

            form.setAttribute('hidden', '');
            button.setAttribute('aria-expanded', 'false');
        });

        window.addEventListener('toolactivated', function(event) {
            if (event.toolName !== 'prepareTravelItem' || !form.querySelector('form[toolname="prepareTravelItem"]')) {
                return;
            }

            openAddItemForm(false);
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });

        document.querySelectorAll('[data-lodging-prefill]').forEach(function(prefillButton) {
            prefillButton.addEventListener('click', function() {
                function nextDateValue(dateValue) {
                    var parts = dateValue.split('-').map(function(part) {
                        return parseInt(part, 10);
                    });
                    var date = new Date(parts[0], parts[1] - 1, parts[2] + 1);
                    var month = String(date.getMonth() + 1).padStart(2, '0');
                    var day = String(date.getDate()).padStart(2, '0');

                    return [date.getFullYear(), month, day].join('-');
                }

                function dateValueDays(dateValue) {
                    var parts = dateValue.split('-').map(function(part) {
                        return parseInt(part, 10);
                    });

                    return Math.floor(Date.UTC(parts[0], parts[1] - 1, parts[2]) / 86400000);
                }

                var titleInput = form.querySelector('input[name="segment_title"]');
                var typeInput = form.querySelector('[name="segment_type"]');
                var locationInput = form.querySelector('input[name="segment_location"]');
                var startInput = form.querySelector('input[name="segment_date"]');
                var endInput = form.querySelector('input[name="segment_end_date"]');
                var detailsInput = form.querySelector('textarea[name="segment_details"]');
                var checkerBox = prefillButton.closest('[data-lodging-checker-box]');
                var selectedNights = checkerBox
                    ? Array.prototype.slice.call(checkerBox.querySelectorAll('[data-lodging-night]:checked'))
                    : [];

                if (!selectedNights.length) {
                    return;
                }

                var selected = selectedNights.map(function(nightInput) {
                    var row = nightInput.closest('.lodging-checker-night');
                    var rowLocation = row ? row.querySelector('[data-lodging-night-location]') : null;
                    return {
                        date: nightInput.value || '',
                        location: rowLocation ? rowLocation.value.trim() : ''
                    };
                }).filter(function(night) {
                    return night.date;
                }).sort(function(a, b) {
                    return a.date.localeCompare(b.date);
                });

                if (!selected.length) {
                    return;
                }

                var hasGap = selected.some(function(night, index) {
                    return index > 0 && dateValueDays(night.date) - dateValueDays(selected[index - 1].date) !== 1;
                });

                if (hasGap) {
                    window.alert((window.travelAppTripData && window.travelAppTripData.continuousLodgingRange) || 'Select one continuous lodging date range.');
                    return;
                }

                var startDate = selected[0].date;
                var lastDate = selected[selected.length - 1].date;
                var endDate = nextDateValue(lastDate);
                var locations = selected.map(function(night) {
                    return night.location;
                }).filter(Boolean);
                var uniqueLocations = locations.filter(function(location, index) {
                    return locations.indexOf(location) === index;
                });

                form.removeAttribute('hidden');
                button.setAttribute('aria-expanded', 'true');

                if (typeInput) {
                    typeInput.value = 'lodging';
                }
                if (startInput) {
                    startInput.value = startDate;
                }
                if (endInput) {
                    endInput.value = endDate;
                }
                if (locationInput) {
                    locationInput.value = locations[0] || '';
                }
                if (detailsInput && uniqueLocations.length > 1) {
                    detailsInput.value = selected.map(function(night) {
                        return night.date + (night.location ? ': ' + night.location : '');
                    }).join('\n');
                } else if (detailsInput && detailsInput.value.indexOf(': ') !== -1) {
                    detailsInput.value = '';
                }
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
                if (titleInput) {
                    titleInput.focus();
                    titleInput.select();
                }
            });
        });

        var lodgingCheckerToggle = document.querySelector('[data-lodging-checker-toggle]');
        var lodgingCheckerBox = document.querySelector('[data-lodging-checker-box]');

        if (lodgingCheckerToggle && lodgingCheckerBox) {
            lodgingCheckerToggle.addEventListener('click', function() {
                var isHidden = lodgingCheckerBox.hasAttribute('hidden');

                if (isHidden) {
                    lodgingCheckerBox.removeAttribute('hidden');
                    lodgingCheckerToggle.setAttribute('aria-expanded', 'true');
                    return;
                }

                lodgingCheckerBox.setAttribute('hidden', '');
                lodgingCheckerToggle.setAttribute('aria-expanded', 'false');
            });
        }
    })();

    (function() {
        var control = document.querySelector('[data-share-control]');

        if (!control) {
            return;
        }

        var copyButtons = Array.prototype.slice.call(control.querySelectorAll('[data-share-copy]'));
        var removeButtons = Array.prototype.slice.call(control.querySelectorAll('[data-share-remove]'));
        var status = control.querySelector('[data-share-status]');
        var copyResetTimers = {};

        copyButtons.forEach(function(button) {
            button.setAttribute('data-share-default-text', button.textContent);
        });

        function setStatus(message) {
            if (status) {
                status.textContent = message || '';
            }
        }

        function setBusy(isBusy) {
            removeButtons.concat(copyButtons).forEach(function(button) {
                if (button) {
                    button.disabled = isBusy;
                }
            });
        }

        function getShareButtonKey(button) {
            return (button.getAttribute('data-share-mode') || 'fellow') + ':' + (button.getAttribute('data-share-kind') || 'timeline');
        }

        function setShareUrls(mode, timelineUrl, calendarUrl) {
            copyButtons.forEach(function(button) {
                if ((button.getAttribute('data-share-mode') || 'fellow') === mode) {
                    button.setAttribute('data-share-url', (button.getAttribute('data-share-kind') || 'timeline') === 'calendar' ? (calendarUrl || '') : (timelineUrl || ''));
                }
            });

            removeButtons.forEach(function(button) {
                if ((button.getAttribute('data-share-mode') || 'fellow') === mode) {
                    button.hidden = !timelineUrl;
                }
            });

            resetCopyButton(mode);
        }

        function resetCopyButton(mode) {
            copyButtons.forEach(function(button) {
                if ((button.getAttribute('data-share-mode') || 'fellow') === mode) {
                    button.textContent = button.getAttribute('data-share-default-text') || button.textContent;
                    button.classList.remove('copied');
                }
            });
        }

        function confirmCopied(button) {
            var mode = button.getAttribute('data-share-mode') || 'fellow';
            var timerKey = getShareButtonKey(button);

            copyButtons.forEach(function(button) {
                if ((button.getAttribute('data-share-mode') || 'fellow') === mode) {
                    button.textContent = button.getAttribute('data-share-default-text') || button.textContent;
                    button.classList.remove('copied');
                }
            });
            button.textContent = (window.travelAppTripData && window.travelAppTripData.copied) || 'Copied!';
            button.classList.add('copied');
            setStatus((button.getAttribute('data-share-kind') || 'timeline') === 'calendar' ? ((window.travelAppTripData && window.travelAppTripData.calendarCopied) || 'Calendar subscription link copied.') : ((window.travelAppTripData && window.travelAppTripData.shareCopied) || 'Share link copied.'));

            if (copyResetTimers[timerKey]) {
                window.clearTimeout(copyResetTimers[timerKey]);
            }

            copyResetTimers[timerKey] = window.setTimeout(function() {
                resetCopyButton(mode);
            }, 1800);
        }

        function requestShareAction(action, mode) {
            var body = new URLSearchParams();
            body.set('action', action);
            body.set('trip_id', control.getAttribute('data-trip-id') || '');
            body.set('nonce', control.getAttribute('data-nonce') || '');
            if (mode) {
                body.set('share_mode', mode);
            }

            setBusy(true);
            setStatus('');

            return fetch(control.getAttribute('data-ajax-url') || '', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: body.toString()
            }).then(function(response) {
                return response.json().then(function(data) {
                    if (!response.ok || !data || !data.success) {
                        throw new Error(data && data.data && data.data.message ? data.data.message : ((window.travelAppTripData && window.travelAppTripData.shareFailed) || 'The sharing change could not be saved.'));
                    }

                    return data.data || {};
                });
            }).then(function(data) {
                if (data.mode) {
                    setShareUrls(data.mode, data.url || '', data.calendar_url || '');
                }
                setStatus(data.message || '');
                return data;
            }).catch(function(error) {
                setStatus(error.message || ((window.travelAppTripData && window.travelAppTripData.shareFailed) || 'The sharing change could not be saved.'));
                throw error;
            }).finally(function() {
                setBusy(false);
            });
        }

        removeButtons.forEach(function(removeButton) {
            removeButton.addEventListener('click', function() {
                requestShareAction('travel_app_remove_share_link', removeButton.getAttribute('data-share-mode') || 'fellow');
            });
        });

        function copyShareUrl(url, button) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                return navigator.clipboard.writeText(url).then(function() {
                    confirmCopied(button);
                }).catch(function() {
                    window.prompt((window.travelAppTripData && window.travelAppTripData.copyPrompt) || 'Copy this link:', url);
                    confirmCopied(button);
                });
            }

            window.prompt((window.travelAppTripData && window.travelAppTripData.copyPrompt) || 'Copy this link:', url);
            confirmCopied(button);
            return Promise.resolve();
        }

        copyButtons.forEach(function(copyButton) {
            copyButton.addEventListener('click', function() {
                var mode = copyButton.getAttribute('data-share-mode') || 'fellow';
                var kind = copyButton.getAttribute('data-share-kind') || 'timeline';
                var url = copyButton.getAttribute('data-share-url') || '';

                if (url) {
                    copyShareUrl(url, copyButton);
                    return;
                }

                copyButton.textContent = (window.travelAppTripData && window.travelAppTripData.generating) || 'Generating...';
                requestShareAction('travel_app_generate_share_link', mode).then(function(data) {
                    var generatedUrl = data ? (kind === 'calendar' ? data.calendar_url : data.url) : '';
                    if (generatedUrl) {
                        copyShareUrl(generatedUrl, copyButton);
                        return;
                    }

                    resetCopyButton(mode);
                }).catch(function() {
                    resetCopyButton(mode);
                });
            });
        });
    })();
    
