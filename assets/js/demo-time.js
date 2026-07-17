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

    function updateControl(control) {
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
        renderPreviewSlot(currentSlot, current);
        renderPreviewSlot(nextSlot, next);

        target.querySelectorAll('[data-preview-demo-time]').forEach(function(node) {
            node.textContent = value ? value.replace('T', ' ') : '';
        });
    }

    function renderPreviewSlot(slot, source) {
        if (!slot) {
            return;
        }

        var title = slot.querySelector('[data-preview-title]');
        var meta = slot.querySelector('[data-preview-meta]');

        if (!source) {
            if (title) {
                title.textContent = slot.getAttribute('data-empty-title') || 'No item';
            }
            if (meta) {
                meta.textContent = '';
            }
            return;
        }

        if (title) {
            title.textContent = source.getAttribute('data-title') || 'Untitled item';
        }
        if (meta) {
            meta.textContent = [
                (source.getAttribute('data-date') || ''),
                (source.getAttribute('data-time') || ''),
                (source.getAttribute('data-location') || '')
            ].filter(Boolean).join(' ');
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

        if (currentItem && nextItem) {
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

        if (top === null) {
            marker.style.display = 'none';
            return;
        }

        marker.style.top = Math.max(0, top) + 'px';
        marker.style.display = 'block';
        markerLabel.textContent = value.slice(11, 16);
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
                updateControl(control);
            });
        });

        var now = control.querySelector('[data-demo-now]');
        if (now) {
            now.addEventListener('click', function() {
                input.value = formatInputDate(new Date());
                updateControl(control);
            });
        }

        input.addEventListener('change', function() {
            updateControl(control);
        });

        updateControl(control);
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[data-demo-controls]').forEach(initControl);
    });
}());
