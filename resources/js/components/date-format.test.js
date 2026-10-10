import assert from 'node:assert/strict';
import test from 'node:test';
import { formatDateValue } from './date-format.js';

test('date-only values keep their calendar day and honor PHP format tokens', () => {
    assert.equal(formatDateValue('2024-02-29', 'date', 'Y-m-d'), '2024-02-29');
    assert.equal(
        formatDateValue('2024-02-29T00:00:00.000000Z', 'date', 'Y-m-d'),
        '2024-02-29',
    );
    assert.equal(
        formatDateValue('2024-02-29', 'date', 'M j, Y', 'en-US'),
        'Feb 29, 2024',
    );
});

test('datetime values honor configured formats in the browser timezone', () => {
    assert.equal(
        formatDateValue(
            '2024-02-29T13:05:06',
            'datetime',
            'Y-m-d H:i',
            'en-US',
        ),
        '2024-02-29 13:05',
    );
});

test('null, empty, and invalid date values render as empty text', () => {
    assert.equal(formatDateValue(null, 'date'), '');
    assert.equal(formatDateValue('', 'datetime'), '');
    assert.equal(formatDateValue('not-a-date', 'date', 'Y-m-d'), '');
    assert.equal(formatDateValue('2024-02-31', 'date', 'Y-m-d'), '');
});

test('unconfigured dates retain safe locale defaults', () => {
    assert.equal(
        formatDateValue('2024-02-29', 'date', undefined, 'en-CA'),
        '2024-02-29',
    );
    assert.equal(
        formatDateValue('2024-02-29T13:05:06', 'datetime', undefined, 'en-US'),
        new Date('2024-02-29T13:05:06').toLocaleString('en-US'),
    );
});
