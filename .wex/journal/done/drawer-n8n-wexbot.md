# Receiving side of network's webhooks and the 'wexbot' scheduled reminders (n8n, 2021–2023)

Opened: 2026-10-09
Updated: 2026-10-13
Author: agent:archeology-drawers

## Status — dropped (2026-10-13, weeger)

Not done, on purpose. Everything here hangs on tools no longer in use: wexbot is dead, n8n is not used
today, and the Rocket.Chat → Twitter relay went with them. The one reusable idea — reminders sent on a
schedule — needs nothing from this archive: Symfony Scheduler covers it the day an application wants one.

## Read this first — status of this todo

> **This is a proposal for discussion, not an order to code.** It comes from the 2026-10 "bottom of the drawers" archaeology pass. Read it, then discuss it with the owner: every design choice below is to be challenged and validated **before** any code is written. Do not start implementing on your own.
>
> - **Before anything else**: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/PACKAGES/PHP/.wex/local/package-agent-intro.md` — where you stand in the suite, what a package must look like, how to work, and how to judge a drawer todo.
> - Context: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/local/network/.wex/knowledge/readme/archeology/index.md.j2`, `sources.md.j2`, and the drawers page linked below.
> - Safety: `NETWORK/local/network` runs on production data — read its code only, never run anything against it. Anonymize anything taken from it. Never copy secrets found in its history.

Drawers page: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/local/network/.wex/knowledge/readme/archeology/drawers-outside.md.j2`

## What it is

network sends a GET webhook to n8n (`NotificationService::webHook`, see `mail-notification.md.j2`). What n8n did with it, and the community bot built next to it, live only in the n8n backup. Two features there belong to a notification package or to network itself.

## Where

`/home/weeger/Desktop/WIP/WEB/WEXAMPLE/PROD/local/n8n/n8n/backup/workflow/` (exported 2023-01-30):
- `10.json` "WEBHOOKS": node `HOOK` (path `wexenger-notifications`), `MESSAGES` (function), `ENV` (if), Rocket.Chat `PROD_WEXENGER_GENERAL` / `DEV_WEXENGER_GENERAL`, Twitter.
- `2.json` "WEXBOT": cron triggers + Rocket.Chat/Twitter nodes.
- `8.json` "WEXBOT_AUTOPOST": Google Sheets queue → LinkedIn / Twitter / Rocket.Chat.
- Credentials are in `PROD/local/n8n/n8n/database.sqlite`: never open or copy them. `8.json` holds sheet and LinkedIn account ids.

## What to recover

1. **Message templates per notification** (`MESSAGES`): query params `notification`, `channel` (default `general`), `env`; template map `core_update` → `:core_update_summary`; `donation_sent` → "a generous person just donated :cart_item_price_final"; `membership_renewed` → "a new Wexie just joined us". Every `:key` in the template is replaced by the query value of the same name (regex replace over all query entries). `env=prod` → Rocket.Chat + Twitter; otherwise Rocket.Chat with a `[DEV]` marker.
2. **Scheduled community reminders** (`2.json`), each a cron + a chat message: Monday 8h (project workshop at 14h + room link), Wednesday 8h (general assembly at 18h), Friday 8h (tech workshop at 15h), Friday 18h (weekend wishes), **the 25th of each month at 8h: "it's accounting time, drop your invoices for this month in network"**, and a yearly anniversary message (named "23th of March" but the cron `0 0 8 23 2 *` fires on 23 February).
3. A chat → social relay: a slash-style webhook from Rocket.Chat posts a member's message on Twitter if the member is in a hard-coded allow-list, else answers privately with the list of allowed members.

## Proposed shape (suggestion)

- In `symfony-notification`: a **channel-agnostic message template per notification type**, with placeholders filled from the notification payload, and a per-environment routing rule (prod → public channels, dev → prefixed internal channel). Today the texts sit in n8n, outside the app.
- A **scheduled reminder** entity or config (cron expression, audience, channel, template). The monthly "submit your invoices" reminder is business logic network should own (it could even list the member's projects with no input invoice this month).
- The social autopost and the Twitter relay stay outside (n8n or nothing).

## Open questions

1. Is n8n kept in 2027, or should network send chat messages itself (Symfony Notifier)?
2. Keep the monthly invoice reminder? As a chat message, a mail, or both?
