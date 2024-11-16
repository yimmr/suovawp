import { ColorPalette } from '@wordpress/components';
import type { ColorPaletteFieldProps } from './types';
import useFormRestState from '../../hooks/useFormRestState';
import BaseField from './BaseField';

export default ({
    errors,
    value,
    name,
    label,
    help,
    className,
    type: _t,
    ...props
}: ColorPaletteFieldProps) => {
    const [color, setColor, inputRef] = useFormRestState<HTMLInputElement, typeof value>(value);
    const clsn = `${className ? ' ' + className : ''}`;
    return (
        <BaseField id={name} label={label} help={help} errors={errors}>
            <input ref={inputRef} type="hidden" name={name} defaultValue={color} />
            <div className={`tw-border tw-p-4 tw-py-5 tw-rounded${clsn}`}>
                <ColorPalette {...props} value={color} onChange={setColor} />
            </div>
        </BaseField>
    );
};
