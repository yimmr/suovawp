import type { ToggleFieldProps } from './types';
import { FormToggle } from '@wordpress/components';
import useFormRestState from '../../hooks/useFormRestState';
import BaseField from './BaseField';

export default function ({ errors, value, name, label, title, help, disabled }: ToggleFieldProps) {
    const [checked, setChecked, inputRef] = useFormRestState<HTMLInputElement, boolean>(!!value);
    const newTitle = title || label;
    const newLabel = title ? label : undefined;
    return (
        <BaseField id={name} errors={errors} label={newLabel} help={help}>
            <input ref={inputRef} type="hidden" name={name} defaultValue={checked ? 1 : 0} />
            <div className="!tw-leading-none">
                <FormToggle
                    disabled={disabled}
                    checked={checked}
                    onChange={(c) => setChecked(c.target.checked)}
                />
                <label className="tw-inline-block tw-mt-px tw-ml-2 tw-align-text-top">
                    {newTitle}
                </label>
            </div>
        </BaseField>
    );
}
