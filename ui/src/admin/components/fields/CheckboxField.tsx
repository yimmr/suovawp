import { CheckboxControl } from '@wordpress/components';
import BaseField from './BaseField';
import type { CheckboxFieldProps } from './types2';
import { Fragment } from 'react/jsx-runtime';
import useFormRestState from '../../hooks/useFormRestState';
import { convertFieldOptions } from '../utils';

export default function CheckboxField(props: CheckboxFieldProps) {
    const { options } = props;
    return options ? <CheckboxGroupField {...props} /> : <SingleCheckboxField {...props} />;
}

function SingleCheckboxField({ errors, type: _t, value, ...props }: CheckboxFieldProps) {
    return (
        <BaseField errors={errors}>
            <WPCheckboxField {...props} value={value?.toString()} />
        </BaseField>
    );
}

function CheckboxGroupField(props: CheckboxFieldProps) {
    const {
        errors,
        type: _t,
        options,
        value,
        name,
        help,
        label,
        inline,
        className: clsn,
        indeterminate,
    } = props;

    const fieldOptions = convertFieldOptions(options || []);
    let values: any[] = Array.isArray(value) ? value : [value];
    let className = `${clsn ? clsn + ' ' : ''}suovawp-checkbox-${inline ? 'inline' : 'block'}`;

    return (
        <BaseField errors={errors} help={help} label={label} id={name}>
            <div className={className}>
                {fieldOptions.map(({ value, label }) => (
                    <Fragment key={value}>
                        <WPCheckboxField
                            label={label}
                            name={name}
                            value={value}
                            indeterminate={indeterminate}
                            className="tw-mb-3"
                            checked={values.includes(value)}
                        />
                    </Fragment>
                ))}
            </div>
        </BaseField>
    );
}

function WPCheckboxField({
    checked: defChecked,
    id: _id,
    ...props
}: { value?: string | number } & Partial<Omit<CheckboxFieldProps, 'type'>>) {
    const [checked, setChecked, inputRef] = useFormRestState<HTMLElement, boolean>(!!defChecked);
    return (
        <span ref={inputRef}>
            <CheckboxControl
                __nextHasNoMarginBottom
                {...props}
                checked={checked}
                onChange={setChecked}
            />
        </span>
    );
}
