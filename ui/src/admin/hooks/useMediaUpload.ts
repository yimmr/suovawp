import { useCallback, useEffect, useState } from 'react';

type MediaOptions = Parameters<NonNullable<typeof wp.media>>[0];
type MediaFrame = ReturnType<NonNullable<typeof wp.media>>;
type MediaSelection = ReturnType<ReturnType<MediaFrame['state']>['get']>;
export type MediaAttachment = ReturnType<MediaSelection['first']>['attributes'];

interface MediaUploadProps {
    options: MediaOptions;
    onInitFailed?: (error: string) => void;
    cache?: boolean;
}

const isWPMediaUndefined = () => typeof wp === 'undefined' || !wp.media;

export default function ({ options, onInitFailed, cache = true }: MediaUploadProps) {
    const [mediaFrame, setMediaFrame] = useState<MediaFrame>();
    const [selected, setSelected] = useState<MediaAttachment[]>([]);
    const [cached, setCached] = useState(false);

    useEffect(() => {
        return () => {
            cached && deleteMediaFrame(options);
        };
    }, [cached]);

    const getMediaFrameOrLoad = useCallback(() => {
        if (mediaFrame) return mediaFrame;
        const frame = getMediaFrame(options, cache);
        setMediaFrame(frame);
        cache && setCached(true);
        return frame;
    }, [mediaFrame, options, onInitFailed, cache]);

    const handleOpen = useCallback(() => {
        const frame = getMediaFrameOrLoad();
        if (frame == null) {
            const error =
                'wp.media 未加载。请确保已经调用 wp_enqueue_media() 并正确引入 wp.media 脚本。';
            onInitFailed ? onInitFailed(error) : console.error(error);
            return;
        }
        frame.on('select', function () {
            const selection = frame.state().get('selection');
            setSelected(selection.map((a) => a.attributes));
        });
        frame.open();
    }, [getMediaFrameOrLoad, onInitFailed]);

    return {
        frame: mediaFrame,
        selected,
        isUndefined: isWPMediaUndefined,
        open: handleOpen,
        close: () => mediaFrame?.close(),
        reset: () => setMediaFrame(undefined),
        query,
    };
}

const queryCache = new Map<number, MediaAttachment>();

async function query(id: number | number[]) {
    if (isWPMediaUndefined()) return [];
    const ids = Array.isArray(id) ? id : [id];
    const cacheItems: MediaAttachment[] = [];
    const qryIds: number[] = [];
    for (const id of ids) {
        const cached = queryCache.get(id);
        if (cached) {
            cacheItems.push(cached);
        } else {
            qryIds.push(id);
        }
    }
    if (qryIds.length === 0) return cacheItems;
    const query = wp.media.query({
        post__in: qryIds,
        posts_per_page: -1,
        orderby: 'post__in',
    });
    await query.more();
    for (const id of qryIds) {
        const attachment = wp.media.attachment(id);
        queryCache.set(id, attachment.attributes);
    }
    return ids.map((id) => queryCache.get(id)).filter((a) => !!a);
}

const mediaFrameCache = new Map<string, MediaFrame>();
const cacheKeyCount: Record<string, number> = {};

function getMediaFrame(options: MediaOptions, cache = true) {
    if (typeof wp === 'undefined' || !wp.media) return;
    if (!cache) wp.media({ ...options });
    const key = generateCacheKey(options);
    cacheKeyCount[key] ??= 0;
    cacheKeyCount[key] += 1;
    if (mediaFrameCache.has(key)) {
        return mediaFrameCache.get(key);
    }
    const frame = wp.media({ ...options });
    mediaFrameCache.set(key, frame);
    return frame;
}

function deleteMediaFrame(options: MediaOptions) {
    if (isWPMediaUndefined()) return;
    const key = generateCacheKey(options);
    cacheKeyCount[key] ??= 0;
    cacheKeyCount[key] -= 1;
    console.log(cacheKeyCount);
    if (cacheKeyCount[key] <= 0) {
        mediaFrameCache.delete(key);
    }
}

function generateCacheKey({ ...options }: MediaOptions) {
    const ignoreKeys = ['frame', 'media', 'state', 'selection'];
    ignoreKeys.forEach((k) => {
        delete (options as any)[k];
    });
    const sortedOptions = sortDeepObject(options);
    return JSON.stringify(sortedOptions);
}

function sortDeepObject(obj: { [k: string]: any }): any {
    if (obj == null || typeof obj !== 'object') {
        return obj;
    }
    if (Array.isArray(obj)) {
        return obj.map(sortDeepObject).sort();
    }
    const sortedKeys = Object.keys(obj).sort();
    const result: { [k: string]: any } = {};
    sortedKeys.forEach((key) => {
        result[key] = sortDeepObject(obj[key]);
    });
    return result;
}
