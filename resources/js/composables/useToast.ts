import { useToast as useVueToastification } from 'vue-toastification';

export const useToast = () => {
    const toast = useVueToastification();

    const success = (message: string, options?: any) => {
        toast.success(message, {
            icon: '✅',
            ...options,
        });
    };

    const error = (message: string, options?: any) => {
        toast.error(message, {
            icon: '❌',
            ...options,
        });
    };

    const warning = (message: string, options?: any) => {
        toast.warning(message, {
            icon: '⚠️',
            ...options,
        });
    };

    const info = (message: string, options?: any) => {
        toast.info(message, {
            icon: 'ℹ️',
            ...options,
        });
    };

    return {
        success,
        error,
        warning,
        info,
        toast,
    };
};
