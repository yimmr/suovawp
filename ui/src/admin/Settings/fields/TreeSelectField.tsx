import { TreeSelect } from '@wordpress/components';
import type { TreeSelectFieldProps } from './types';
import BaseField from './BaseField';
import useFormRestState from '../hooks/useFormRestState';

export default function ({ errors, value, type: _t, tree, ...props }: TreeSelectFieldProps) {
    const [selected, setSelected, fieldRef] = useFormRestState<HTMLDivElement, string>(value ?? '');
    if (!tree) {
        return (
            <BaseField
                errors={[`[${props.label || 'tree-select'}]Missing tree-select tree!`]}
            ></BaseField>
        );
    }
    return (
        <BaseField errors={errors} fieldRef={fieldRef}>
            <TreeSelect
                __nextHasNoMarginBottom
                {...props}
                tree={tree}
                onChange={setSelected}
                selectedId={selected}
            />
        </BaseField>
    );
}
