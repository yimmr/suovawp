import { PropsWithChildren, StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import DynamicPortals, { DynamicPortalsProps } from './DynamicPortals';
import { DataType, EnhancePageProvider } from './EnhancePageContext';

function App({
    children,
    initData,
    ...portalsProps
}: PropsWithChildren<{ initData?: DataType } & DynamicPortalsProps>) {
    return (
        <EnhancePageProvider initData={initData}>
            {children}
            <DynamicPortals {...portalsProps} />
        </EnhancePageProvider>
    );
}

interface EnhancePageOptions extends DynamicPortalsProps {
    el?: string | HTMLElement | null;
    dataEl?: string | HTMLElement | null;
    className?: React.HTMLAttributes<HTMLElement>['className'] | string[];
    autoRoot?: boolean;
}

export const mountEnhancePage = (children?: React.ReactNode, options: EnhancePageOptions = {}) => {
    const { el = '#root', dataEl, className, autoRoot, ...portalsProps } = options;
    const rootElement = resolveRootElement(el, autoRoot);
    if (rootElement == null) return;
    if (className) {
        const classNames = typeof className === 'string' ? className.split(' ') : className;
        rootElement.classList.add(...classNames);
    }
    const initData = loadInitData(rootElement, dataEl);
    const root = createRoot(rootElement);
    root.render(
        <StrictMode>
            <App {...{ children, initData, ...portalsProps }} />
        </StrictMode>
    );
};

function resolveRootElement(el: EnhancePageOptions['el'], autoRoot = true) {
    if (el instanceof HTMLElement) {
        return el;
    }
    const ele = typeof el === 'string' ? document.querySelector(el) : null;
    if (ele instanceof HTMLElement) return ele;
    if (!autoRoot) return null;
    const newRoot = document.createElement('div');
    newRoot.id = typeof el === 'string' ? el : '';
    document.body.appendChild(newRoot);
    return newRoot;
}

function loadInitData(root: HTMLElement, dataEl: EnhancePageOptions['dataEl']) {
    let jsonEl;
    if (dataEl instanceof HTMLElement) {
        jsonEl = dataEl;
    } else if (dataEl) {
        jsonEl = document.querySelector(dataEl);
    } else {
        jsonEl = root.querySelector('script[type="application/json"]');
    }
    if (jsonEl && jsonEl.textContent) {
        return JSON.parse(jsonEl.textContent);
    }
    return { ...root.dataset };
}
