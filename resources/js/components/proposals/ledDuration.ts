/**
 * LED durations as the interface shows them (C6, like App\Support\LedDuration::format): whole seconds
 * → "1′30″". Display only: steps, residuals and prices come from the server (CLAUDE.md §4).
 */
export function formatLedDuration(seconds: number): string {
    const sign = seconds < 0 ? '−' : '';
    const value = Math.abs(seconds);
    const minutes = Math.floor(value / 60);
    const rest = value % 60;

    return `${sign}${minutes}′${String(rest).padStart(2, '0')}″`;
}
