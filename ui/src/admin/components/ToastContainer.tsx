import { useEffect, useState } from 'react';
import { Snackbar } from '@wordpress/components';
import { SnackbarProps } from '@wordpress/components/build-types/snackbar/types';

const typeInfo = {
    success: {
        className: 'components-notice is-success',
        icon: 'warning',
        iconColor: 'blue',
    },
    warning: {
        className: 'components-notice is-warning',
        icon: 'warning',
        iconColor: 'blue',
    },
    error: {
        className: 'components-notice is-error',
        icon: 'info',
        iconColor: 'blue',
    },
    info: {
        className: 'components-notice is-info',
        icon: 'info',
        iconColor: 'blue',
    },
};

export type ToastProps = Partial<SnackbarProps> & {
    id?: string;
    message: string;
    duration: number;
    type: keyof typeof typeInfo;
};

const fillToast = (toast: ToastProps, idx: number) => {
    toast.id ||= 'toast' + idx;
    toast.children = toast.message;
    toast.politeness = toast.type === 'error' ? 'assertive' : 'polite';
    //toast.className = toast.className || typeInfo[toast.type].className;
    // toast.icon = <Icon icon={typeInfo[toast.type].icon as any} />;
    return toast;
};

export default ({ toasts: _toasts = [] }: { toasts: ToastProps[] }) => {
    const [toasts, setToasts] = useState<ToastProps[]>(_toasts.map(fillToast));

    useEffect(() => {
        const handleShowToast = (e: Event & { detail?: ToastProps }) => {
            if (e.detail != null) {
                setToasts((t) => [...t, fillToast(e.detail as ToastProps, t.length)]);
            }
        };
        document.body.addEventListener('show-toast', handleShowToast);
    }, []);

    if (!toasts) {
        return null;
    }

    const removeToast = (id: string) => {
        setToasts((prevToasts) => prevToasts.filter((toast) => toast.id !== id));
    };

    return (
        <div
            id="toast-container"
            style={{ position: 'fixed', bottom: '1rem', right: '1rem', zIndex: 999999 }}
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: '5px' }}>
                {toasts.map((toast) => {
                    const timer = setTimeout(() => removeToast(toast.id as string), toast.duration);
                    return (
                        <Snackbar
                            key={toast.id}
                            onDismiss={function noRefCheck() {}}
                            onRemove={() => (removeToast(toast.id as string), clearTimeout(timer))}
                            // explicitDismiss={true}
                            {...(toast as any)}
                        />
                    );
                })}
            </div>
        </div>
    );
};
