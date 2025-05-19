import { useMemo } from 'react';
import { generateGridColsWithPreset } from '../utils';
import type { MediaCards } from './types2';
import { Attachment } from './MediaField';

export const CARDS_LAYOUTS = {
    single: {
        default: 1,
    },
    normal: {
        default: 1,
        sm: 2,
        md: 3,
        lg: 4,
    },
    compact: {
        default: 2,
        sm: 3,
        md: 4,
        lg: 6,
    },
    loose: {
        default: 1,
        sm: 2,
        md: 2,
        lg: 3,
    },
    icon: {
        default: 4,
        sm: 6,
        md: 8,
        lg: 10,
    },
} as const;

interface CardsProps extends MediaCards {
    attachments: Attachment[];
    onReplace?: (id: number) => void;
    onRemove?: (id: number) => void;
    name?: string;
    style?: React.CSSProperties;
    className?: string;
}

export default function ({
    attachments,
    onRemove,
    onReplace,
    name,
    layout = 'normal',
    showTitle = false,
    gap = 4,
    replaceText = '替换',
    removeText = '删除',
    className = '',
    style,
    ratio,
    objectFit = 'cover',
}: CardsProps) {
    if (typeof layout === 'string') {
        ratio ||= layout === 'icon' ? 'square' : 'video';
    }
    const cols = useMemo(() => generateGridColsWithPreset(CARDS_LAYOUTS, layout), [layout]);
    let cardStyle: React.CSSProperties | undefined;
    ratio ||= 'video';
    const map: { [key: string]: string } = { '16/9': 'video', '1/1': 'square' };
    ratio = ratio in map ? map[ratio] : ratio;
    if (!['auto', 'video', 'square'].includes(ratio)) {
        cardStyle = { aspectRatio: ratio };
    }
    return (
        <div className={`tw-grid tw-gap-${gap} ${cols} tw-mb-4 ${className}`.trim()} style={style}>
            {attachments.map(({ type, url, id, alt, title }, i) => {
                return (
                    <div
                        key={id + '_' + i}
                        className="tw-relative tw-group tw-border tw-border-solid tw-border-gray-300/50 tw-rounded-lg tw-overflow-hidden"
                    >
                        <div
                            className={`tw-bg-black/75 tw-leading-[0] tw-aspect-${ratio}`}
                            style={cardStyle}
                        >
                            <AttachmentCard {...{ url, type, id, alt, title, objectFit }} />
                        </div>
                        <div
                            className={`tw-overflow-hidden tw-relative tw-bg-white *:tw-text-xs tw-p-3 tw-flex tw-items-center tw-justify-center${
                                showTitle ? ' md:tw-justify-end' : ''
                            } tw-gap-2 tw-whitespace-nowrap`}
                        >
                            {showTitle && (
                                <div className="tw-flex-1 tw-hidden md:tw-block tw-duration-75 tw-w-1/2 tw-h-full tw-mr-auto tw-bg-white">
                                    <p className="tw-duration-75 tw-truncate">{alt || title}</p>
                                </div>
                            )}
                            <button
                                type="button"
                                className="tw-cursor-pointer tw-bg-blue-500 tw-text-white tw-px-3 tw-py-1 tw-rounded hover:tw-bg-blue-600"
                                onClick={() => onReplace?.(id)}
                            >
                                {replaceText}
                            </button>
                            <button
                                type="button"
                                className="tw-bg-red-500 tw-text-white tw-px-3 tw-py-1 tw-rounded hover:tw-bg-red-600"
                                onClick={() => onRemove?.(id)}
                            >
                                {removeText}
                            </button>
                        </div>
                        <input type="hidden" name={name} value={id} />
                    </div>
                );
            })}
        </div>
    );
}

type AttachmentCardProps = Pick<Attachment, 'url' | 'type' | 'alt' | 'title'> & {
    objectFit?: 'cover' | 'fill' | 'contain' | 'none' | 'scale-down';
};

function AttachmentCard({ url, type, alt, title, objectFit = 'cover' }: AttachmentCardProps) {
    switch (type) {
        case 'image':
            return (
                <img src={url} alt={alt} className={`tw-w-full tw-h-full tw-object-${objectFit}`} />
            );
        case 'audio':
            return (
                <div className="tw-w-full tw-h-full tw-p-3 tw-object-contain">
                    <audio
                        src={url}
                        className="tw-w-full tw-h-full"
                        controls
                        muted
                        autoPlay={false}
                    />
                </div>
            );
        case 'video':
            return (
                <video
                    className="tw-w-full tw-h-full tw-object-contain"
                    src={url}
                    controls
                    muted
                    autoPlay={false}
                />
            );
        default:
            return (
                <div className="tw-w-full tw-h-full tw-bg-white tw-border-b tw-p-3 tw-overflow-hidden">
                    <div className="tw-flex tw-items-center">
                        <span className="tw-text-amber-400 tw-h-8 tw-w-8 tw-max-h-full tw-max-w-full tw-mr-2">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="currentColor"
                                className="size-6"
                            >
                                <path d="M5.625 1.5c-1.036 0-1.875.84-1.875 1.875v17.25c0 1.035.84 1.875 1.875 1.875h12.75c1.035 0 1.875-.84 1.875-1.875V12.75A3.75 3.75 0 0 0 16.5 9h-1.875a1.875 1.875 0 0 1-1.875-1.875V5.25A3.75 3.75 0 0 0 9 1.5H5.625Z" />
                                <path d="M12.971 1.816A5.23 5.23 0 0 1 14.25 5.25v1.875c0 .207.168.375.375.375H16.5a5.23 5.23 0 0 1 3.434 1.279 9.768 9.768 0 0 0-6.963-6.963Z" />
                            </svg>
                        </span>
                        <h3 className="tw-font-bold tw-text-lg">{alt || title}</h3>
                    </div>
                    <p className="tw-mt-2">{url}</p>
                </div>
            );
    }
}
