import { cva, type VariantProps } from 'class-variance-authority';

export { default as Button } from './Button.vue';

export const buttonVariants = cva(
    'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-full text-[15px] font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0',
    {
        variants: {
            variant: {
                default: 'border border-primary bg-white text-primary hover:border-amber-400 hover:bg-amber-400 hover:text-white shadow-xs dark:bg-card dark:border-white/30 dark:text-white',
                destructive: 'border border-rose-600 bg-white text-rose-700 hover:border-amber-400 hover:bg-amber-400 hover:text-white shadow-xs dark:bg-card dark:border-rose-500/50 dark:text-white',
                outline: 'border border-border bg-white text-foreground hover:border-amber-400 hover:bg-amber-400 hover:text-white shadow-xs dark:bg-card dark:border-white/30 dark:text-white',
                secondary: 'border border-border bg-white text-foreground hover:border-amber-400 hover:bg-amber-400 hover:text-white shadow-xs dark:bg-card dark:border-white/30 dark:text-white',
                ghost: 'hover:border-amber-400 hover:bg-amber-400 hover:text-white',
                link: 'text-primary underline-offset-4 hover:underline',
            },
            size: {
                default: 'h-11 px-[21px] py-[11px]',
                sm: 'h-9 px-4 text-sm',
                lg: 'h-12 px-7 text-base',
                icon: 'h-10 w-10',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    },
);

export type ButtonVariants = VariantProps<typeof buttonVariants>;
