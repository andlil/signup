# signup – requirements specification

A self-hosted web app for signing up to events (for example a vacation, a party
or a course) through a unique link per event. Roughly like Google Forms, but with
places, a waiting list and Excel export.

## Technology

- Laravel (PHP 8.3+), MySQL/MariaDB
- Blade + Livewire for the interface
- Docker / docker-compose: `app` (PHP + Apache), `db` (MariaDB), `queue` (queue worker), `scheduler`
- Email through Laravel's mail configuration in `.env` (`MAIL_MAILER=log` during development, SMTP in production)
- Interface and emails in Swedish and English (English source strings, Swedish in `lang/sv.json` and `lang/sv/`)
- Excel export as `.xlsx`

## Roles

| Role         | Description |
|--------------|-------------|
| Main admin   | Creates, deactivates and deletes organizer accounts. Can see, edit and delete all events. |
| Organizer    | Logs in with a magic link. Creates and manages their own events. |
| Participant  | Has no account. Signs up through the event's public link. |

The first main admin is created from `ADMIN_EMAIL` in `.env` when the app starts, if there is no main admin yet, or with the command `php artisan signup:create-admin`.

## Login (magic link)

- The organizer enters their email address and receives an email with a one-time link.
- The link is valid for 15 minutes and can only be used once.
- Only active accounts created by the main admin can log in. The response is the same whether or not the email address exists, so nobody can find out which accounts exist.
- Login attempts are rate limited.

## Events

- Title, description (formatted with buttons for bold, italic, headings, lists, quotes and links; stored as Markdown), location, start and end
- An event must have a start, a last day to sign up, or both. An end requires a start. Without a start (for example a poll about vacation dates), retention is counted from the last day to sign up.
- Public link with a random slug: `/e/{slug}`
- Status: draft → published. A published event can be closed for signups by hand and reopened.
- Last day to sign up (optional). After it the form is closed. Signups always close when the event starts.
- The overview shows for each published event whether signups are open or closed, and why.
- Maximum number of places (optional). The organizer chooses per event how places are counted:
  - one place per signup, or
  - from a chosen number question in the form (for example "number of people")
- Waiting list when the event is full
- Notifications to the organizer, chosen per event: on every signup/cancellation, a daily summary, or none
- GDPR: the number of days after the event before signups are deleted automatically (30 days by default)

## Form builder

- The fields **Name** and **Email** are always included and required.
- The organizer can add their own questions of these types:
  - short text, long text
  - multiple choice (radio buttons), dropdown
  - checkboxes (several choices)
  - date
  - number (integer, with optional minimum and maximum)
  - vacation planning: the organizer sets the first and last day and whether to show weekdays only. The participant sees a calendar of the period's days and ticks the days they want off, also a whole week at a time. The period can be at most 366 days. In the signup list and in emails the choices are shown compactly ("12–16 July, 2 August (6 days)"), and in the Excel export the question gets a column with the number of days and one column per day. There is no limit on how many people can be off on the same day.
- Every question has a label and help text, can be required, and has a sort order.
- **Once the event is published, the questions cannot be changed.** The form is then locked. The description, dates and similar can still be edited.

## Signing up (participants)

- The participant fills in the form and ticks the consent box (required).
- If there is room the signup is confirmed, otherwise the participant goes on the waiting list (the participant sees which).
- The participant receives a confirmation email with a personal secret link where the signup can be changed or cancelled.
- Changes are possible until the last day to sign up.
- By default, cancelling is possible until the last day to sign up, or until the start if the event starts earlier.
- The organizer can choose to allow cancelling after the last day to sign up. Participants can then cancel until the event starts, or without a time limit if the event has no start.
- The organizer can always cancel a signup.

## Waiting list

- The order is decided by when the signup was made.
- When a place becomes free (through a cancellation, a smaller number of people or more places), the first person in line is moved up automatically, as long as they fit, and receives an email.
- If places are counted from the number question and the first person in line does not fit, the next person in order is tried.
- A confirmed participant who wants to increase their number only gets it if there are enough places. Otherwise an error is shown and the participant keeps their place.
- A signup cannot be larger than the event's total number of places.
- If the organizer reduces the number of places, nobody is cancelled. New signups then go on the waiting list.
- Each email address can only have one active signup per event.

## Organizer administration

- List of the organizer's events with places taken, the number on the waiting list and whether signups are open
- Signup list per event, where the organizer can cancel individual signups (the participant receives an email) and delete cancelled signups
- Excel export (`.xlsx`) with one row per signup and one column per question, plus columns for status (confirmed/waiting list), places and when the signup was made. Confirmed signups are listed first, then the waiting list in order. Cancelled signups are left out. Answers that look like formulas are stored as text.

## Background jobs (scheduler)

- Every 15 minutes: when signups close (at the last day to sign up or at the start, whichever comes first), the Excel file is emailed automatically to the organizer, once per event. Events that closed more than two days ago are skipped.
- 07:00: a daily summary to organizers who chose it: one email per organizer with new signups and cancellations since the previous summary. No email is sent if nothing happened.
- 03:00: signups are deleted when the event's retention period has passed, counted from the end, the start or the last day to sign up, in that order.
- 03:30: expired magic link tokens are removed.

## Out of scope

- Payments
- Voting on dates (like Doodle)
- Participant accounts
