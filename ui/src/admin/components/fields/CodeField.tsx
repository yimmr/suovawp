import type { CodeFieldProps } from './types';
import useFormRestState from '../../hooks/useFormRestState';
import BaseField from './BaseField';
import CodeEditor from './CodeEditor';

export default ({
    type: _t,
    errors,
    value,
    name,
    label,
    help,
    className,
    style,
    language,
    lang = 'html',
    ...props
}: CodeFieldProps) => {
    const [code, setCode, inputRef] = useFormRestState<HTMLInputElement, typeof value>(value);
    return (
        <BaseField id={name} label={label} help={help} errors={errors}>
            <input ref={inputRef} type="hidden" name={name} defaultValue={code} />
            <div className={className} style={style}>
                <CodeEditor
                    {...props}
                    language={language || lang}
                    value={code}
                    onChange={setCode}
                />
            </div>
        </BaseField>
    );
};
