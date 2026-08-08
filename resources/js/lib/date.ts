import { format } from 'date-fns';

/**
 * Today as `YYYY-MM-DD`, in the user's own timezone.
 *
 * Deliberately not `new Date().toISOString().slice(0, 10)`: that reads the UTC
 * date, so an evening sale anywhere west of UTC would be filed under tomorrow
 * and land in the wrong reporting period.
 */
export function todayIso(): string {
    return format(new Date(), 'yyyy-MM-dd');
}
