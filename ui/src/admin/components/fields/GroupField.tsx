import BaseField from './BaseField';
import type { GroupFieldProps } from './types';

import { useCallback, useMemo, useRef, useState } from 'react';
import {
    DndContext,
    closestCenter,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import type { DragEndEvent } from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import HelpText from './HelpText';
import GroupItemField from './GroupItemField';
import useFormRestState from '../../hooks/useFormRestState';

const extractFirstItemFromFieldsIf = (
    value: GroupFieldProps['value'],
    fields: GroupFieldProps['fields']
) => {
    if (Array.isArray(value)) {
        return value;
    }
    const item: { [k: string]: any } = {};
    let has = false;
    for (const field of fields) {
        if ('value' in field && field?.id) {
            item[field.id] = field.value;
            has = true;
        }
    }
    return has ? [item] : [];
};

const setupInitValue = (value: GroupFieldProps['value'], fields: GroupFieldProps['fields']) => {
    const items = extractFirstItemFromFieldsIf(value, fields);
    const map = new Map<number, { [k: string]: any }>();
    items.forEach((item, i) => {
        map.set(i + 1, item);
    });
    return [map, [...map.keys()]] as [typeof map, number[]];
};

export default ({
    errors,
    label,
    name,
    help,
    fields,
    className,
    addText,
    opened,
    initOpen,
    value,
    titleFrom,
    onDeleteError,
    errorId,
    parentName,
}: GroupFieldProps) => {
    const [initValue, initIds] = useMemo(() => setupInitValue(value, fields), [value, fields]);
    const [ids, setIds, fieldRef] = useFormRestState<HTMLDivElement, typeof initIds>(initIds);
    const lastIdRef = useRef(ids.length);
    const generateId = useCallback(() => (lastIdRef.current += 1), []);
    const [isDragStart, setDragStart] = useState(false);

    const sensors = useSensors(
        useSensor(PointerSensor, {
            activationConstraint: {
                distance: 5, // 需要移动5px才触发拖拽
            },
        }),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        })
    );

    const handleDragStart = useCallback(
        function () {
            setDragStart(true);
            errorId && onDeleteError?.(errorId);
        },
        [onDeleteError]
    );

    const handleDragEnd = useCallback(function (event: DragEndEvent) {
        setDragStart(false);
        const { active, over } = event;
        const aid = Number(active.id);
        const oid = over?.id != null ? Number(over.id) : undefined;
        if (oid != null && aid !== oid) {
            setIds((ids) => {
                if (over) {
                    const oldIndex = ids.indexOf(aid);
                    const newIndex = ids.indexOf(oid);
                    return arrayMove(ids, oldIndex, newIndex);
                }
                return ids;
            });
        }
    }, []);

    const handleAddItem = useCallback(() => {
        setIds((ids) => [...ids, generateId()]);
    }, []);

    const handleRemoveItem = useCallback((idx: number) => {
        setIds((ids) => ids.filter((id) => id !== idx));
    }, []);

    return (
        <BaseField errors={errors?._errors} id={name} label={label}>
            <div
                ref={fieldRef}
                className={`${
                    parentName
                        ? ''
                        : 'tw-border tw-border-solid tw-px-4 tw-py-5 tw-rounded tw-shadow-sm'
                } ${className}`.trim()}
            >
                {help && <HelpText className="tw-mb-6" help={help} />}
                <DndContext
                    sensors={sensors}
                    collisionDetection={closestCenter}
                    onDragStart={handleDragStart}
                    onDragEnd={handleDragEnd}
                >
                    <SortableContext items={ids} strategy={verticalListSortingStrategy}>
                        {ids.map((id, idx) => (
                            <GroupItemField
                                key={id}
                                id={id}
                                idx={idx}
                                value={initValue.get(id)}
                                fields={fields}
                                errors={errors ? errors[idx] : undefined}
                                opened={isDragStart ? false : opened}
                                initOpen={initOpen}
                                onRemove={handleRemoveItem}
                                titleFrom={titleFrom}
                                errorId={`${errorId}.${idx}`}
                                onDeleteError={onDeleteError}
                                parentName={parentName}
                            />
                        ))}
                    </SortableContext>
                </DndContext>
                <button
                    type="button"
                    className="tw-w-full tw-h-auto tw-p-4 tw-border-2 tw-flex tw-justify-center tw-items-center tw-border-dashed  tw-border-gray-300
                     tw-text-gray-500 tw-text-center active:tw-text-sky-300 active:tw-border-sky-300
                    tw-cursor-pointer"
                    onClick={handleAddItem}
                >
                    {addText || (
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                            className="tw-size-6"
                        >
                            <path
                                fillRule="evenodd"
                                d="M12 3.75a.75.75 0 0 1 .75.75v6.75h6.75a.75.75 0 0 1 0 1.5h-6.75v6.75a.75.75 0 0 1-1.5 0v-6.75H4.5a.75.75 0 0 1 0-1.5h6.75V4.5a.75.75 0 0 1 .75-.75Z"
                                clipRule="evenodd"
                            />
                        </svg>
                    )}
                </button>
            </div>
        </BaseField>
    );
};
