import { useCallback, useRef, useState } from 'react';
import { Button } from '@wordpress/components';
import { showToast } from './Toast';
import FormContent from './FormContent';
import type { FormContentProps } from './FormContent';

export interface SettingsFormProps {
    fields: FormContentProps['fields'];
    data?: { [k: string]: any };
    _wpnonce: string;
    onSendBefore?: (formData: FormData) => void;
    customText?: {
        btns?: {
            save?: { label?: string; tooltip?: string };
            undo?: { label?: string; tooltip?: string };
            reset?: { label?: string; tooltip?: string };
        };
    };
}

const defaultText = {
    btns: {
        save: { label: '保存', tooltip: '恢复到默认值' },
        undo: { label: '撤销', tooltip: '撤销未保存的更改' },
        reset: { label: '重置', tooltip: '重置' },
    },
};

const mergeText = (customText?: SettingsFormProps['customText']): typeof defaultText => {
    if (!customText) return defaultText;
    const merge = (target: any, source: any): any => {
        Object.keys(source).forEach((key) => {
            if (source[key] instanceof Object && key in target) {
                target[key] = merge({ ...target[key] }, source[key]);
            } else {
                target[key] = source[key];
            }
        });
        return target;
    };
    return merge({ ...defaultText }, customText);
};

export default function ({ fields, data, _wpnonce, onSendBefore, customText }: SettingsFormProps) {
    const actionRef = useRef('');
    const formRef = useRef<HTMLFormElement>(null);
    const [errors, setErrors] = useState<FormContentProps['errors']>({} as any);
    // const [hasError, setHasError] = useState(false);
    const formText = mergeText(customText);

    const deleteError = useCallback((id: string) => {
        setErrors((err) => {
            if (!err) return err;

            const keys = id.split('.');
            const lastKey = keys.pop();
            let current = err;
            // 如果keys为空，current是当前对象
            for (const key of keys) {
                if (typeof current[key] !== 'object' || !(key in current)) {
                    return err;
                }
                current = current[key];
            }
            if (lastKey && lastKey in current) {
                delete current[lastKey];
                return { ...err };
            }
            return err;
        });
    }, []);

    const submitForm = useCallback(
        async (submitType: string) => {
            if (!submitType || formRef.current == null) {
                return;
            }
            const formData = new FormData(formRef.current);
            formData.set('submit_type', submitType);
            formData.set('_wpnonce', _wpnonce);
            if (onSendBefore) {
                onSendBefore(formData);
            }
            const res = await fetch(window.location.href, {
                method: 'POST',
                headers: {
                    // 'Content-Type': 'application/x-www-form-urlencoded',
                    accept: 'application/json',
                },
                body: formData,
            });
            const resData = await res.json();
            if (!res.ok) {
                // setHasError(true);
                setErrors(resData);
                const msgarr = [res.statusText];
                msgarr.map((msg) => showToast({ message: msg, type: 'error', duration: 3000 }));
            } else {
                // setHasError(false);
                setErrors({} as any);
                showToast({
                    message: resData.message || res.statusText,
                    type: 'success',
                    duration: 3000,
                });
                window.location.reload();
            }
        },
        [_wpnonce, onSendBefore]
    );

    const handleFormAction = useCallback(
        async (e: React.MouseEvent<HTMLButtonElement>) => {
            if (actionRef.current || formRef.current == null) return;
            const newAction = e.currentTarget.value;
            actionRef.current = newAction;
            switch (newAction) {
                case 'undo':
                    formRef.current.reset();
                    break;
                default:
                    await submitForm(newAction);
                    break;
            }
            actionRef.current = '';
        },
        [submitForm]
    );

    return (
        <form ref={formRef} onSubmit={(e) => e.preventDefault()} method="POST">
            <FormContent fields={fields} data={data} errors={errors} onDeleteError={deleteError} />
            <footer className="tw-mt-10 tw-pb-10">
                <div className="tw-flex tw-gap-4 tw-justify-center md:tw-w-1/2">
                    <Button
                        variant="primary"
                        size="compact"
                        isBusy={actionRef.current === 'save'}
                        value="save"
                        // label={formText.btns.save.tooltip}
                        onClick={handleFormAction}
                    >
                        {formText.btns.save.label}
                    </Button>
                    <Button
                        type="button"
                        variant="secondary"
                        size="compact"
                        showTooltip={true}
                        label={formText.btns.undo.tooltip}
                        isBusy={actionRef.current === 'undo'}
                        value="undo"
                        onClick={handleFormAction}
                    >
                        {formText.btns.undo.label}
                    </Button>
                    <Button
                        type="button"
                        variant="secondary"
                        isDestructive={true}
                        size="compact"
                        label={formText.btns.reset.tooltip}
                        showTooltip={true}
                        isBusy={actionRef.current === 'reset'}
                        value="reset"
                        onClick={handleFormAction}
                    >
                        {formText.btns.reset.label}
                    </Button>
                </div>
            </footer>
        </form>
    );
}
