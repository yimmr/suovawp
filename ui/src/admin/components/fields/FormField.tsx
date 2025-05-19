import { useState } from 'react';
import { FieldProps } from './types';
import { TextControl } from '@wordpress/components';

export default function ({ type, value: val = '' }: FieldProps) {
    const [value, setValue] = useState<string>(val);
    console.log('[FormField]:' + type);
    let field = null;
    switch (type) {
        case 'a':
            field = <FieldA value={value} onChange={setValue} />;
        case 'b':
            field = <FieldB value={value} onChange={setValue} />;
        case 'c':
        default:
            field = <FieldC value={value} onChange={setValue} />;
    }
    return <div>FormField{field}</div>;
}

function FieldA({ value, onChange }: FieldProps) {
    console.log('FieldA:', value);

    return <TextControl value={value} onChange={onChange} />;
}

function FieldB({ value, onChange }: FieldProps) {
    console.log('FieldB:', value);

    return <TextControl value={value} onChange={onChange} />;
}

function FieldC({ value, onChange }: FieldProps) {
    console.log('FieldC:', value);

    return <TextControl value={value} onChange={onChange} />;
}
