import { TextareaControl } from '@wordpress/components';
import { FieldProps } from './types';
import InputField from './InputField';
import { useState } from 'react';

export default function ({ hidden, ...props }: FieldProps) {
    const [value, setValue] = useState(props.value || '');
    return renderField(props);
}

function renderField({ type, ...props }: FieldProps) {
    switch (type) {
        case 'text':
            return <InputField {...props} />;
        case 'textarea':
            const { id: _id, value = '', onChange = () => {}, ...rest } = props;
            return <TextareaControl {...rest} value={value} onChange={onChange} />;
        case 'number':
            return <InputField {...props} as="number" />;
        default:
            return <InputField {...props} />;
    }
}
