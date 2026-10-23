export type VoiceStudent = {
    id: number;
    first_name: string;
    last_name: string;
    middle_name?: string | null;
    full_name: string;
};

export type ScoreAction = { type: 'score'; score: number } | { type: 'pass' } | { type: 'absent' };

export const normalizeName = (value: string) =>
    value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9\s]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

/** Exact names only: never guess between students with a shared surname. */
export function matchStudents(name: string, students: VoiceStudent[]): VoiceStudent[] {
    const normalized = normalizeName(name);
    if (!normalized) return [];
    return students.filter((student) => {
        const { first_name: first, last_name: last, middle_name: middle } = student;
        return [
            last,
            `${last} ${first}`,
            `${first} ${last}`,
            `${last} ${first} ${middle ?? ''}`,
            `${first} ${middle ?? ''} ${last}`,
            student.full_name,
        ].some((alias) => normalizeName(alias) === normalized);
    });
}

const ones = [
    'zero',
    'one',
    'two',
    'three',
    'four',
    'five',
    'six',
    'seven',
    'eight',
    'nine',
    'ten',
    'eleven',
    'twelve',
    'thirteen',
    'fourteen',
    'fifteen',
    'sixteen',
    'seventeen',
    'eighteen',
    'nineteen',
];
const tens = ['twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

function underHundred(words: string[]): number | null {
    if (words.length === 1 && ones.includes(words[0])) return ones.indexOf(words[0]);
    const ten = tens.indexOf(words[0]);
    if (ten < 0) return null;
    if (words.length === 1) return (ten + 2) * 10;
    const unit = ones.indexOf(words[1]);
    return words.length === 2 && unit > 0 && unit < 10 ? (ten + 2) * 10 + unit : null;
}

function underThousand(words: string[]): number | null {
    if (words[1] !== 'hundred') return underHundred(words);
    const unit = ones.indexOf(words[0]);
    if (unit < 1 || unit > 9) return null;
    let rest = words.slice(2);
    if (rest[0] === 'and') rest = rest.slice(1);
    if (!rest.length) return words.at(-1) === 'and' ? null : unit * 100;
    const remainder = underHundred(rest);
    return remainder === null || remainder === 0 ? null : unit * 100 + remainder;
}

export function spokenInteger(value: string): number | null {
    if (/^\d+$/.test(value)) return Number(value);
    const words = value.replace(/-/g, ' ').split(/\s+/);
    let total = 0;
    let remaining = words;
    for (const [scale, multiplier] of [
        ['million', 1000000],
        ['thousand', 1000],
    ] as const) {
        const index = remaining.indexOf(scale);
        if (index < 0) continue;
        const part = underThousand(remaining.slice(0, index));
        if (part === null || part === 0) return null;
        total += part * multiplier;
        remaining = remaining.slice(index + 1);
    }
    if (!remaining.length) return total || null;
    if (total && remaining[0] === 'and') remaining = remaining.slice(1);
    const rest = underThousand(remaining);
    return rest === null ? null : total + rest;
}

/** One finalized utterance is one entry, with an optional name before it. */
export function parseScoreCommand(transcript: string): { name: string; action: ScoreAction | null } {
    const text = transcript
        .toLowerCase()
        .trim()
        .replace(/[.,!?]+$/, '')
        .replace(/\s+/g, ' ');
    const keyword = text.match(/^(?:(.*?)\s+)?(pass|skip|absent)$/);
    if (keyword) return { name: (keyword[1] ?? '').replace(/\s+is$/, ''), action: { type: keyword[2] === 'absent' ? 'absent' : 'pass' } };
    const scoreLabel = text.match(/^(.*?)\bscore(?:\s+(?:is|of))?\s+(.+?)(?:\s+points?)?$/);
    if (scoreLabel) {
        const score = spokenInteger(scoreLabel[2]);
        return { name: scoreLabel[1].trim(), action: score === null ? null : { type: 'score', score } };
    }
    const withoutPoints = text.replace(/\s+points?$/, '');
    const tokens = withoutPoints.split(' ');
    for (let index = 0; index < tokens.length; index++) {
        const score = spokenInteger(tokens.slice(index).join(' '));
        if (score !== null) {
            // Do not reinterpret malformed/negative/decimal scores as a student's name.
            const name = tokens.slice(0, index).join(' ');
            if (
                /(?:\d|minus|negative|point|hundred|thousand|million)\b/.test(name) ||
                tokens.slice(0, index).some((word) => ones.includes(word) || tens.includes(word))
            )
                break;
            return { name, action: { type: 'score', score } };
        }
    }
    return { name: transcript.trim(), action: null };
}
