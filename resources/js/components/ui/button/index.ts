import { cva, type VariantProps } from 'class-variance-authority';

export { default as Button } from './Button.vue';

export const buttonVariants = cva(
    'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-full text-[15px] font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0',
    {
        variants: {
            variant: {
                default: 'bg-primary text-primary-foreground hover:bg-[#0066cc]',
                destructive: 'bg-destructive text-destructive-foreground hover:bg-destructive/90',
                outline: 'border border-primary bg-transparent text-primary hover:bg-primary hover:text-primary-foreground',
                secondary: 'bg-secondary text-secondary-foreground hover:bg-secondary/80',
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
