<?php

namespace App\Domain\Operations\Services;

use App\Models\User;
use App\Support\SupportCode;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\UserDataBag;
use Throwable;

/**
 * Last step before an error leaves for Sentry (progettazione-tecnica §5.2.7; D-179; PR-09): no
 * personal data (CLAUDE.md §7). Registered as "before_send" in config/sentry.php, together with
 * send_default_pii off.
 *
 * What stays: the type of the error, its message without e-mail and IP addresses nor SQL, the stack
 * trace without variables, method and address of the page without the query string, the id and the
 * role of the signed-in user and the support code shown to the user (D-265), so that an error
 * reported by phone can be found.
 *
 * What goes: e-mail, name and IP address of the user, cookies, headers, body and query string of the
 * request, extra data, values of the variables of the stack frames, bindings of the SQL breadcrumbs.
 */
final class SentryScrubber
{
    public static function beforeSend(Event $event, ?EventHint $hint = null): Event
    {
        self::user($event);
        self::request($event);

        try {
            $event->setTag('support_code', app(SupportCode::class)->value());
        } catch (Throwable) {
            // No container (very early failure): the error is sent without the code.
        }

        $event->setExtra([]);

        $message = $event->getMessage();
        if ($message !== null) {
            $event->setMessage(self::text($message));
        }

        foreach ($event->getExceptions() as $exception) {
            $exception->setValue(self::text($exception->getValue()));

            foreach ($exception->getStacktrace()?->getFrames() ?? [] as $frame) {
                $frame->setVars([]);
            }
        }

        $breadcrumbs = [];
        foreach ($event->getBreadcrumbs() as $breadcrumb) {
            $breadcrumb = $breadcrumb->withoutMetadata('bindings');
            $text = $breadcrumb->getMessage();
            $breadcrumbs[] = $text === null ? $breadcrumb : $breadcrumb->withMessage(self::text($text));
        }
        $event->setBreadcrumb($breadcrumbs);

        return $event;
    }

    /**
     * Removes e-mail addresses, IP addresses, the SQL statement that Laravel appends to database
     * errors and the value quoted in "Duplicate entry '…'".
     */
    public static function text(string $text): string
    {
        $text = (string) preg_replace('/\s*\((?:Connection:.*?)?SQL:\s.*$/s', '', $text);
        $text = (string) preg_replace("/Duplicate entry '[^']*'/", "Duplicate entry '[valore]'", $text);
        $text = (string) preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[e-mail]', $text);
        $text = (string) preg_replace('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', '[ip]', $text);

        // IPv6: eight groups, or a shortened form with "::" (times like 10:30:00 stay).
        return (string) preg_replace('/\b(?:[0-9a-f]{1,4}:){7}[0-9a-f]{1,4}\b|\b(?:[0-9a-f]{1,4}:){1,7}:(?:[0-9a-f]{1,4}(?::[0-9a-f]{1,4})*)?/i', '[ip]', $text);
    }

    private static function user(Event $event): void
    {
        $event->setUser(null);

        try {
            $user = auth()->user();
        } catch (Throwable) {
            $user = null;
        }

        if ($user instanceof User) {
            $event->setUser(UserDataBag::createFromUserIdentifier($user->id));
            $event->setTag('role', $user->role->value);
        }
    }

    private static function request(Event $event): void
    {
        $request = $event->getRequest();

        if ($request === []) {
            return;
        }

        $url = isset($request['url']) && is_string($request['url']) ? strtok($request['url'], '?') : null;

        $event->setRequest(array_filter([
            'method' => isset($request['method']) && is_string($request['method']) ? $request['method'] : null,
            'url' => $url === false ? null : $url,
        ]));
    }
}
