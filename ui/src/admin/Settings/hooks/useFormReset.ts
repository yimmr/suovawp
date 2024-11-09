import { useEffect, useRef } from 'react';

export type formResetCallabck = (form?: HTMLFormElement) => void;

const resetCallbacks = new Map<HTMLFormElement, Set<formResetCallabck>>();
const removeCallbacks = new Map<HTMLFormElement, () => void>();

function setupFormListenerIf(form: HTMLFormElement) {
    if (resetCallbacks.has(form)) return;
    removeCallbacks.set(form, setupFormListener(form));
}

function setupFormListener(form: HTMLFormElement) {
    resetCallbacks.set(form, new Set());
    const handler = () => {
        const callbacks = resetCallbacks.get(form);
        callbacks?.forEach((cb) => cb(form));
    };
    form.addEventListener('reset', handler);
    return () => {
        form.removeEventListener('reset', handler);
    };
}

/**
 * 用法：把钩子返回的ref关联到一元素，当顶级表单触发reset事件时自动执行指定的回调
 */
export function useFormReset<T extends Element>(callback: formResetCallabck) {
    const elementRef = useRef<T>(null);
    useEffect(() => {
        const element = elementRef.current;
        if (!element) return;
        const form = element.closest('form');
        if (!form) return;
        setupFormListenerIf(form);
        const callbacks = resetCallbacks.get(form)!;
        callbacks.add(callback);
        return () => {
            callbacks.delete(callback);
            if (callbacks.size === 0) {
                resetCallbacks.delete(form);
                removeCallbacks.get(form)?.();
                removeCallbacks.delete(form);
            }
        };
    }, [callback]);
    return elementRef;
}
