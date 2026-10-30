import { usePage } from '@inertiajs/vue3';
import { localDate } from '@/today';

/**
 * Today's date (YYYY-MM-DD) in the signed-in user's timezone (Preferences), for
 * default dates in forms. Call the returned function each time a form opens, so a
 * page left open past midnight still defaults to the right day. Falls back to the
 * server's shared `today` prop, then to the browser's own date.
 */
export function useToday() {
    const page = usePage();

    return () => {
        const timeZone = page.props.auth?.user?.timezone;

        if (timeZone) {
            return localDate(timeZone);
        }

        return page.props.today ?? localDate(undefined);
    };
}
