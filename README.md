# symfony-notification

Version: 2.1.3

## What it is

Telling people what they must know now: a catalogue of notification types, an inbox per person, each person's choice of channel, and once per window for reminders. Not to be confused with symfony-activity, which keeps what happened to be read later.

It sits over Symfony Notifier, which sends but keeps nothing: the chat goes through a Notifier chatter transport, the mail through symfony-mail.

## Declaring the categories

```yaml
wexample_symfony_notification:
    categories:
        invoice.validated:
            title: app.notifications::invoice_validated.title   # translation ids, read with the parameters
            body: app.notifications::invoice_validated.body
            label: app.notifications::invoice_validated.label   # its name on the preferences page; the title by default
            level: success                                       # info, success, warning, danger
            group: app.notifications::group.billing              # how the preferences page files it
            roles: [ROLE_MEMBER]                                 # empty: anyone
            channels: [mail]                                     # personal, each recipient may turn one off
            broadcast: [chat, webhook]                           # once per notify()
            route: app_invoice_show                              # the subject's page, given its id
    chat:
        transport: rocketchat                                    # a framework.notifier chatter transport
    webhook:
        url: '%env(NOTIFICATION_WEBHOOK_URL)%'
        method: POST
        username: '%env(NOTIFICATION_WEBHOOK_USER)%'
        password: '%env(NOTIFICATION_WEBHOOK_PASSWORD)%'
```

A category not declared is refused when sent.

## Sending

```php
$notifications->notify('invoice.validated', $organization->getMembers(), $invoice, ['number' => $invoice->getNumber(), 'actor' => (string) $user], $user);
```

- One `Notification` per recipient allowed to receive it (`roles`, through the hierarchy), each kept in its inbox: the inbox is no channel, nothing turns it off.
- Then `DeliverNotificationsMessage`, ids only, sends them: through each personal channel the recipient keeps, then once through each broadcast channel. Synchronous, unless the application routes the message to a transport — the texts are built when sending, in each recipient's language, never in the queue.
- A channel failing is logged and the others go on.
- A category with no `route` links to the `url` parameter, when sent with one: a link no route gives, a signed link for someone without an account.
- The actor and the recipient are separate fields; recipients and actors are kept by type and id, the subject through symfony-helpers' `LinkedToEntityTrait`.

### Reminders

```php
// Every day: each member told once since the start of the window.
$notifications->notifyOnce('membership.expiring', $members, $membership, new DateTimeImmutable('-1 month'), [...]);
```

`notifyOnce()` skips a recipient already told this category, about this subject, since then, and returns what it created — nothing when everyone had it.

## Channels

| Channel | Kind | Sends |
|---|---|---|
| `mail` | personal | through symfony-mail, in the recipient's language: the title as subject, the text, a link to the subject, a signed link to stop receiving the category by mail |
| `chat` | broadcast | a Notifier `ChatMessage` to `chat.transport`; nothing without a chatter |
| `webhook` | broadcast | `{env, notification, channel, level, subject, data}` as JSON (or `?payload=` for GET), basic auth, 2 s timeout; nothing without `webhook.url` |

Another channel implements `NotificationChannelInterface`: its name, personal or not, `deliver()`.

## Preferences

Opted out of, never into: without a choice stored, a category goes through its `channels`. `NotificationPreferenceService::set($recipient, 'invoice.validated', 'mail', false)` stores one; `getChoices()` lists them for a page; `getUnsubscribeUrl()` signs a link that turns a channel off without signing in, `unsubscribe($request)` applies it.

## The inbox

`NotificationInboxService` reads the signed-in account's: `paginate()`, `countUnread()`, `markRead($id)` — its own only —, `markAllRead()`. A page asks without reloading:

| Route | Path | |
|---|---|---|
| `notification_unread_count` | `GET /_notifications/unread-count` | `{count}` |
| `notification_read` | `POST /_notifications/{id}/read` | `{count}`, 404 for another's |
| `notification_read_all` | `POST /_notifications/read-all` | `{count: 0}` |

## Table of Contents

- [What it is](#what-it-is)
- [Declaring the categories](#declaring-the-categories)
- [Sending](#sending)
- [Channels](#channels)
- [Preferences](#preferences)
- [The inbox](#the-inbox)
- [Integration in the Suite](#integration-in-the-suite)
- [Dependencies](#dependencies)
- [Versioning & Compatibility Policy](#versioning--compatibility-policy)
- [License](#license)
- [About us](#about-us)
- [Migration Notes](#migration-notes)

## Integration in the Suite

This package is part of the Wexample Suite — a collection of high-quality, modular tools designed to work seamlessly together across multiple languages and environments.

### Related Packages

The suite includes packages for configuration management, file handling, prompts, and more. Each package can be used independently or as part of the integrated suite.

## Dependencies

- php: >=8.5
- doctrine/orm: ^3.0
- doctrine/doctrine-bundle: ^2.13 || ^3.0
- symfony/http-client: ^7.4 || ^8.0
- symfony/http-kernel: ^7.4 || ^8.0
- symfony/messenger: ^7.4 || ^8.0
- symfony/notifier: ^7.4 || ^8.0
- symfony/routing: ^7.4 || ^8.0
- symfony/security-core: ^7.4 || ^8.0
- symfony/uid: ^7.4 || ^8.0
- wexample/symfony-helpers: >=18.0.0
- wexample/symfony-loader: >=24.0.0
- wexample/symfony-mail: >=3.0.0
- wexample/symfony-translations: >=16.0.0

## Versioning & Compatibility Policy

Wexample packages follow **Semantic Versioning** (SemVer):

- **MAJOR**: Breaking changes
- **MINOR**: New features, backward compatible
- **PATCH**: Bug fixes, backward compatible

We maintain backward compatibility within major versions and provide clear migration guides for breaking changes.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

Free to use in both personal and commercial projects.

## About us

[Wexample](https://wexample.com) stands as a cornerstone of the digital ecosystem — a collective of seasoned engineers, researchers, and creators driven by a relentless pursuit of technological excellence. More than a media platform, it has grown into a vibrant community where innovation meets craftsmanship, and where every line of code reflects a commitment to clarity, durability, and shared intelligence.

This packages suite embodies this spirit. Trusted by professionals and enthusiasts alike, it delivers a consistent, high-quality foundation for modern development — open, elegant, and battle-tested. Its reputation is built on years of collaboration, refinement, and rigorous attention to detail, making it a natural choice for those who demand both robustness and beauty in their tools.

Wexample cultivates a culture of mastery. Each package, each contribution carries the mark of a community that values precision, ethics, and innovation — a community proud to shape the future of digital craftsmanship.

Built and maintained by [Romain Weeger](https://weeger.io).

## Migration Notes

When upgrading between major versions, refer to the migration guides in the documentation.

Breaking changes are clearly documented with upgrade paths and examples.
