import type { LanguageName } from '@uiw/codemirror-extensions-langs';
import type { WPColorPaletteProps } from './types-wp';
import type { CodeEditorTheme } from './CodeEditor';

type Errors = string[];

export type FormErrors<T = { [k: string]: any }> = {
    _errors: string[];
} & (T extends object ? { [K in keyof T]?: FormErrors<T[K]> } : Record<string, never>);

export type ErrorListProps = {
    errors?: Errors;
};

export interface BaseFieldProps {
    name: string;
    id?: string;
    label?: string;
    value?: any;
    defaultValue?: any;
    errors?: ErrorListProps['errors'];
    width?: 'full' | '1/2' | '1/3' | '20' | string;
    className?: React.HTMLAttributes<HTMLElement>['className'];
    style?: React.CSSProperties;
    help?: string | React.ReactNode;
    disabled?: boolean;
    required?: boolean;
    placeholder?: string;
    onDeleteError?: (id: string) => void;
    errorId?: string;
    parentName?: { raw: string; parsed: string };
}

type HTMLInputType =
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
    type: 'input';
    variant: HTMLInputType;
}

export interface TextFieldProps extends BaseFieldProps {
    type: 'text';
    min?: number;
    max?: number;
    showCount?: boolean;
}

export interface TextareaFieldProps extends BaseFieldProps {
    type: 'textarea';
    rows?: number;
    cols?: number;
    min?: number;
    max?: number;
    showCount?: boolean;
}

export interface NumberFieldProps extends BaseFieldProps {
    type: 'number';
    min?: number;
    max?: number;
    step?: number;
}

export type FieldOptionProps = {
    label: string;
    value: string;
};

export type FieldOptions = FieldOptionProps[] | { [v: string | number]: string };

export interface RadioFieldProps extends BaseFieldProps {
    type: 'radio';
    value?: string;
    options?: FieldOptions;
    inline?: boolean;
}

export interface CheckboxFieldProps extends BaseFieldProps {
    type: 'checkbox';
    checked?: boolean;
    value?: string | number | Array<string | number>;
    options?: FieldOptions;
    inline?: boolean;
    indeterminate?: boolean;
}

export interface ToggleFieldProps extends BaseFieldProps {
    type: 'toggle';
    title?: string;
    value?: boolean;
    checked?: boolean;
}

export interface RangeFieldProps extends BaseFieldProps {
    type: 'range';
    min?: number;
    max?: number;
    step?: number;
    value?: number;
}

export interface SelectFieldProps extends BaseFieldProps {
    type: 'select';
    options?: FieldOptions;
    multiple?: boolean;
    value?: string | string[];
}

export interface Tree {
    id: string;
    name: string;
    children?: Tree[];
}

export interface TreeSelectFieldProps extends BaseFieldProps {
    type: 'tree-select';
    tree: Tree[];
    value?: string;
    variant?: 'default' | 'minimal';
    size?: 'small' | 'default' | 'compact';
    prefix?: React.ReactNode;
    suffix?: React.ReactNode;
}

export interface DatePickerFieldProps extends BaseFieldProps {
    type: 'date-picker';
    format?: string;
    showTime?: boolean;
}

export interface UploadFieldProps extends BaseFieldProps {
    type: 'upload';
}

export interface ColorPaletteFieldProps extends BaseFieldProps {
    type: 'color-palette';
    clearable?: boolean;
    colors?: WPColorPaletteProps['colors'];
    value?: string;
}

export interface CodeEditorProps {
    value?: string;
    onChange: (value: string) => void;
    language?: LanguageName;
    theme?: CodeEditorTheme;
    height?: string;
    readOnly?: boolean;
}

export interface CodeFieldProps extends BaseFieldProps, Omit<CodeEditorProps, 'onChange'> {
    type: 'code';
    value?: string;
    lang?: CodeEditorProps['language'];
}

export type MediaType = 'image' | 'video' | 'audio' | 'application' | 'file';
export type Cols = Record<'default' | 'sm' | 'md' | 'lg' | 'xl' | '2xl', number>;
export type MediaCards = {
    showTitle?: boolean;
    replaceText?: string;
    removeText?: string;
    layout?: 'single' | 'normal' | 'compact' | 'loose' | 'icon' | Cols;
    gap?: number;
    ratio?: 'auto' | 'square' | 'auto' | '3/4' | string;
    objectFit?: 'cover' | 'contain' | 'fill' | 'none' | 'scale-down';
};

export interface MediaFieldProps extends BaseFieldProps {
    type: 'media';
    value?: number | number[];
    accept?: MediaType | MediaType[];
    query?: {
        author?: number;
        uploadedTo?: number;
    };
    modalTitle?: string;
    modalButtonText?: string;
    buttonText?: string;
    multiple?: boolean;
    cards?: MediaCards;
}

export interface CustomFieldProps extends BaseFieldProps {
    type: 'custom';
    children: string | ((props: CustomFieldProps) => string | React.JSX.Element);
    html?: boolean;
}

type ComplexFieldBaseProps = Omit<BaseFieldProps, 'errors'>;

export interface GroupFieldProps extends ComplexFieldBaseProps {
    type: 'group';
    value?: { [k: string]: any }[];
    fields: FieldProps[];
    errors?: FormErrors;
    titleFrom?: string;
    initOpen?: boolean;
    opened?: boolean;
    addText?: string;
}

export interface FieldsetFieldProps extends ComplexFieldBaseProps {
    type: 'fieldset';
    value?: { [k: string]: any };
    fields: FieldProps[];
    errors?: FormErrors;
    initOpen?: boolean;
    opened?: boolean;
    icon?: JSX.Element;
}

export type FieldProps =
    | TextFieldProps
    | TextareaFieldProps
    | NumberFieldProps
    | RadioFieldProps
    | CheckboxFieldProps
    | ToggleFieldProps
    | RangeFieldProps
    | SelectFieldProps
    | TreeSelectFieldProps
    | DatePickerFieldProps
    | UploadFieldProps
    | CustomFieldProps
    | ColorPaletteFieldProps
    | MediaFieldProps
    | GroupFieldProps
    | FieldsetFieldProps
    | CodeFieldProps;
