import Toast, { type PluginOptions, POSITION } from 'vue-toastification';
import 'vue-toastification/dist/index.css';

const toastOptions: PluginOptions = {
    timeout: 5000,
    position: POSITION.TOP_RIGHT,
    closeOnClick: true,
    pauseOnFocusLoss: true,
    pauseOnHover: true,
    draggable: true,
    draggablePercent: 0.6,
    showCloseButtonOnHover: false,
    hideProgressBar: false,
    closeButton: 'button',
    icon: true,
    rtl: false,
    toastClassName:
        'v-toast rounded-lg shadow-lg backdrop-blur-sm border border-neutral-200/60 dark:border-neutral-700/60 bg-white/90 dark:bg-neutral-800/90 text-neutral-900 dark:text-white',
    bodyClassName: 'flex items-center gap-3 p-4',
};

export { toastOptions };
export default Toast;
