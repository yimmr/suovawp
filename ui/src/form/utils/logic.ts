type CompareOperators<T> = {
    '==='?: T;
    '!=='?: T;
    '=='?: T;
    '!='?: T;
    '>'?: T;
    '>='?: T;
    '<'?: T;
    '<='?: T;
    eq?: T;
    ne?: T;
    gt?: T;
    gte?: T;
    lt?: T;
    lte?: T;
    in?: T[];
    nin?: T[];
    contains?: T extends string ? string : never;
    startsWith?: T extends string ? string : never;
    endsWith?: T extends string ? string : never;
    regex?: T;
    empty?: boolean | null;
};

type FieldCondition<T> = T | CompareOperators<T>;

type LogicalOperators<T> = {
    AND?: Condition<T> | Condition<T>[];
    OR?: Condition<T> | Condition<T>[];
};

type Condition<T> = LogicalOperators<T> & {
    [K in keyof T]?: FieldCondition<T[K]>;
};

export function parseCondition<T>(condition: Condition<T>): boolean {
    for (const key in condition) {
        const value = condition[key];
        if (key === 'AND' || key === 'OR') {
            if (Array.isArray(value)) {
                continue;
            }
        }
        if (typeof value === 'object' && value !== null) {
        }
    }
}

export function parseOperator(op: string, a: any, b: any) {
    switch (op) {
        case '===':
        case 'eq':
            return a === b;
        case '!==':
        case 'ne':
            return a !== b;
        case '==':
            return a == b; // eslint-disable-line eqeqeq
        case '!=':
            return a != b; // eslint-disable-line eqeqeq
        case '>':
        case 'gt':
            return a > b;
        case '>=':
        case 'gte':
            return a >= b;
        case '<':
        case 'lt':
            return a < b;
        case '<=':
        case 'lte':
            return a <= b;
        case 'in':
            return Array.isArray(b) && b.includes(a);
        case 'nin':
            return Array.isArray(b) && !b.includes(a);
        case 'contains':
            return typeof a === 'string' && a.includes(b);
        case 'startsWith':
            return typeof a === 'string' && a.startsWith(b);
        case 'endsWith':
            return typeof a === 'string' && typeof b === 'string' && a.endsWith(b);
        case 'regex':
            return typeof a === 'string' && typeof b === 'string' && new RegExp(b).test(a);
        case 'empty':
            return b === false ? !isEmpty(a) : isEmpty(a);
        default:
            throw new Error(`Unknown operator: ${op}`);
    }
}

export function isEmpty<T>(value: T): boolean {
    if (value === null || value === undefined) {
        return true;
    }
    if (typeof value === 'string') {
        return value.trim().length === 0;
    }
    if (Array.isArray(value)) {
        return value.length === 0;
    }
    if (value instanceof Map || value instanceof Set) {
        return value.size === 0;
    }
    if (typeof value === 'object' && value !== null) {
        return Object.keys(value).length === 0;
    }
    return false;
}
