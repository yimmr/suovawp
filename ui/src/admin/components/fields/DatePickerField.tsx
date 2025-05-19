import { DateTimePicker } from '@wordpress/components';
import BaseField from './BaseField';
import type { DatePickerFieldProps } from './types2';
import useFormRestState from '../../hooks/useFormRestState';
import { getLocaleText, isValidDateTime } from '../utils';
import { useEffect } from 'react';

const dateTimelocaleText = (el?: Element | null) => {
    if (!el) return;
    const timeEl = el.querySelector('.components-datetime__time');
    if (timeEl != null) {
        const selecters =
            '.components-select-control__input option,.components-datetime__time-legend';
        timeEl.querySelectorAll(selecters).forEach((el) => {
            el.textContent = getLocaleText(el.textContent?.trim());
        });
    }
    // const dateEl = el.querySelector('.components-datetime__date');
    // if (dateEl != null) {
    //     const selecters = '.components-heading strong,&>div>div';
    //     dateEl.querySelectorAll(selecters).forEach((el) => {
    //         el.textContent = getLocaleText(el.textContent?.trim());
    //     });
    // }
};

export default function ({
    errors,
    value,
    type: _t,
    className,
    label,
    help,
    name,
    ...props
}: DatePickerFieldProps) {
    const [picked, setPicked, fieldRef] = useFormRestState<HTMLDivElement, typeof value>(value);
    if (!isValidDateTime(picked)) {
        const title = label || 'date-picker';
        const errors = [`[${title}]Invalid date value: "${picked}". (e.g. "2023-12-31T23:59:59")!`];
        return <BaseField errors={errors}></BaseField>;
    }
    useEffect(() => {
        if (!fieldRef.current) return;
        dateTimelocaleText(fieldRef.current);
    }, []);
    return (
        <BaseField errors={errors} id={name} label={label} help={help} className={className}>
            <div
                ref={fieldRef}
                className="tw-border tw-border-indigo-300/30 tw-p-4 tw-py-5 tw-rounded"
            >
                <DateTimePicker
                    startOfWeek={1}
                    dateOrder="ymd"
                    {...props}
                    currentDate={picked}
                    onChange={setPicked}
                />
            </div>
            <input type="hidden" name={name} defaultValue={picked} />
        </BaseField>
    );
}
