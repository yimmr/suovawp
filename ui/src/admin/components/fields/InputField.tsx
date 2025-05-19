import { TextControl } from '@wordpress/components';
import type { InputFieldProps } from './types2';
import BaseField from './BaseField';

export default ({ errors, value, variant, id: _id, ...props }: InputFieldProps) => {
    return (
        <BaseField errors={errors}>
            <TextControl
                __nextHasNoMarginBottom
                onChange={(_v) => {}}
                {...props}
                type={variant}
                defaultValue={value}
            />
        </BaseField>
    );
};
