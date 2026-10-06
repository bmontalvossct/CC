import { cva, type VariantProps } from 'class-variance-authority';

export { default as Button } from './Button.vue';

export const buttonVariants = cva(
    'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-xl text-[15px] font-medium ring-offset-background transition-[color,background-color,border-color,box-shadow,transform] duration-200 motion-safe:active:scale-[0.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0',
    {
        variants: {
            variant: {
                default: 'border border-primary bg-primary text-primary-foreground shadow-sm hover:bg-primary/90 hover:shadow-md',
                destructive: 'border border-rose-600 bg-rose-600 text-white shadow-sm hover:bg-rose-700',
                outline: 'border border-border bg-card text-foreground shadow-sm hover:border-primary/40 hover:bg-accent',
                secondary: 'border border-transparent bg-secondary text-secondary-foreground hover:bg-accent',
                ghost: 'hover:bg-accent hover:text-accent-foreground',
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
