(function() {
    function formatInputDate(date) {
        var pad = function(value) {
            return String(value).padStart(2, '0');
        };
        return [
            date.getFullYear(),
            pad(date.getMonth() + 1),
            pad(date.getDate())
        ].join('-') + 'T' + [pad(date.getHours()), pad(date.getMinutes())].join(':');
    }

    function parseDateTime(value) {
        return value ? new Date(value).getTime() : 0;
    }

    function dateOffsetDays(date, baseDate) {
        if (!date || !baseDate) {
            return null;
        }

        var dateTime = parseDateTime(date + 'T12:00');
        var baseTime = parseDateTime(baseDate + 'T12:00');
        if (!dateTime || !baseTime) {
            return null;
        }

        return Math.round((dateTime - baseTime) / 86400000);
    }

    function formatRelativeDateTime(date, timeLabel, fullLabel, currentDate) {
        var offsetDays = dateOffsetDays(date, currentDate);

        if (offsetDays === 0) {
            return timeLabel || fullLabel || '';
        }
        if (offsetDays === 1) {
            return ['tomorrow', timeLabel].filter(Boolean).join(' ');
        }
        if (offsetDays === -1) {
            return ['yesterday', timeLabel].filter(Boolean).join(' ');
        }

        return fullLabel || [date, timeLabel].filter(Boolean).join(' ');
    }

    function getSourceEndTime(source) {
        var endDate = source.getAttribute('data-end-date') || '';
        var endTime = source.getAttribute('data-end-time') || '';

        return endDate && endTime ? parseDateTime(endDate + 'T' + endTime) : 0;
    }

    function formatDuration(milliseconds) {
        var past = milliseconds < 0;
        var minutesTotal = Math.max(0, Math.round(Math.abs(milliseconds) / 60000));
        var days = Math.floor(minutesTotal / 1440);
        var hours = Math.floor((minutesTotal % 1440) / 60);
        var minutes = minutesTotal % 60;
        var parts = [];

        if (days) {
            parts.push(days + 'd');
        }
        if (hours || days) {
            parts.push(hours + 'h');
        }
        if (!days && minutes) {
            parts.push(minutes + 'm');
        }
        if (!parts.length) {
            return 'Now';
        }

        return past ? parts.join(' ') + ' ago' : 'in ' + parts.join(' ');
    }

    function normalizeTime(value) {
        var match = String(value || '').trim().match(/^(\d{1,2}):(\d{2})$/);
        if (!match) {
            return '12:00';
        }

        var hours = Math.max(0, Math.min(23, parseInt(match[1], 10) || 0));
        var minutes = Math.max(0, Math.min(59, parseInt(match[2], 10) || 0));

        return String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0');
    }

    function syncVisibleInputs(control) {
        var input = control.querySelector('[data-demo-input]');
        var dateInput = control.querySelector('[data-demo-date]');
        var timeInput = control.querySelector('[data-demo-time]');
        var value = input && input.value ? input.value : '';

        if (dateInput) {
            dateInput.value = value ? value.slice(0, 10) : '';
        }
        if (timeInput) {
            timeInput.value = value ? value.slice(11, 16) : '12:00';
        }
    }

    function syncHiddenInput(control) {
        var input = control.querySelector('[data-demo-input]');
        var dateInput = control.querySelector('[data-demo-date]');
        var timeInput = control.querySelector('[data-demo-time]');
        if (!input || !dateInput || !dateInput.value) {
            return;
        }

        var time = normalizeTime(timeInput && timeInput.value ? timeInput.value : '12:00');
        if (timeInput) {
            timeInput.value = time;
        }
        input.value = dateInput.value + 'T' + time;
    }

    function updateControl(control) {
        syncHiddenInput(control);
        var id = control.getAttribute('data-demo-controls');
        var input = control.querySelector('[data-demo-input]');
        var value = input && input.value ? input.value : '';
        var dateValue = value ? value.slice(0, 10) : '';
        var currentTime = parseDateTime(value);

        document.querySelectorAll('[data-demo-target="' + id + '"]').forEach(function(target) {
            updateTimelineTarget(target, value, dateValue, currentTime);
            updatePreviewTarget(target, value, currentTime);
        });
    }

    function updateTimelineTarget(target, value, dateValue, currentTime) {
        if (!target.classList.contains('timeline')) {
            return;
        }

        var currentItem = null;
        var nextItem = null;

        target.querySelectorAll('.timeline-day').forEach(function(day) {
            var date = day.getAttribute('data-date') || '';
            day.classList.toggle('past', dateValue && date && date !== 'unscheduled' && date < dateValue);
            day.classList.toggle('current', dateValue && date === dateValue);
        });

        target.querySelectorAll('.timeline-item').forEach(function(item) {
            var itemTime = parseDateTime(item.getAttribute('data-datetime') || '');
            item.classList.remove('current', 'past');
            if (!currentTime || !itemTime) {
                return;
            }
            if (itemTime <= currentTime) {
                currentItem = item;
                item.classList.add('past');
            } else if (!nextItem) {
                nextItem = item;
            }
        });

        if (currentItem) {
            currentItem.classList.add('current');
        } else if (nextItem) {
            nextItem.classList.add('current');
        }

        positionMarker(target, value, currentTime, currentItem, nextItem);
    }

    function updatePreviewTarget(target, value, currentTime) {
        if (!target.hasAttribute('data-demo-preview')) {
            return;
        }

        var current = null;
        var next = null;
        var items = Array.prototype.slice.call(target.querySelectorAll('[data-preview-item]'));

        items.forEach(function(item) {
            var itemTime = parseDateTime(item.getAttribute('data-datetime') || '');
            item.hidden = true;
            if (!currentTime || !itemTime) {
                return;
            }
            if (itemTime <= currentTime) {
                current = item;
            } else if (!next) {
                next = item;
            }
        });

        var currentSlot = target.querySelector('[data-preview-slot="current"]');
        var nextSlot = target.querySelector('[data-preview-slot="next"]');
        renderPreviewSlot(currentSlot, current, currentTime, value ? value.slice(0, 10) : '');
        renderPreviewSlot(nextSlot, next, currentTime, value ? value.slice(0, 10) : '');

        target.querySelectorAll('[data-preview-demo-time]').forEach(function(node) {
            node.textContent = value ? value.replace('T', ' ') : '';
        });
    }

    function renderPreviewSlot(slot, source, currentTime, currentDate) {
        if (!slot) {
            return;
        }

        var title = slot.querySelector('[data-preview-title]');
        var meta = slot.querySelector('[data-preview-meta]');
        var previewLocation = slot.querySelector('[data-preview-location]');
        var end = slot.querySelector('[data-preview-end]');
        var countdown = slot.querySelector('[data-preview-countdown]');
        var isCurrentSlot = slot.getAttribute('data-preview-slot') === 'current';

        if (!source) {
            slot.hidden = true;
            slot.removeAttribute('href');
            if (title) {
                title.textContent = slot.getAttribute('data-empty-title') || 'No item';
            }
            if (meta) {
                meta.textContent = '';
            }
            if (previewLocation) {
                previewLocation.textContent = '';
            }
            if (end) {
                end.textContent = '';
            }
            if (countdown) {
                countdown.textContent = '';
            }
            return;
        }

        slot.hidden = false;
        slot.setAttribute('href', source.getAttribute('data-url') || '#');
        if (title) {
            title.textContent = source.getAttribute('data-title') || 'Untitled item';
        }
        var location = source.getAttribute('data-location') || '';
        var endLocation = source.getAttribute('data-end-location') || '';
        var endDate = source.getAttribute('data-end-date') || '';
        var endTime = source.getAttribute('data-end-time') || '';
        var endTimeValue = getSourceEndTime(source);
        var isLodging = source.getAttribute('data-type') === 'lodging';
        var isTravelInProgress = isCurrentSlot && currentTime && endTimeValue > currentTime && endLocation && endLocation !== location;
        var isLodgingInProgress = isCurrentSlot && currentTime && endTimeValue > currentTime && isLodging;

        if (meta) {
            if (isTravelInProgress) {
                meta.textContent = [
                    '→',
                    formatRelativeDateTime(endDate, endTime, source.getAttribute('data-end-label') || '', currentDate),
                    endLocation
                ].filter(Boolean).join(' ');
            } else if (isLodgingInProgress) {
                meta.textContent = '';
            } else {
                var locationLabel = location && endLocation && location !== endLocation
                    ? location + ' → ' + endLocation
                    : (location || endLocation);
                var date = source.getAttribute('data-date') || '';
                var timeLabel = source.getAttribute('data-time-label') || '';
                var dateTimeLabel = formatRelativeDateTime(
                    date,
                    timeLabel,
                    source.getAttribute('data-date-time-label') || '',
                    currentDate
                );

                meta.textContent = [
                    dateTimeLabel,
                    isLodging ? '' : locationLabel
                ].filter(Boolean).join(' ');
            }
        }
        if (previewLocation) {
            previewLocation.textContent = isLodging ? (location || endLocation) : '';
        }
        if (end) {
            var endLabel = formatRelativeDateTime(
                endDate,
                endTime,
                source.getAttribute('data-end-label') || '',
                currentDate
            );
            end.textContent = endDate && !isTravelInProgress ? ['→', endLabel].filter(Boolean).join(' ') : '';
        }
        if (countdown) {
            var countdownTarget = isTravelInProgress || isLodgingInProgress ? endTimeValue : parseDateTime(source.getAttribute('data-datetime') || '');
            countdown.textContent = currentTime && countdownTarget
                ? formatDuration(countdownTarget - currentTime)
                : '';
        }
    }

    function positionMarker(target, value, currentTime, currentItem, nextItem) {
        var marker = target.querySelector('.time-marker');
        var markerLabel = target.querySelector('.time-marker-label');

        if (!marker || !markerLabel || !currentTime) {
            return;
        }

        var targetRect = target.getBoundingClientRect();
        var top = null;
        var dateValue = value ? value.slice(0, 10) : '';
        var currentDay = getTimelineDay(target, dateValue);
        var currentDayIsEmpty = currentDay && !currentDay.querySelector('.timeline-item');

        if (currentDayIsEmpty) {
            top = getTimeMarkerDayTop(currentDay, targetRect, currentTime);
        } else if (currentItem && nextItem) {
            var currentRect = currentItem.getBoundingClientRect();
            var nextRect = nextItem.getBoundingClientRect();
            var currentValue = parseDateTime(currentItem.getAttribute('data-datetime') || '');
            var nextValue = parseDateTime(nextItem.getAttribute('data-datetime') || '');
            var ratio = nextValue > currentValue ? (currentTime - currentValue) / (nextValue - currentValue) : 1;
            ratio = Math.max(0, Math.min(1, ratio));
            top = (currentRect.bottom - targetRect.top) + (nextRect.top - currentRect.bottom) * ratio;
        } else if (nextItem) {
            top = nextItem.getBoundingClientRect().top - targetRect.top - 8;
        } else if (currentItem) {
            top = currentItem.getBoundingClientRect().bottom - targetRect.top + 8;
        }

        if (top === null && currentDay) {
            top = getTimeMarkerDayTop(currentDay, targetRect, currentTime);
        }

        if (top === null) {
            marker.style.display = 'none';
            return;
        }

        marker.style.top = Math.max(0, top) + 'px';
        marker.style.display = 'block';
        markerLabel.textContent = value.slice(11, 16);
    }

    function getTimelineDay(target, dateValue) {
        if (!dateValue) {
            return null;
        }

        var days = Array.prototype.slice.call(target.querySelectorAll('.timeline-day'));
        return days.find(function(day) {
            return day.getAttribute('data-date') === dateValue;
        }) || null;
    }

    function getTimeMarkerDayTop(day, targetRect, currentTime) {
        var dayRect = day.getBoundingClientRect();
        var heading = day.querySelector('.day-heading');
        var headingRect = heading ? heading.getBoundingClientRect() : null;
        var start = (headingRect ? headingRect.bottom : dayRect.top) - targetRect.top + 10;
        var end = dayRect.bottom - targetRect.top - 10;
        var date = new Date(currentTime);
        var minutes = date.getHours() * 60 + date.getMinutes();
        var ratio = minutes / 1439;

        if (end <= start) {
            return dayRect.top - targetRect.top;
        }

        return start + (end - start) * ratio;
    }

    function initControl(control) {
        var input = control.querySelector('[data-demo-input]');
        if (!input) {
            return;
        }

        control.querySelectorAll('[data-demo-shift]').forEach(function(button) {
            button.addEventListener('click', function() {
                var minutes = parseInt(button.getAttribute('data-demo-shift'), 10) || 0;
                var current = input.value ? new Date(input.value) : new Date();
                current.setMinutes(current.getMinutes() + minutes);
                input.value = formatInputDate(current);
                syncVisibleInputs(control);
                updateControl(control);
            });
        });

        var now = control.querySelector('[data-demo-now]');
        if (now) {
            now.addEventListener('click', function() {
                input.value = formatInputDate(new Date());
                syncVisibleInputs(control);
                updateControl(control);
            });
        }

        control.querySelectorAll('[data-demo-date], [data-demo-time]').forEach(function(field) {
            field.addEventListener('change', function() {
                updateControl(control);
            });
            field.addEventListener('blur', function() {
                updateControl(control);
            });
        });

        syncVisibleInputs(control);
        updateControl(control);
    }

    function currentDateTimeValue() {
        return formatInputDate(new Date());
    }

    function updateStandalonePreviews() {
        var controlledIds = {};
        document.querySelectorAll('[data-demo-controls]').forEach(function(control) {
            controlledIds[control.getAttribute('data-demo-controls')] = true;
        });

        var value = currentDateTimeValue();
        var currentTime = parseDateTime(value);
        document.querySelectorAll('[data-demo-preview]').forEach(function(target) {
            var id = target.getAttribute('data-demo-target') || '';
            if (!controlledIds[id]) {
                updatePreviewTarget(target, value, currentTime);
            }
        });
    }

    function updateStandaloneTimelines() {
        var controlledIds = {};
        document.querySelectorAll('[data-demo-controls]').forEach(function(control) {
            controlledIds[control.getAttribute('data-demo-controls')] = true;
        });

        var value = currentDateTimeValue();
        var currentTime = parseDateTime(value);
        var dateValue = value ? value.slice(0, 10) : '';

        document.querySelectorAll('.timeline').forEach(function(target) {
            var id = target.getAttribute('data-demo-target') || '';
            if (!controlledIds[id]) {
                updateTimelineTarget(target, value, dateValue, currentTime);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[data-demo-controls]').forEach(initControl);
        updateStandaloneTimelines();
        updateStandalonePreviews();
        window.setInterval(function() {
            document.querySelectorAll('[data-demo-controls]').forEach(updateControl);
            updateStandaloneTimelines();
            updateStandalonePreviews();
        }, 60000);
    });
}());
