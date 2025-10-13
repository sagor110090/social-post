import { cva, type VariantProps } from 'class-variance-authority'

export { default as Button } from './Button.vue'

export const buttonVariants = cva(
  'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-xl text-sm font-semibold transition-all duration-300 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*=\'size-\'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive relative overflow-hidden group',
  {
    variants: {
      variant: {
        default:
          'bg-gradient-to-r from-brand-primary to-brand-primary-dark text-white shadow-lg hover:shadow-xl hover:scale-105 hover:from-brand-primary-light hover:to-brand-primary before:absolute before:inset-0 before:bg-gradient-to-r before:from-transparent before:via-white/20 before:to-transparent before:translate-x-[-100%] before:transition-transform before:duration-500 hover:before:translate-x-[100%]',
        destructive:
          'bg-gradient-to-r from-red-500 to-red-600 text-white shadow-lg hover:shadow-xl hover:scale-105 hover:from-red-600 hover:to-red-700 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40',
        outline:
          'border-2 border-brand-primary/60 bg-white/50 text-brand-primary shadow-md hover:bg-brand-primary hover:text-white hover:scale-105 hover:shadow-lg backdrop-blur-sm dark:bg-neutral-800/50 dark:border-brand-primary/80 dark:hover:bg-brand-primary',
        secondary:
          'bg-gradient-to-r from-neutral-100 to-neutral-200 text-neutral-700 shadow-md hover:shadow-lg hover:scale-105 hover:from-neutral-200 hover:to-neutral-300 dark:from-neutral-800 dark:to-neutral-700 dark:text-neutral-300',
        ghost:
          'hover:bg-neutral-100/80 hover:text-neutral-900 hover:scale-105 dark:hover:bg-neutral-800/80 dark:hover:text-neutral-100 rounded-xl',
        link: 'text-brand-primary underline-offset-4 hover:underline font-semibold hover:text-brand-primary-dark',
      },
      size: {
        default: 'h-10 px-6 py-3 has-[>svg]:px-5 text-base',
        sm: 'h-8 rounded-lg gap-2 px-4 has-[>svg]:px-3 text-sm',
        lg: 'h-12 rounded-xl px-8 py-4 has-[>svg]:px-6 text-lg',
        icon: 'size-10 rounded-xl',
      },
    },
    defaultVariants: {
      variant: 'default',
      size: 'default',
    },
  },
)

export type ButtonVariants = VariantProps<typeof buttonVariants>
