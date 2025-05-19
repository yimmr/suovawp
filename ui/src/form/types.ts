type FieldType =
    | 'text'
    | 'input'
    | 'textarea'
    | 'number'
    | 'radio'
    | 'checkbox'
    | 'toggle'
    | 'range'
    | 'select';
export type FieldProps = {
    type:
        | 'text'
        | 'input'
        | 'textarea'
        | 'number'
        | 'radio'
        | 'checkbox'
        | 'toggle'
        | 'range'
        | 'select';
} & (
    | TextFieldProps
    | TextareaFieldProps
    | NumberFieldProps
    | RadioFieldProps
    | CheckboxFieldProps
    | ToggleFieldProps
    | RangeFieldProps
    | SelectFieldProps
);

export interface BaseFieldProps {
    name: string;
    id?: string;
    label?: string;
    value?: any;
    defaultValue?: any;
    placeholder?: string;
    help?: string | React.ReactNode;
    required?: boolean;
    hidden?: boolean; // 隐藏且可提交
    disabled?: boolean; // 不可交互但可见，不提交
    readonly?: boolean; // 只读不可编辑，可聚焦、提交
    draggable?: boolean; // 是否可拖拽
    width?: 'full' | '1/2' | '1/3' | '20' | string;
    className?: React.HTMLAttributes<HTMLElement>['className'];
    style?: React.CSSProperties;
    pattern?: string; // 正则验证模式
    onChange?: (value: string) => void;
}

type HTMLInputType =
    | 'hidden'
    | 'text'
    | 'password'
    | 'email'
    | 'number'
    | 'tel'
    | 'url'
    | 'search'
    | 'date'
    | 'time'
    | 'datetime-local'
    | 'color';

export interface InputFieldProps extends BaseFieldProps {
    variant?: HTMLInputType;
    as?: HTMLInputType; // 作为原生input的type
    autocomplete?: boolean;
}

export interface TextFieldProps extends BaseFieldProps {
    min?: number;
    max?: number;
    showCount?: boolean;
    autocomplete?: boolean;
}

export interface TextareaFieldProps extends BaseFieldProps {
    rows?: number;
    cols?: number;
    min?: number;
    max?: number;
    showCount?: boolean;
    autocomplete?: boolean;
}

export interface NumberFieldProps extends BaseFieldProps {
    min?: number;
    max?: number;
    step?: number;
    autocomplete?: boolean;
}

export type FieldOptionProps = {
    label: string;
    value: string;
};

export type FieldOptions = FieldOptionProps[] | { [v: string | number]: string };

export interface RadioFieldProps extends BaseFieldProps {
    value?: string;
    options?: FieldOptions;
    inline?: boolean;
}

export interface CheckboxFieldProps extends BaseFieldProps {
    checked?: boolean;
    value?: string | number | Array<string | number>;
    options?: FieldOptions;
    inline?: boolean;
    indeterminate?: boolean;
}

export interface ToggleFieldProps extends BaseFieldProps {
    title?: string;
    value?: boolean;
    checked?: boolean;
}

export interface RangeFieldProps extends BaseFieldProps {
    min?: number;
    max?: number;
    step?: number;
    value?: number;
}

export interface SelectFieldProps extends BaseFieldProps {
    options?: FieldOptions;
    multiple?: boolean;
    value?: string | string[];
}
