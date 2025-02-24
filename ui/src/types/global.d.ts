var wp: WP.WPInstance;

namespace WP {
    interface WPInstance {
        media: {
            (Options: Media.Options): Media.Frame;
            query: (args?: { [k: string]: any }) => {
                filters: object;
                length: number;
                models: Media.Model[];
                more: () => Promise<undefined>;
            };
            attachment: (id: number | string) => Media.Model;
        };
        i18n?: {
            __: (text: string, domain = 'default') => string;
            _n: (single: string, plural: string, number: string, domain = 'default') => string;
            _nx: (
                single: string,
                plural: string,
                number: string,
                context: string,
                domain = 'default'
            ) => string;
            _x: (text: string, context: string, domain = 'default') => string;
        };
        [k: string]: any;
    }

    namespace Media {
        type MediaType = 'image' | 'audio' | 'video' | 'application' | 'text' | 'file' | string;
        type MediaOrderBy =
            | 'date'
            | 'title'
            | 'filename'
            | 'menuOrder'
            | 'uploadedTo'
            | 'id'
            | 'author'
            | 'modified'
            | 'mime_type'
            | 'status'
            | string;

        // 会变为 ajax action `query-attachments` 的查询参数
        interface Library {
            query?: boolean;
            type?: MediaType | MediaType[];
            author?: number; // 作者ID
            uploadedTo?: number; //文章ID
            [k: string]: any;
        }

        interface Options {
            title?: string;
            button?: {
                text?: string;
            };
            library?: Library;
            multiple?: boolean;
            media?: any;
            modal?: boolean;
            uploader?: boolean;
            state?: string;
            selection?: any[];
        }

        interface Frame {
            // 测试时调用close方法才触发close事件，手动关闭和选择均触发escape事件
            on(event: 'select' | 'open' | 'close' | 'escape', callback: () => void): this;
            once(event: 'select' | 'open' | 'close' | 'escape', callback: () => void): this;
            open(): void;
            close(): void;
            state(): State;
        }

        interface State {
            get(prop: 'selection'): Selection;
        }

        interface Selection {
            length: number;
            models: Model[];
            multiple: boolean;
            first(): Model;
            map<T>(callback: (s: Model) => T): T[];
        }

        interface Model {
            id: number;
            cid: string;
            attributes: WPMediaAttributes;
            changed: WPMediaAttributes;
            collection: object;
            get<K extends keyof WPMediaAttributes>(prop: K): WPMediaAttributes[K];
        }

        type WPMediaAttributes =
            | ImageAttachment
            | VideoAttachment
            | AudioAttachment
            | FileAttachment;

        interface Attachment {
            id: number;
            title: string;
            filename: string;
            url: string;
            link: string;
            alt: string;
            author: string;
            description: string;
            caption: string;
            name: string;
            status: string;
            uploadedTo: number;
            date: string;
            modified: string;
            menuOrder: number;
            mime: string;
            type: string;
            subtype: string;
            icon: string;
            dateFormatted: string;
            nonces: {
                update: string;
                delete: string;
                edit: string;
            };
            editLink: string;
            authorName: string;
            authorLink: string;
            filesizeInBytes: number;
            filesizeHumanReadable: string;
            context: string;
            compat: {
                item: string;
                meta: string;
            };
        }

        interface ImageAttachment extends Attachment {
            type: 'image';
            mime: `image/${string}`;
            height: number;
            width: number;
            orientation: 'landscape' | 'portrait';
            sizes: {
                [K in string]: {
                    url: string;
                    height: number;
                    width: number;
                    orientation: 'landscape' | 'portrait';
                };
            };
            meta: false;
        }

        interface VideoAttachment extends Attachment {
            type: 'video';
            mime: `video/${string}`;
            height: number;
            width: number;
            fileLength: string;
            fileLengthHumanReadable: string;
            meta: {
                artist: false;
                album: false;
                bitrate: number;
                bitrate_mode: false;
            };
            image: {
                src: string;
                width: number;
                height: number;
            };
            thumb: {
                src: string;
                width: number;
                height: number;
            };
        }

        interface AudioAttachment extends Attachment {
            type: 'audio';
            mime: `audio/${string}`;
            uploading: boolean;
            fileLength: string;
            fileLengthHumanReadable: string;
            meta: {
                artist: false;
                album: false;
                bitrate: number;
                bitrate_mode: string;
            };
            image: {
                src: string;
                width: number;
                height: number;
            };
            thumb: {
                src: string;
                width: number;
                height: number;
            };
            artist: string;
            album: string;
        }

        interface FileAttachment extends Attachment {
            type: 'application';
            mime: `application/${string}`;
            meta: false;
        }
    }
}
