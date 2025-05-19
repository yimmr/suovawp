import type { RadioFieldProps } from './types2';
import { RadioControl } from '@wordpress/components';
import BaseField from './BaseField';
import useFormRestState from '../../hooks/useFormRestState';
import { convertFieldOptions } from '../utils';

export default function ({
    errors,
    type: _t,
    options,
    value,
    inline = true,
    id: _id,
    className: clsn,
    ...props
}: RadioFieldProps) {
    const [selected, setSelected, fieldRef] = useFormRestState<HTMLDivElement, typeof value>(value);
    if (!options) {
        return (
            <BaseField errors={[`[${props.label || 'radio'}]Missing radio options!`]}></BaseField>
        );
    }
    const fieldOptions = convertFieldOptions(options);
    let className = `${clsn ? clsn + ' ' : ''}suovawp-radio-${inline ? 'inline' : 'block'}`;
    return (
        <BaseField errors={errors} fieldRef={fieldRef}>
            <RadioControl
                {...props}
                className={`[&>legend]:!tw-font-bold [&>legend]:!tw-text-sm ${className}`}
                options={fieldOptions}
                selected={selected}
                onChange={setSelected}
            />
        </BaseField>
    );
}
