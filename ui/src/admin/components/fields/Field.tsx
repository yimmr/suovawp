import { lazy, Suspense } from 'react';
import type { FieldProps } from './types2';
import TextareaField from './TextareaField';
import TextField from './TextField';
import InputField from './InputField';
import CheckboxField from './CheckboxField';
import RadioField from './RadioField';
import SelectField from './SelectField';
import NumberField from './NumberField';
import CustomField from './CustomField';
import UploadField from './UploadField';
import DatePickerField from './DatePickerField';
import ColorPaletteField from './ColorPaletteField';
import ToggleField from './ToggleField';
import RangeField from './RangeField';
import MediaField from './MediaField';
import TreeSelectField from './TreeSelectField';
import GroupField from './GroupField';
import FieldsetField from './FieldsetField';

const LazyCodeEditor = lazy(() => import('./CodeField'));

export default function Field({ errorId, parentName, onDeleteError, ...props }: FieldProps) {
    const { type } = props;
    switch (type) {
        case 'text':
            return <TextField {...props} />;
        case 'textarea':
            return <TextareaField {...props} />;
        case 'number':
            return <NumberField {...props} />;
        case 'checkbox':
            return <CheckboxField {...props} />;
        case 'radio':
            return <RadioField {...props} />;
        case 'toggle':
            return <ToggleField {...props} />;
        case 'range':
            return <RangeField {...props} />;
        case 'select':
            return <SelectField {...props} />;
        case 'tree-select':
            return <TreeSelectField {...props} />;
        case 'date-picker':
            return <DatePickerField {...props} />;
        case 'upload':
            return <UploadField {...props} />;
        case 'color-palette':
            return <ColorPaletteField {...props} />;
        case 'code':
            return (
                <Suspense fallback={<div>Loading editor...</div>}>
                    <LazyCodeEditor {...props} />
                </Suspense>
            );
        case 'media':
            return <MediaField {...props} />;
        case 'custom':
            return <CustomField {...props} />;
        case 'group':
            return <GroupField {...props} {...{ errorId, parentName, onDeleteError }} />;
        case 'fieldset':
            return <FieldsetField {...props} {...{ errorId, parentName, onDeleteError }} />;
        default:
            return <InputField {...(props as any)} variant="text" />;
    }
}
