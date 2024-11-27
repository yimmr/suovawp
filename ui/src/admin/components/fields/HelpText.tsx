import React from 'react';

interface HelpTextProps {
    children?: React.ReactNode;
    content?: string | React.ReactNode | string[];
    title?: string;
    linkHref?: string;
    linkText?: string;
    className?: string;
    html?: boolean;
    list?: 'decimal' | 'disc' | 'none';
    help?: string | HelpTextProps | React.ReactNode;
}

export default function ({ help, ...props }: HelpTextProps) {
    if (typeof help === 'string' || React.isValidElement(help)) {
        props = { ...props, content: help };
    } else if (typeof help === 'object') {
        props = { ...props, ...help };
    }
    const { children, title, linkHref, linkText, content, className, html, list } = props;
    return (
        <div
            className={`tw-p-4 tw-bg-blue-50 tw-rounded-lg tw-border tw-border-blue-200 tw-shadow-sm${
                className ? ' ' + className : ''
            }`}
        >
            <div className="tw-flex tw-items-start">
                <div className="tw-flex-shrink-0">
                    <svg
                        className="tw-w-5 tw-h-5 tw-text-blue-600"
                        fill="currentColor"
                        viewBox="0 0 20 20"
                    >
                        <path
                            fillRule="evenodd"
                            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                            clipRule="evenodd"
                        />
                    </svg>
                </div>
                <div className="tw-ml-3">
                    {title && (
                        <h3 className="tw-text-sm tw-font-medium tw-text-blue-800 tw-mb-2">
                            {title}
                        </h3>
                    )}
                    <div className="tw-text-sm tw-text-blue-700">
                        <HelpContent html={html} list={list}>
                            {content}
                        </HelpContent>
                        {children}
                    </div>
                    {linkHref && (
                        <div className="tw-mt-3">
                            <a
                                href={linkHref}
                                className="tw-text-sm tw-font-medium tw-text-blue-600 hover:tw-text-blue-500 tw-transition-colors"
                            >
                                {linkText || '了解更多 →'}
                            </a>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

function HelpContent({
    children,
    html = false,
    list = 'decimal',
}: {
    children: HelpTextProps['content'];
    list?: HelpTextProps['list'];
    html?: boolean;
}) {
    if (typeof children === 'string') {
        return html ? <div dangerouslySetInnerHTML={{ __html: children }}></div> : children;
    }
    if (Array.isArray(children)) {
        return (
            <ul className={`tw-list-${list}`}>
                {children.map((item, idx) =>
                    html ? (
                        <li key={idx} dangerouslySetInnerHTML={{ __html: item }}></li>
                    ) : (
                        <li key={idx}>{item}</li>
                    )
                )}
            </ul>
        );
    }
    return children;
}
