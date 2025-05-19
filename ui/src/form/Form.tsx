import { FieldProps } from './types';

export interface FormProps {
    data: { [k: string]: any };
    fields: FieldProps[];
}

export default function Form({ fields }: FormProps) {
    return (
        <form>
            {fields.map((field, idx) => {
                const { type = 'text', id = '', name } = field;
                const clsn = parseClassNames(field.className, field.width, type);
                let realName = name;
                if (realName && idx != null) {
                    realName = realName.replace('{{idx}}', idx.toString());
                }
                return (
                    <div key={id} className={clsn}>
                        <label htmlFor={id}>{field.label}</label>
                        <input id={id} name={realName} type={type} />
                    </div>
                );
            })}
        </form>
    );
}
