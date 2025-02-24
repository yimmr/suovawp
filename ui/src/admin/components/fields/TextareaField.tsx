import type { TextareaFieldProps } from './types';
import { TextareaControl } from '@wordpress/components';
import BaseField from './BaseField';

export default ({ errors, value, type, id: _id, ...props }: TextareaFieldProps) => {
    return (
        <BaseField errors={errors}>
            <TextareaControl
                __nextHasNoMarginBottom
                onChange={(_v) => {}}
                {...(props as any)}
                defaultValue={value}
            />
        </BaseField>
    );
};
