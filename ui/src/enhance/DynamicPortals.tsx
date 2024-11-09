import { useEffect, useMemo, useRef, useState } from 'react';
import ReactDOM from 'react-dom';

export interface DynamicPortalsProps {
    components?: Record<string, React.ComponentType<any>>;
    body?: Element | DocumentFragment | null;
    flag?: string;
}

type Portal = {
    container: Element | DocumentFragment;
    key?: string | null | undefined;
    Component: () => JSX.Element;
};

export default function DynamicPortals({
    components = {},
    body,
    flag = 'data-react-portal',
}: DynamicPortalsProps) {
    const [portals, setPortals] = useState<Portal[]>([]);
    const wrapRef = useRef<any>(null);
    const wrap = useMemo(() => resolveWrapElement(body), [body]);
    useEffect(() => {
        if (wrapRef.current == wrap) return;
        wrapRef.current = wrap;
        const elements = wrap.querySelectorAll<HTMLElement>(`[${flag}]`);
        if (elements.length <= 0) return;
        const newPortals = [];
        for (const element of elements) {
            const componentName = element.getAttribute(flag);
            if (!componentName || !(componentName in components)) {
                continue;
            }
            if (portals.findIndex(({ container }) => container === element) !== -1) {
                continue;
            }
            element.removeAttribute(flag);
            const Component = components[componentName];
            const props = element.dataset;
            newPortals.push({
                key: componentName,
                container: element,
                Component: () => <Component {...props} $el={element} />,
            });
        }
        setPortals(newPortals);
    }, [wrap]);
    return portals.map(({ container, key, Component }) =>
        ReactDOM.createPortal(<Component />, container, key)
    );
}

function resolveWrapElement(el: DynamicPortalsProps['body']) {
    if (!el) return document.body;
    if (el instanceof Element || el instanceof DocumentFragment) {
        return el;
    }
    return document.querySelector(el as string) || document.body;
}
