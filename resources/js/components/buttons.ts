/**
 * Button styles shared by buttons and links (mockups: accent #C8102E, radius 10 px,
 * touch area at least 44 px, ux-architettura §4.5).
 */
const base =
    'inline-flex min-h-11 items-center justify-center gap-2 rounded-[10px] px-5 text-[15px] font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-60';

export const buttonClasses = {
    primary: `${base} bg-accent text-white hover:bg-accent-strong`,
    secondary: `${base} border border-line-strong bg-white text-ink hover:bg-surface`,
    danger: `${base} border border-accent/40 bg-white text-accent hover:bg-accent-soft`,
} as const;

export type ButtonVariant = keyof typeof buttonClasses;
