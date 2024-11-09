import { BaseFieldProps, FieldOptionProps, FieldOptions } from './fields/types';

const widthClasses = {
    full: 'md:tw-w-full',
    '1/2': 'md:tw-w-1/2',
    '1/3': 'md:tw-w-1/3',
    '20': 'md:tw-w-20',
};

export const parseClassNames = (
    className?: BaseFieldProps['className'],
    width?: BaseFieldProps['width'],
    type?: string,
    defaultWidth = '1/2'
) => {
    const parsed: string[] = [];
    const w = width || (type === 'number' ? '20' : defaultWidth);
    parsed.push(widthClasses[w as keyof typeof widthClasses] || `md:tw-w-[${w}]`);
    if (className) {
        parsed.push(Array.isArray(className) ? className.join(' ') : className);
    }
    return parsed.join(' ');
};

export const convertFieldOptions = (options: FieldOptions) => {
    if (Array.isArray(options)) {
        return options;
    }
    const newOptions: FieldOptionProps[] = [];
    for (const [key, value] of Object.entries(options)) {
        newOptions.push({ label: value, value: key });
    }
    return newOptions;
};

export function isValidDateTime(dateTimeStr: string): boolean {
    try {
        const date = new Date(dateTimeStr);
        return !isNaN(date.getTime());
    } catch (error) {
        return false;
    }
}

let localeTextData: Record<string, string> = {};

export function setLocaleText(key: string | typeof localeTextData, value?: string) {
    if (typeof key === 'object' && key != null) {
        localeTextData = { ...key };
    } else if (value) {
        localeTextData[key] = value;
    }
}

export function getLocaleText(key?: string) {
    if (key === undefined) return '';
    return key in localeTextData ? localeTextData[key] : key;
}

type Columns = Partial<Record<'default' | 'sm' | 'md' | 'lg' | 'xl' | '2xl', number>>;
export function generateGridCols(columns: Columns, prefix = '') {
    const classes = Object.keys(columns).map((c) => {
        if (c === 'default') return `${prefix}grid-cols-${columns[c as keyof typeof columns]}`;
        return `${c}:${prefix}grid-cols-${columns[c as keyof typeof columns]}`;
    });
    return classes.join(' ');
}

export function generateGridColsWithPreset<T extends Record<string, Columns>>(
    preset: T,
    cols?: keyof T | Columns,
    prefix = 'tw-'
) {
    if (!cols) return '';
    const columns = typeof cols === 'string' ? preset[cols] : cols;
    return generateGridCols(columns as Columns, prefix);
}
