import { Moon, Sun } from 'lucide-react';
import { useTheme } from '../theme/ThemeContext';

type Props = {
  /** Full width row (sidebar) vs compact (header) */
  variant?: 'row' | 'compact';
};

export function ThemeToggle({ variant = 'row' }: Props): JSX.Element {
  const { theme, toggleTheme } = useTheme();
  const isDark = theme === 'dark';

  const base =
    variant === 'row'
      ? 'flex w-full items-center justify-center gap-2 rounded-xl border border-border bg-card px-3 py-2.5 text-sm font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground'
      : 'inline-flex items-center justify-center rounded-xl border border-border bg-card p-2.5 text-muted-foreground transition hover:bg-muted hover:text-foreground';

  return (
    <button type="button" className={base} onClick={toggleTheme} aria-label={isDark ? 'Switch to light theme' : 'Switch to dark theme'}>
      {isDark ? <Sun className="h-4 w-4 shrink-0 text-brand-lime" /> : <Moon className="h-4 w-4 shrink-0" />}
      {variant === 'row' ? <span>{isDark ? 'Light mode' : 'Dark mode'}</span> : null}
    </button>
  );
}
