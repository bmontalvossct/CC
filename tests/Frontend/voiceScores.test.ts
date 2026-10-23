import assert from 'node:assert/strict';
import { test } from 'node:test';
import { matchStudents, parseScoreCommand, spokenInteger } from '../../resources/js/lib/voiceScores.ts';

const roster = [
    { id: 1, first_name: 'Ana', last_name: 'Dela Cruz', full_name: 'Dela Cruz, Ana' },
    { id: 2, first_name: 'Luis', last_name: 'Dela Cruz', full_name: 'Dela Cruz, Luis' },
    { id: 3, first_name: 'José', last_name: 'Reyes', full_name: 'Reyes, José' },
];

test('matches full names in either order, compound surnames and accents', () => {
    assert.deepEqual(
        matchStudents('Dela Cruz Ana', roster).map((student) => student.id),
        [1],
    );
    assert.deepEqual(
        matchStudents('Ana Dela Cruz', roster).map((student) => student.id),
        [1],
    );
    assert.deepEqual(
        matchStudents('Reyes Jose', roster).map((student) => student.id),
        [3],
    );
    assert.deepEqual(
        matchStudents('Reyes', roster).map((student) => student.id),
        [3],
    );
    assert.deepEqual(
        matchStudents('Dela Cruz', roster).map((student) => student.id),
        [1, 2],
    );
    assert.deepEqual(matchStudents('Rey', roster), []);
});

test('parses numeric and spoken scores with or without names and score labels', () => {
    for (const phrase of ['18', 'eighteen', 'score eighteen', 'score of eighteen points']) {
        assert.deepEqual(parseScoreCommand(phrase), { name: '', action: { type: 'score', score: 18 } });
    }
    for (const phrase of ['Dela Cruz Ana 18', 'Dela Cruz Ana score eighteen', 'Dela Cruz Ana eighteen points.']) {
        assert.deepEqual(parseScoreCommand(phrase), { name: 'dela cruz ana', action: { type: 'score', score: 18 } });
    }
    assert.equal(spokenInteger('zero'), 0);
    assert.equal(spokenInteger('twenty-five'), 25);
    assert.equal(spokenInteger('one hundred and five'), 105);
    assert.equal(spokenInteger('one thousand two hundred thirty four'), 1234);
});

test('recognizes pass, skip, and activity absence', () => {
    assert.deepEqual(parseScoreCommand('pass'), { name: '', action: { type: 'pass' } });
    assert.deepEqual(parseScoreCommand('skip'), { name: '', action: { type: 'pass' } });
    assert.deepEqual(parseScoreCommand('Reyes Jose absent'), { name: 'reyes jose', action: { type: 'absent' } });
    assert.deepEqual(parseScoreCommand('Reyes Jose is absent'), { name: 'reyes jose', action: { type: 'absent' } });
});

test('rejects malformed, negative, decimal, and multiple scores rather than adding numbers', () => {
    for (const phrase of ['-1', 'negative five', 'minus five', '1.5', 'ten twenty', '10 20', 'one point five', 'one hundred hundred']) {
        assert.equal(parseScoreCommand(phrase).action, null, phrase);
    }
    assert.equal(spokenInteger('one hundred and'), null);
    assert.equal(spokenInteger('one thousand thousand'), null);
});
