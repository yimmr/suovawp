import { TextControl } from '@wordpress/components';
import type { InputFieldProps } from './types';
import { useCallback } from 'react';

export default ({
    id: _id,
    variant,
    as,
    value = '',
    onChange = (_v) => {},
    ...props
}: InputFieldProps) => {
    const type = as || variant || 'text';
    if (type === 'hidden') {
        const handleChange = useCallback(
            (e: React.ChangeEvent<HTMLInputElement>) => onChange(e.target.value),
            []
        );
        return <input type="hidden" {...props} value={value} onChange={handleChange} />;
    }
    return (
        <TextControl
            __nextHasNoMarginBottom
            {...props}
            onChange={onChange}
            type={type as any}
            value={value}
        />
    );
};
