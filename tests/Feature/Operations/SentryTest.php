<?php

use App\Domain\Operations\Services\SentryScrubber;
use App\Models\User;
use App\Support\SupportCode;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\ExceptionDataBag;
use Sentry\Frame;
use Sentry\Stacktrace;
use Sentry\UserDataBag;

/*
| Errors to Sentry (progettazione-tecnica §5.2.7; D-179; CLAUDE.md §7; PR-09): EU region, off without
| DSN, no personal data. The last step before sending (SentryScrubber) keeps the type of the error,
| the id and role of the user and the support code, and removes everything else that could identify
| a person.
*/

function sentryEvent(string $message): Event
{
    $event = Event::createEvent();
    $event->setUser(UserDataBag::createFromArray([
        'id' => 99,
        'email' => 'mario.rossi@example.test',
        'ip_address' => '93.40.1.2',
        'username' => 'mario',
    ]));
    $event->setRequest([
        'method' => 'POST',
        'url' => 'https://portale.example.test/utenti/5?token=segreto',
        'query_string' => 'token=segreto',
        'cookies' => ['portale_session' => 'abc'],
        'headers' => ['authorization' => ['Bearer x'], 'user-agent' => ['Test']],
        'data' => ['email' => 'mario.rossi@example.test', 'password' => 'Password-segreta-1'],
        'env' => ['REMOTE_ADDR' => '93.40.1.2'],
    ]);
    $event->setExtra(['email' => 'mario.rossi@example.test']);
    $event->setExceptions([new ExceptionDataBag(
        new RuntimeException($message),
        new Stacktrace([new Frame('store', 'app/Http/Controllers/UserController.php', 42, null, null, ['email' => 'mario.rossi@example.test'])]),
    )]);
    $event->setBreadcrumb([
        new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, 'db.sql.query', 'select * from users where email = ?', ['bindings' => ['mario.rossi@example.test']]),
    ]);

    return $event;
}

it('D-179: Sentry is off without DSN and never collects personal data nor performance data', function () {
    expect(config('sentry.dsn'))->toBeEmpty()
        ->and(config('sentry.send_default_pii'))->toBeFalse()
        ->and(config('sentry.traces_sample_rate'))->toBeNull()
        ->and(config('sentry.profiles_sample_rate'))->toBeNull()
        ->and(config('sentry.breadcrumbs.logs'))->toBeFalse()
        ->and(config('sentry.breadcrumbs.sql_bindings'))->toBeFalse()
        ->and(config('sentry.breadcrumbs.http_client_requests'))->toBeFalse()
        ->and(config('sentry.before_send'))->toBe([SentryScrubber::class, 'beforeSend']);
});

it('D-179: keeps only the id and the role of the signed-in user, with the support code', function () {
    $agent = User::factory()->agent()->create();
    $this->actingAs($agent);

    $event = SentryScrubber::beforeSend(sentryEvent('Errore di prova'));

    expect($event->getUser()?->getId())->toBe($agent->id)
        ->and($event->getUser()?->getEmail())->toBeNull()
        ->and($event->getUser()?->getIpAddress())->toBeNull()
        ->and($event->getUser()?->getUsername())->toBeNull()
        ->and($event->getTags())->toBe([
            'role' => 'agent',
            'support_code' => app(SupportCode::class)->value(),
        ]);
});

it('D-179: without a signed-in user no user goes to Sentry', function () {
    $event = SentryScrubber::beforeSend(sentryEvent('Errore di prova'));

    expect($event->getUser())->toBeNull()
        ->and($event->getTags())->toHaveKey('support_code');
});

it('D-179: keeps method and page of the request, without query string, cookies, headers and body', function () {
    $event = SentryScrubber::beforeSend(sentryEvent('Errore di prova'));

    expect($event->getRequest())->toBe([
        'method' => 'POST',
        'url' => 'https://portale.example.test/utenti/5',
    ])->and($event->getExtra())->toBe([]);
});

it('D-179: removes e-mail and IP addresses, SQL and duplicated values from the messages', function () {
    $event = SentryScrubber::beforeSend(sentryEvent(
        "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'mario.rossi@example.test' for key 'users_email_unique' "
        .'(Connection: mariadb, Host: 10.0.0.5, Port: 3306, Database: portale, SQL: insert into users (email) values (mario.rossi@example.test))'
    ));

    expect($event->getExceptions()[0]->getValue())
        ->toBe("SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '[valore]' for key 'users_email_unique'")
        ->and(SentryScrubber::text('Login from 93.40.1.2 and 2001:db8::1:2 by anna@example.test'))
        ->toBe('Login from [ip] and [ip] by [e-mail]');
});

it('D-179: removes the variables of the stack frames and the values of the SQL breadcrumbs', function () {
    $event = SentryScrubber::beforeSend(sentryEvent('Errore di prova'));

    $frame = $event->getExceptions()[0]->getStacktrace()?->getFrames()[0];
    $breadcrumb = $event->getBreadcrumbs()[0];

    expect($frame?->getVars())->toBe([])
        ->and($frame?->getLine())->toBe(42)
        ->and($breadcrumb->getMessage())->toBe('select * from users where email = ?')
        ->and($breadcrumb->getMetadata())->not->toHaveKey('bindings');
});
