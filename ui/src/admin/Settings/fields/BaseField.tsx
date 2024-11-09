import React from 'react';
import type { BaseFieldProps } from './types';
import { ErrorList } from './ErrorList';
import { BaseControl } from '@wordpress/components';

type BaseProps = React.PropsWithChildren<
    Partial<Pick<BaseFieldProps, 'errors' | 'label' | 'help' | 'id' | 'className'>> & {
        fieldRef?: React.Ref<HTMLDivElement>;
    }
>;

export default function ({ errors, children, label, help, id, fieldRef, className }: BaseProps) {
    const clsn = className ? ' ' + className : '';
    if (label) {
        return (
            <BaseControl
                as="label"
                className={`[&_label]:!tw-text-sm [&_label]:!tw-font-bold tw-mb-7${clsn}`}
                help={help}
                label={label}
                id={id}
                __nextHasNoMarginBottom
            >
                {children}
                <ErrorList errors={errors} />
                <div ref={fieldRef}></div>
            </BaseControl>
        );
    }
    return (
        <div
            ref={fieldRef}
            className={`[&_label]:!tw-text-sm [&_label]:!tw-font-bold tw-mb-6${clsn}`}
        >
            {children}
            <ErrorList errors={errors} />
        </div>
    );
}
