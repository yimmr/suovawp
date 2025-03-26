import { Fragment } from 'react/jsx-runtime';
import FormField from './fields/Field';
import type { FieldProps, FormErrors } from './fields/types';
import { parseClassNames } from './utils';

import './_form.css';

export interface FormContentProps {
    fields: FieldProps[];
    errors?: FormErrors;
    idx?: number;
    isChild?: boolean;
    parentId?: string;
    onDeleteError?: (id: string) => void;
    parentName?: { raw: string; parsed: string };
    defaultWidth?: string;
}
let keyidx = 0;
export default function ({
    fields,
    errors,
    idx,
    onDeleteError,
    parentId = '',
    parentName,
    defaultWidth,
}: FormContentProps) {
    return fields.map((field) => {
        const { className, width, type = 'text', id = '', name } = field;
        const errorId = parentId ? `${parentId}.${id}` : id;
        const clsn = parseClassNames(className, width, type, defaultWidth);
        let errorArr;
        if (errors && errors[id] != null) {
            if (type === 'fieldset' || type === 'group') {
                errorArr = errors[id];
            } else {
                errorArr = errors[id]._errors;
                if (errorArr == null && errors[id] != null && field.name.endsWith('[]')) {
                    errorArr = [];
                    if (Array.isArray(errors[id])) {
                        for (const e of errors[id]) {
                            errorArr.push(...e._errors);
                        }
                    }
                }
            }
        }

        let realName = name;
        if (realName && parentName && parentName?.raw) {
            const { raw, parsed } = parentName;
            realName = realName.startsWith(raw) ? parsed + realName.slice(raw.length) : realName;
        }
        if (realName && idx != null) {
            realName = realName.replace('{{idx}}', idx.toString());
        }

        return (
            <Fragment key={id + '_' + (keyidx += 1)}>
                <FormField
                    {...field}
                    name={realName}
                    id={undefined}
                    errors={errorArr as any}
                    className={clsn}
                    errorId={id ? errorId : parentId}
                    onDeleteError={onDeleteError}
                    parentName={{ raw: name, parsed: realName }}
                />
            </Fragment>
        );
    });
}
