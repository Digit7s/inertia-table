const phpDateTokens = new Set([
    'Y',
    'y',
    'm',
    'n',
    'd',
    'j',
    'D',
    'l',
    'N',
    'w',
    'F',
    'M',
    'H',
    'G',
    'h',
    'g',
    'i',
    's',
    'a',
    'A',
]);

function parseDate(value, type) {
    if (value instanceof Date) {
        return value;
    }

    if (typeof value === 'string' && type === 'date') {
        const dateOnly = /^(\d{4})-(\d{2})-(\d{2})(?:[ T].*)?$/.exec(value);

        if (dateOnly) {
            const parsed = new Date(
                Number(dateOnly[1]),
                Number(dateOnly[2]) - 1,
                Number(dateOnly[3]),
            );

            if (
                parsed.getFullYear() !== Number(dateOnly[1]) ||
                parsed.getMonth() !== Number(dateOnly[2]) - 1 ||
                parsed.getDate() !== Number(dateOnly[3])
            ) {
                return new Date(Number.NaN);
            }

            return parsed;
        }
    }

    if (typeof value !== 'string' && typeof value !== 'number') {
        return new Date(Number.NaN);
    }

    return new Date(value);
}

function tokenValue(token, date, locale) {
    const day = date.getDate();
    const hour = date.getHours();

    switch (token) {
        case 'Y':
            return String(date.getFullYear());
        case 'y':
            return String(date.getFullYear()).slice(-2);
        case 'm':
            return String(date.getMonth() + 1).padStart(2, '0');
        case 'n':
            return String(date.getMonth() + 1);
        case 'd':
            return String(day).padStart(2, '0');
        case 'j':
            return String(day);
        case 'D':
            return new Intl.DateTimeFormat(locale, { weekday: 'short' }).format(
                date,
            );
        case 'l':
            return new Intl.DateTimeFormat(locale, { weekday: 'long' }).format(
                date,
            );
        case 'N':
            return String(date.getDay() === 0 ? 7 : date.getDay());
        case 'w':
            return String(date.getDay());
        case 'F':
            return new Intl.DateTimeFormat(locale, { month: 'long' }).format(
                date,
            );
        case 'M':
            return new Intl.DateTimeFormat(locale, { month: 'short' }).format(
                date,
            );
        case 'H':
            return String(hour).padStart(2, '0');
        case 'G':
            return String(hour);
        case 'h':
            return String(hour % 12 || 12).padStart(2, '0');
        case 'g':
            return String(hour % 12 || 12);
        case 'i':
            return String(date.getMinutes()).padStart(2, '0');
        case 's':
            return String(date.getSeconds()).padStart(2, '0');
        case 'a':
            return hour < 12 ? 'am' : 'pm';
        case 'A':
            return hour < 12 ? 'AM' : 'PM';
        default:
            return token;
    }
}

/**
 * Format a serialized Laravel date value. Date-only values are parsed as local
 * calendar dates; datetimes retain the existing browser-local display behavior.
 * A supplied format uses the documented PHP date token subset.
 */
export function formatDateValue(value, type, format, locale) {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    const date = parseDate(value, type);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    if (typeof format === 'string' && format.length > 0) {
        let result = '';
        let escaped = false;

        for (const character of format) {
            if (escaped) {
                result += character;
                escaped = false;
            } else if (character === '\\') {
                escaped = true;
            } else if (phpDateTokens.has(character)) {
                result += tokenValue(character, date, locale);
            } else {
                result += character;
            }
        }

        return escaped ? `${result}\\` : result;
    }

    return type === 'date'
        ? date.toLocaleDateString(locale)
        : date.toLocaleString(locale);
}
