import { SelectControl } from '@wordpress/components';
import BaseField from './BaseField';
import type { SelectFieldProps } from './types';
import useFormRestState from '../hooks/useFormRestState';
import { convertFieldOptions } from '../utils';

export default function ({
    errors,
    value,
    options,
    type: _t,
    multiple = false,
    ...props
}: SelectFieldProps) {
    const [selected, setSelected, fieldRef] = useFormRestState<HTMLDivElement, typeof value>(value);

    if (!options) {
        return (
            <BaseField errors={[`[${props.label || 'select'}]Missing select options!`]}></BaseField>
        );
    }
    const fieldOptions = convertFieldOptions(options);

    return (
        <BaseField fieldRef={fieldRef} errors={errors}>
            {multiple ? (
                <SelectControl
                    __nextHasNoMarginBottom
                    {...props}
                    id={props.name}
                    options={fieldOptions}
                    value={selected as string[]}
                    onChange={setSelected}
                    multiple={true}
                />
            ) : (
                <SelectControl
                    __nextHasNoMarginBottom
                    {...props}
                    id={props.name}
                    options={fieldOptions}
                    value={selected as string}
                    onChange={setSelected}
                />
            )}
        </BaseField>
    );
}
