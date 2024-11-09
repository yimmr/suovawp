import type { RangeFieldProps } from './types';
import { RangeControl } from '@wordpress/components';
import useFormRestState from '../hooks/useFormRestState';
import BaseField from './BaseField';

export default function ({ errors, type, value: val, name, ...props }: RangeFieldProps) {
    const [value, setValue, inputRef] = useFormRestState<HTMLInputElement, number | undefined>(val);
    return (
        <BaseField errors={errors}>
            <RangeControl
                __nextHasNoMarginBottom={true}
                {...props}
                value={value}
                // initialPosition={value}
                onChange={setValue}
            />
            <input ref={inputRef} type="hidden" name={name} defaultValue={value} />
        </BaseField>
    );
}
