const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');

function clock(now) {
    const RealDate = Date;
    class FixedDate extends RealDate {
        constructor(...args) { super(...(args.length ? args : [now])); }
        static now() { return new RealDate(now).getTime(); }
    }
    const context = { Date: FixedDate, Intl, document: { addEventListener() {} } };
    const source = fs.readFileSync('assets/js/timeline-time.js', 'utf8')
        .replace(/\}\(\)\);\s*$/, 'globalThis.clock = { tripRelativeLabel, currentDateTimeValue, updateTripClock, updateTimelineTarget }; }());');
    vm.runInNewContext(source, context);
    return context;
}

const labels = { tomorrow: 'Starts tomorrow', startsMany: 'Starts in %d days', yesterday: 'Ended yesterday', endedMany: 'Ended %d days ago', active: 'Active now' };

test('cached trip labels follow the current date through start and end boundaries', () => {
    const { tripRelativeLabel: label } = clock('2026-11-20T12:00:00Z').clock;
    const start = '2026-11-25', end = '2026-11-28';
    for (const [today, expected] of [
        ['2026-11-20', 'Starts in 5 days'], ['2026-11-24', 'Starts tomorrow'],
        ['2026-11-25', 'Active now'], ['2026-11-28', 'Active now'],
        ['2026-11-29', 'Ended yesterday'], ['2026-11-30', 'Ended 2 days ago'],
        ['2026-12-29', '']
    ]) { assert.equal(label(start, end, today, labels), expected); }
    assert.equal(label('', '', '2026-11-20', labels), '');
});

test('live site clock ignores cached seed and handles timezone day boundaries and DST', () => {
    for (const [now, zone, expected] of [
        ['2026-11-20T23:30:00Z', 'Europe/Berlin', '2026-11-21T00:30'],
        ['2026-07-20T23:30:00Z', 'Europe/Berlin', '2026-07-21T01:30'],
        ['2026-11-20T23:30:00Z', '-05:00', '2026-11-20T18:30'],
        ['2026-11-20T23:30:00Z', '+05:30', '2026-11-21T05:00']
    ]) {
        const page = { getAttribute: name => name === 'data-timezone' ? zone : '2020-01-01T00:00', closest() { return this; } };
        assert.equal(clock(now).clock.currentDateTimeValue(page), expected);
    }
});

test('Now/Next panel becomes visible for a trip cached before it started, then hides after it ends', () => {
    const context = clock('2026-11-25T12:00:00Z');
    const panel = { hidden: true };
    const label = { getAttribute: () => JSON.stringify(labels), hidden: true };
    const attrs = { 'data-timezone': 'UTC', 'data-trip-start': '2026-11-25', 'data-trip-end': '2026-11-28' };
    const page = { closest() { return this; }, getAttribute: name => attrs[name], querySelectorAll: selector => selector === '[data-trip-relative]' ? [label] : [panel] };
    context.document.querySelectorAll = () => [page];
    context.clock.updateTripClock();
    assert.equal(panel.hidden, false);
    assert.equal(label.textContent, 'Active now');
    context.clock.updateTripClock('2026-11-29T00:00');
    assert.equal(panel.hidden, true);
    assert.equal(label.textContent, 'Ended yesterday');
});

function classes(initial = []) {
    const values = new Set(initial);
    return { contains: name => values.has(name), add: name => values.add(name),
        remove: (...names) => names.forEach(name => values.delete(name)),
        toggle: (name, active) => active ? values.add(name) : values.delete(name) };
}

test('day, navigation and item highlighting advances from cached classes to the current date', () => {
    const context = clock('2026-11-26T12:00:00Z');
    const day = { classList: classes(['current']), getAttribute: () => '2026-11-25' };
    const link = { classList: classes(['is-current']), getAttribute: () => '2026-11-25', removeAttribute(name) { this.removed = name; } };
    const state = { classList: classes() };
    const attrs = { 'data-date': '2026-11-25', 'data-time': '10:00', 'data-datetime': '2026-11-25T10:00', 'data-end-date': '2026-11-25', 'data-end-time': '11:00' };
    const item = { classList: classes(['current']), getAttribute: name => attrs[name] || '', querySelector: () => state };
    const target = {
        classList: classes(['timeline']), hasAttribute: () => true, getAttribute: () => '',
        closest: () => ({ querySelectorAll: () => [link] }),
        querySelector: () => null,
        querySelectorAll: selector => selector === '.timeline-day' ? [day] : [item]
    };
    context.clock.updateTimelineTarget(target, '2026-11-26T12:00', '2026-11-26', new Date('2026-11-26T12:00').getTime());
    assert.equal(day.classList.contains('current'), false);
    assert.equal(day.classList.contains('past'), true);
    assert.equal(link.classList.contains('is-current'), false);
    assert.equal(link.removed, 'aria-current');
    assert.equal(item.classList.contains('current'), false);
    assert.equal(item.classList.contains('past'), true);
    assert.equal(state.textContent, 'Passed');
});
