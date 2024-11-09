import ToastContainer, { ToastProps } from './ToastContainer';
import { createRoot } from 'react-dom/client';

export const showToast = (
    message: Omit<ToastProps, 'id'> | string,
    type: ToastProps['type'] = 'success',
    duration = 3000
) => {
    let data: ToastProps;
    if (typeof message === 'string') {
        data = { message, type, duration };
    } else {
        data = message;
        data.type ??= type;
        data.duration ??= duration;
    }
    let container = document.querySelector('#toast-container');
    if (container == null) {
        container = document.createElement('div');
        document.body.appendChild(container);
        createRoot(container).render(<ToastContainer toasts={[data]} />);
    } else {
        document.body.dispatchEvent(new CustomEvent('show-toast', { detail: data }));
    }
};

export default function (props: ToastProps) {
    showToast(props);
    return null;
}
