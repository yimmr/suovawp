import type { GroupFieldProps } from './types';
import FormContent from '../FormContent';
import { PanelBody } from '@wordpress/components';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { useEffect, useRef, useState } from 'react';

const getItemTitle = (
    from: GroupItemProps['titleFrom'],
    value: GroupItemProps['value'],
    fields: GroupItemProps['fields'],
    keys: string[] = []
) => {
    if (!from) return;
    if (value) {
        if (value[from]) return value[from];
        for (const key of keys) {
            if (value[key]) return value[key];
        }
    }
    if (fields.length) {
        const field = fields.find((field) => field?.id === from);
        if (field?.value) return field?.value;
    }
};

interface GroupItemProps
    extends Pick<
        GroupFieldProps,
        | 'fields'
        | 'errors'
        | 'titleFrom'
        | 'initOpen'
        | 'opened'
        | 'errorId'
        | 'onDeleteError'
        | 'parentName'
    > {
    id: number;
    idx: number;
    value?: { [k: string]: any };
    onRemove?: (id: number) => void;
}

export default function ({
    id,
    idx,
    fields,
    titleFrom = 'title',
    errors,
    initOpen = false,
    opened,
    onRemove,
    value = {},
    errorId,
    onDeleteError,
    parentName,
}: GroupItemProps) {
    const { attributes, listeners, setNodeRef, transform, transition } = useSortable({
        id,
        disabled: false,
    });
    const divRef = useRef<HTMLDivElement>(null);
    const [title, setTitle] = useState(
        getItemTitle(titleFrom, value, fields, ['label']) || `项目${id}`
    );

    useEffect(() => {
        if (divRef.current == null || !titleFrom) return;
        let input: HTMLElement | null;
        const func = (e: Event) => {
            const input = e.target as HTMLInputElement;
            input?.value && setTitle(input.value);
        };
        for (const field of fields) {
            if (field?.id === titleFrom) {
                let name = field.name || '';
                name = name.replace('{{idx}}', idx.toString());
                input = divRef.current.querySelector<HTMLElement>(`input[name="${name}"]`);
                input?.addEventListener('change', func);
                break;
            }
        }
        return () => {
            if (input) {
                input.removeEventListener('change', func);
            }
        };
    }, [fields]);

    const style = { transform: CSS.Transform.toString(transform), transition };

    // 自定义面板标题组件
    const CustomTitle = (
        <div className="tw-flex tw-items-center tw-w-full">
            <span
                {...attributes}
                {...listeners}
                role="button"
                className="tw-flex tw-items-center tw-justify-center tw-cursor-move tw-mr-3 tw-px-3 -tw-ml-2 hover:tw-bg-gray-100 tw-rounded"
            >
                ⋮⋮
            </span>
            <span>{title}</span>
            <span
                role="button"
                onClick={(e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    onRemove?.(id);
                    errorId && onDeleteError?.(errorId);
                }}
                className="tw-flex tw-items-center tw-justify-center tw-ml-auto tw-text-red-500 tw-text-sm tw-mr-2 tw-px-1 active:tw-text-red-600 active:tw-scale-50 tw-duration-100
                tw-absolute tw-top-0 tw-bottom-0 tw-h-full tw-right-12"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="currentColor"
                    className="tw-size-4"
                >
                    <path
                        fillRule="evenodd"
                        d="M16.5 4.478v.227a48.816 48.816 0 0 1 3.878.512.75.75 0 1 1-.256 1.478l-.209-.035-1.005 13.07a3 3 0 0 1-2.991 2.77H8.084a3 3 0 0 1-2.991-2.77L4.087 6.66l-.209.035a.75.75 0 0 1-.256-1.478A48.567 48.567 0 0 1 7.5 4.705v-.227c0-1.564 1.213-2.9 2.816-2.951a52.662 52.662 0 0 1 3.369 0c1.603.051 2.815 1.387 2.815 2.951Zm-6.136-1.452a51.196 51.196 0 0 1 3.273 0C14.39 3.05 15 3.684 15 4.478v.113a49.488 49.488 0 0 0-6 0v-.113c0-.794.609-1.428 1.364-1.452Zm-.355 5.945a.75.75 0 1 0-1.5.058l.347 9a.75.75 0 1 0 1.499-.058l-.346-9Zm5.48.058a.75.75 0 1 0-1.498-.058l-.347 9a.75.75 0 0 0 1.5.058l.345-9Z"
                        clipRule="evenodd"
                    />
                </svg>
            </span>
        </div>
    );

    const newFields = fields.map((field) => {
        if (field?.id && field.id in value) {
            const fieldVal = value[field.id];
            if (field?.type === 'fieldset' && typeof fieldVal === 'object') {
                for (const subfield of field.fields) {
                    if (subfield?.id && subfield.id in fieldVal) {
                        subfield.value = fieldVal[subfield.id];
                    }
                }
            }
            return {
                ...field,
                value: fieldVal,
            };
        }
        return field;
    });

    return (
        <div
            ref={setNodeRef}
            style={style}
            className={`tw-border${errors ? ' tw-border-red-500' : ''} tw-bg-white tw-mb-1`}
        >
            <PanelBody
                className="[&.is-opened_.components-panel\_\_body-title]:tw-border-b"
                title={CustomTitle as unknown as string}
                initialOpen={initOpen}
                opened={opened}
                scrollAfterOpen={false}
                children={({ opened }) => (
                    <div ref={divRef} className={`${opened ? 'tw-block' : 'tw-hidden'}`}>
                        <FormContent
                            isChild={true}
                            idx={idx}
                            fields={newFields}
                            errors={errors}
                            onDeleteError={onDeleteError}
                            parentId={errorId}
                            parentName={parentName}
                            defaultWidth="full"
                        />
                    </div>
                )}
            ></PanelBody>
        </div>
    );
}
