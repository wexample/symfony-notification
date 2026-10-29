# symfony-notification

Version: 2.1.11

Notifications addressed to people: a catalogue of types, an inbox, each person's choice of channel, and once per window for reminders — sent by mail, chat or webhook

## Table of Contents

- [Installation](#installation)

## Installation

```bash
composer require wexample/symfony-notification
```

```php
// config/bundles.php
Wexample\SymfonyNotification\WexampleSymfonyNotificationBundle::class => ['all' => true],
```

Notifications are sent through Messenger: `framework.messenger` must be on — synchronous unless `DeliverNotificationsMessage` is routed to a transport.

The bundle maps two tables, `notification` (the inbox) and `notification_preference` (each recipient's choices): generate a migration after installing.

```bash
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

Nothing is sent until categories are declared — see the usage page. The pages — the inbox, the preferences, the page a mail's unsubscribe link opens — are symfony-notification-ds's.
