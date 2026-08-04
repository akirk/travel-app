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
        var date = source.getAttribute('data-date') || '';
        var endDate = source.getAttribute('data-end-date') || date;
        var endTime = source.getAttribute('data-end-time') || '';

        return date && endDate && endTime ? parseDateTime(endDate + 'T' + endTime) : 0;
    }

    function hasSameDayEndTimeInMeta(source, dateTimeLabel) {
        var date = source.getAttribute('data-date') || '';
        var endDate = source.getAttribute('data-end-date') || '';
        var endTime = source.getAttribute('data-end-time') || '';

        return !!(date && endDate === date && endTime && dateTimeLabel.indexOf(endTime) !== -1);
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
            var itemRange = getItemTimeRange(item);
            item.classList.remove('current', 'past');
            if (!currentTime || !itemRange) {
                return;
            }
            if (itemRange.start <= currentTime) {
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

        updateTimelineJumpControls(target, positionMarker(target, value, currentTime));
    }

    function updatePreviewTarget(target, value, currentTime) {
        if (!target.hasAttribute('data-demo-preview')) {
            return;
        }

        var current = null;
        var next = null;
        var items = Array.prototype.slice.call(target.querySelectorAll('[data-preview-item]'));
        var currentDate = value ? value.slice(0, 10) : '';

        items.forEach(function(item, index) {
            var itemTime = parseDateTime(item.getAttribute('data-datetime') || '');
            item.hidden = true;
            if (!currentTime || !itemTime) {
                return;
            }
            if (itemTime <= currentTime) {
                current = item;
            } else if (!next) {
                next = item;
                if (isTodayCheckout(item, currentDate)) {
                    current = item;
                    next = findNextPreviewItem(items, index + 1, currentTime);
                }
            }
        });

        var currentSlot = target.querySelector('[data-preview-slot="current"]');
        var nextSlot = target.querySelector('[data-preview-slot="next"]');
        renderPreviewSlot(currentSlot, current, currentTime, currentDate);
        renderPreviewSlot(nextSlot, next, currentTime, currentDate);

        target.querySelectorAll('[data-preview-demo-time]').forEach(function(node) {
            node.textContent = value ? value.replace('T', ' ') : '';
        });
    }

    function isTodayCheckout(item, currentDate) {
        return item
            && item.getAttribute('data-timeline-kind') === 'checkout'
            && currentDate
            && item.getAttribute('data-date') === currentDate;
    }

    function findNextPreviewItem(items, startIndex, currentTime) {
        for (var index = startIndex; index < items.length; index++) {
            var itemTime = parseDateTime(items[index].getAttribute('data-datetime') || '');
            if (currentTime && itemTime && itemTime > currentTime) {
                return items[index];
            }
        }

        return null;
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
        var label = slot.querySelector('[data-preview-label]');
        var isCurrentSlot = slot.getAttribute('data-preview-slot') === 'current';

        if (!source) {
            slot.hidden = true;
            slot.removeAttribute('href');
            if (label) {
                label.textContent = slot.getAttribute('data-slot-label') || '';
            }
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
        var timelineKind = source.getAttribute('data-timeline-kind') || 'start';
        var isCheckout = timelineKind === 'checkout';
        var isLodging = source.getAttribute('data-type') === 'lodging' && !isCheckout;
        var hasEnded = isCurrentSlot && currentTime && endTimeValue && endTimeValue <= currentTime;
        var isTravelInProgress = isCurrentSlot && currentTime && endTimeValue > currentTime && endLocation && endLocation !== location;
        var isLodgingInProgress = isCurrentSlot && currentTime && endTimeValue > currentTime && isLodging;

        if (label) {
            label.textContent = hasEnded
                ? (slot.getAttribute('data-ended-label') || slot.getAttribute('data-slot-label') || '')
                : (slot.getAttribute('data-slot-label') || '');
        }
        var dateTimeLabel = '';
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
                dateTimeLabel = formatRelativeDateTime(
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
            end.textContent = endDate && !isTravelInProgress && !hasSameDayEndTimeInMeta(source, dateTimeLabel)
                ? ['→', endLabel].filter(Boolean).join(' ')
                : '';
        }
        if (countdown) {
            var countdownTarget = isTravelInProgress || isLodgingInProgress ? endTimeValue : parseDateTime(source.getAttribute('data-datetime') || '');
            countdown.textContent = currentTime && countdownTarget
                ? formatDuration(countdownTarget - currentTime)
                : '';
        }
    }

    function positionMarker(target, value, currentTime) {
        var marker = target.querySelector('.time-marker');
        var markerLabel = target.querySelector('.time-marker-label');

        if (!marker || !markerLabel) {
            return false;
        }

        if (!currentTime) {
            marker.style.display = 'none';
            markerLabel.textContent = '';
            return false;
        }

        var targetRect = target.getBoundingClientRect();
        var dateValue = value ? value.slice(0, 10) : '';
        var currentDay = getTimelineDay(target, dateValue);
        var top = currentDay ? getTimeMarkerDayTop(currentDay, targetRect, currentTime) : null;

        if (top === null) {
            marker.style.display = 'none';
            markerLabel.textContent = '';
            return false;
        }

        marker.style.top = Math.max(0, top) + 'px';
        marker.style.display = 'block';
        markerLabel.textContent = value.slice(11, 16);
        return true;
    }

    function hasExplicitItemTime(item) {
        return !!(item && String(item.getAttribute('data-time') || '').trim().match(/^\d{1,2}:\d{2}$/));
    }

    function getItemTimeRange(item) {
        if (!item) {
            return null;
        }

        if (hasExplicitItemTime(item)) {
            var time = parseDateTime(item.getAttribute('data-datetime') || '');
            return time ? { start: time, end: time } : null;
        }

        var date = getItemDate(item);
        var start = date ? parseDateTime(date + 'T00:00') : 0;
        var end = date ? parseDateTime(date + 'T23:59:59') : 0;

        return start && end ? { start: start, end: end } : null;
    }

    function getItemDate(item) {
        if (!item) {
            return '';
        }

        return item.getAttribute('data-date') || (item.getAttribute('data-datetime') || '').slice(0, 10);
    }

    function updateTimelineJumpControls(target, enabled) {
        if (!target.id) {
            return;
        }

        document.querySelectorAll('[data-timeline-now]').forEach(function(button) {
            if (button.getAttribute('aria-controls') !== target.id) {
                return;
            }

            button.disabled = !enabled;
        });
    }

    function getScrollOffset() {
        var viewportOffset = Math.round(window.innerHeight * 0.18);
        return Math.max(72, Math.min(160, viewportOffset));
    }

    function scrollToTimelineNow(target) {
        var marker = target.querySelector('.time-marker');
        var currentItem = target.querySelector('.timeline-item.current');

        if (marker && marker.style.display !== 'none') {
            window.scrollTo({
                top: Math.max(0, marker.getBoundingClientRect().top + window.pageYOffset - getScrollOffset()),
                behavior: 'smooth'
            });
            return;
        }

        if (currentItem) {
            currentItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    function initTimelineJumpControls() {
        document.querySelectorAll('[data-timeline-now]').forEach(function(button) {
            button.addEventListener('click', function() {
                var targetId = button.getAttribute('aria-controls') || '';
                var target = targetId ? document.getElementById(targetId) : null;

                if (!target || button.disabled) {
                    return;
                }

                scrollToTimelineNow(target);
            });
        });
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
        var date = day.getAttribute('data-date') || '';
        var startValue = date ? parseDateTime(date + 'T00:00') : 0;
        var endValue = date ? parseDateTime(date + 'T23:59:59') : 0;
        var anchors = [];

        if (!startValue || !endValue) {
            return null;
        }

        anchors.push({
            value: startValue,
            top: getTimelineDayAnchorTop(day, targetRect)
        });

        Array.prototype.slice.call(day.querySelectorAll('.timeline-item')).forEach(function(item) {
            var itemDate = getItemDate(item);
            var itemTime = String(item.getAttribute('data-time') || '').trim();
            var itemRect;
            var itemValue;
            var endDate;
            var endTime;
            var endValueForItem;

            if (itemDate !== date || !itemTime.match(/^\d{1,2}:\d{2}$/)) {
                return;
            }

            itemRect = item.getBoundingClientRect();
            itemValue = parseDateTime(date + 'T' + itemTime);
            if (itemValue) {
                anchors.push({
                    value: itemValue,
                    top: itemRect.top - targetRect.top
                });
            }

            endDate = item.getAttribute('data-end-date') || '';
            endTime = String(item.getAttribute('data-end-time') || '').trim();
            if (endTime.match(/^\d{1,2}:\d{2}$/) && (!endDate || endDate === date)) {
                endValueForItem = parseDateTime(date + 'T' + endTime);
                if (endValueForItem && endValueForItem > itemValue) {
                    anchors.push({
                        value: endValueForItem,
                        top: itemRect.bottom - targetRect.top
                    });
                }
            }
        });

        anchors.push({
            value: endValue,
            top: getTimelineDayEndTop(day, targetRect)
        });

        anchors.sort(function(a, b) {
            return a.value - b.value || a.top - b.top;
        });

        return interpolateTimeMarkerTop(anchors, currentTime);
    }

    function interpolateTimeMarkerTop(anchors, currentTime) {
        var previous = anchors[0] || null;
        var next = null;
        var ratio;

        if (!previous) {
            return null;
        }

        for (var index = 1; index < anchors.length; index++) {
            next = anchors[index];
            if (currentTime <= next.value) {
                break;
            }
            previous = next;
            next = null;
        }

        if (!next) {
            return previous.top;
        }

        if (next.value <= previous.value || next.top <= previous.top) {
            return previous.top;
        }

        ratio = (currentTime - previous.value) / (next.value - previous.value);
        ratio = Math.max(0, Math.min(1, ratio));

        return previous.top + (next.top - previous.top) * ratio;
    }

    function getTimelineDayAnchorTop(day, targetRect) {
        var heading = day.querySelector('.day-heading');
        var rect = heading ? heading.getBoundingClientRect() : day.getBoundingClientRect();

        return rect.top - targetRect.top + (heading ? 14 : 0);
    }

    function getTimelineDayEndTop(day, targetRect) {
        var nextDay = getNextTimelineDay(day);

        return nextDay ? nextDay.getBoundingClientRect().top - targetRect.top : day.getBoundingClientRect().bottom - targetRect.top;
    }

    function getNextTimelineDay(day) {
        var next = day.nextElementSibling;

        while (next && !next.classList.contains('timeline-day')) {
            next = next.nextElementSibling;
        }

        return next || null;
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

    function currentDateTimeValue(target) {
        var seeded = getSeededCurrentDateTime(target);

        return seeded || formatInputDate(new Date());
    }

    function getSeededCurrentDateTime(target) {
        if (!target) {
            return '';
        }

        var value = target.getAttribute('data-current-time-value') || '';
        var captured = parseInt(target.getAttribute('data-current-time-captured') || '', 10);
        var base = value ? new Date(value) : null;

        if (!value || !captured || !base || isNaN(base.getTime())) {
            return '';
        }

        return formatInputDate(new Date(base.getTime() + Math.max(0, Date.now() - captured * 1000)));
    }

    function updateStandalonePreviews() {
        var controlledIds = {};
        document.querySelectorAll('[data-demo-controls]').forEach(function(control) {
            controlledIds[control.getAttribute('data-demo-controls')] = true;
        });

        document.querySelectorAll('[data-demo-preview]').forEach(function(target) {
            var id = target.getAttribute('data-demo-target') || '';
            if (!controlledIds[id]) {
                var value = currentDateTimeValue(target);
                var currentTime = parseDateTime(value);
                updatePreviewTarget(target, value, currentTime);
            }
        });
    }

    function updateStandaloneTimelines() {
        var controlledIds = {};
        document.querySelectorAll('[data-demo-controls]').forEach(function(control) {
            controlledIds[control.getAttribute('data-demo-controls')] = true;
        });

        document.querySelectorAll('.timeline').forEach(function(target) {
            var id = target.getAttribute('data-demo-target') || '';
            if (!controlledIds[id] && target.getAttribute('data-current-time') === '1') {
                var value = currentDateTimeValue(target);
                var currentTime = parseDateTime(value);
                var dateValue = value ? value.slice(0, 10) : '';
                updateTimelineTarget(target, value, dateValue, currentTime);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        initTimelineJumpControls();
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
