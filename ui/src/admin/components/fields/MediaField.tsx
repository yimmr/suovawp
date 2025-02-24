import { Button } from '@wordpress/components';
import BaseField from './BaseField';
import type { MediaFieldProps } from './types';
import useMediaUpload from '../../hooks/useMediaUpload';
import type { MediaAttachment } from '../../hooks/useMediaUpload';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useFormReset } from '../../hooks/useFormReset';
import MediaCards from './MediaCards';

export type Attachment = MediaAttachment;

function convertMediaType(type?: string | string[]) {
    if (!type) return type;
    if (Array.isArray(type)) {
        return type.map((t) => (t === 'file' ? 'application' : t));
    } else if (type === 'file') {
        return 'application';
    }
    return type;
}

export default function ({
    errors,
    label,
    name,
    help,
    className,
    value,
    buttonText = '上传',
    accept = 'image',
    multiple = false,
    query = {},
    modalTitle = '',
    modalButtonText,
    cards = {},
}: MediaFieldProps) {
    const [attachments, setAttachments] = useState<Attachment[]>([]);
    const isReplaceRef = useRef<number>(0);
    const [initAttachments, setInitAttachments] = useState<MediaAttachment[]>([]);

    const media = useMediaUpload({
        options: {
            title: modalTitle || label,
            button: { text: modalButtonText },
            library: { type: convertMediaType(accept), ...query },
            multiple,
        },
    });

    useEffect(() => {
        if (value && (!Array.isArray(value) || value.length > 0)) {
            media.query(value).then((a) => {
                setAttachments(a);
                setInitAttachments(a);
            });
        }
    }, [value]);

    const handleReset = useCallback(() => {
        setAttachments(initAttachments);
    }, [media]);
    const fieldRef = useFormReset<HTMLDivElement>(handleReset);

    useEffect(() => {
        if (media.selected) {
            const selected = media.selected;
            setAttachments((a) => {
                const targetId = isReplaceRef.current;
                if (targetId) {
                    const target = selected[0];
                    if (target) {
                        return a.length > 0
                            ? a.map((m) => (m.id === targetId ? target : m))
                            : [target];
                    }
                    return a.filter(({ id }) => id !== targetId);
                }
                if (!multiple && a.length > 0) {
                    return a;
                }
                return [...a, ...selected];
            });
        }
    }, [media.selected, multiple]);

    const handleRemove = useCallback((id: number) => {
        setAttachments((a) => a.filter((a) => a.id !== id));
    }, []);

    const handleReplace = useCallback(
        (id: number) => {
            isReplaceRef.current = id;
            media.open();
        },
        [media]
    );

    const handleUpload = useCallback(() => media.open(), [media]);

    if (media.isUndefined()) {
        const errors = ['Media library not loaded!'];
        return <BaseField errors={errors} id={name} label={label} help={help}></BaseField>;
    }

    return (
        <BaseField errors={errors} id={name} label={label} help={help}>
            <div ref={fieldRef} className={className}>
                <MediaCards
                    attachments={attachments}
                    onReplace={handleReplace}
                    onRemove={handleRemove}
                    name={name}
                    {...cards}
                />
                {(multiple || attachments.length <= 0) && (
                    <Button variant="primary" size="compact" onClick={handleUpload}>
                        {buttonText}
                    </Button>
                )}
            </div>
        </BaseField>
    );
}
