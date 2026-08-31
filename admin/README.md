# Admin Panel — Setup Guide

A hand-rolled **MVC PHP** admin panel for managing your freelance leads.
It ships with secure **admin login (auth)**, a dashboard, role-based **Admin
Management**, the **Enquiry Management** module (contact-form messages, with
Important / Client flags), the admin-only **Client Management** module
(clients, meetings, invoices and payments), the **Project Management**
module (projects, tasks, assignment and per-task comments) shared by admins
and managers, the admin-only **Hosting & Domain Management** module
(hosting/domain records, renewal countdowns and renewal reminders), and the
admin-only **Billing** module (printable bills raised against a client's
project the moment they pay, bill history, and PDF download).

## Architecture (MVC)

```
admin/
├── index.php              # Front controller — every request enters here
├── .htaccess              # Routes requests to index.php + blocks app/ config/ database/
├── config/config.php      # DB credentials, base path, session settings, helpers
├── app/
│   ├── Core/              # The framework: Router, Controller, Model, View, Auth, Database
│   ├── Controllers/       # Auth, Dashboard, User (admin mgmt), Account, Enquiry, Client, Project, Hosting, Bill
│   ├── Models/            # User, Lead, LeadNote, Client, ClientMeeting, ClientInvoice, ClientPayment, Project, ProjectTask, ProjectTaskNote, HostingService, HostingRenewal, Bill
│   └── Views/             # HTML templates (layouts, auth, dashboard, users, account, enquiries, clients, projects, hosting, bills, errors)
├── assets/css/admin.css   # Indigo/slate professional theme
├── assets/js/hosting.js   # Hosting forms: auto renewal date, show/hide, client autofill
└── database/
    ├── schema.sql                          # Fresh install: creates every table (roles + enquiries + onboarding + clients + projects + hosting)
    ├── migration_roles.sql                 # Existing install: adds the role column
    ├── migration_enquiries.sql             # Existing install: adds is_important / is_client flags
    ├── migration_onboarding.sql            # Existing install: adds hire-record + document columns
    ├── migration_enquiry_unread_notes.sql  # Existing install: adds is_read + the lead_notes timeline table
    ├── migration_clients.sql               # Existing install: adds the Client Management tables
    ├── migration_client_services_cost.sql  # Only if you ran migration_clients.sql before it had services/project_cost
    ├── migration_projects.sql              # Existing install: adds the Project Management tables
    ├── migration_hosting.sql               # Existing install: adds the Hosting & Domain tables
    ├── migration_bills.sql                 # Existing install: adds the Billing table
    └── create_admin.php                    # One-time: creates your first login, then DELETE it
```

**Request flow:** browser → `.htaccess` → `index.php` → `Router` → `Controller` → `Model` (DB) → `View` → HTML.

## Deploy to cPanel (one time)

1. **Upload** the whole `admin/` folder into `public_html/` so it lives at
   `public_html/admin/`.
2. **Create the database**: cPanel → *MySQL Databases* → create a database and a
   user, add the user to the database with **All Privileges**. Note the names
   (cPanel prefixes them, e.g. `cpuser_admin`).
3. **Edit** `config/config.php` → set `DB_NAME`, `DB_USER`, `DB_PASS`.
   Also set `SECURE_COOKIES` to `true` (you have HTTPS) and `DEBUG` to `false`.
4. **Import the schema**: cPanel → *phpMyAdmin* → select your DB → *Import* →
   choose `database/schema.sql` → Go.
5. **Create your login**: edit the name/email/password at the top of
   `database/create_admin.php`, visit
   `https://yoursite.com/admin/database/create_admin.php` once, then **delete that file**.
6. Go to `https://yoursite.com/admin/` and sign in. 🎉

## Run locally first (recommended)

Install **XAMPP** (or Laragon). Put the `admin` folder in `htdocs`, start
Apache + MySQL, create a DB in phpMyAdmin, import `schema.sql`, run
`create_admin.php`, then open `http://localhost/admin/`.
(Keep `BASE_PATH = '/admin'` to match the URL.)

## Roles & Admin Management

Two roles control who can do what:

| Capability                        | Admin | Manager |
|-----------------------------------|:-----:|:-------:|
| Sign in, view dashboard           |  ✅   |   ✅    |
| **Admin Management** (add/remove users, reset passwords) | ✅ | ❌ |
| **Client Management** (clients, meetings, invoices, payments) | ✅ | ❌ |
| **Project Management** — view projects, update status/comment on own tasks | ✅ | ✅ |
| **Project Management** — create/edit/delete projects and tasks, assign work | ✅ | ❌ |
| **Hosting & Domain Management** (hosting/domain records, renewals) | ✅ | ❌ |
| **Billing** (raise bills, view history, download PDF) | ✅ | ❌ |
| Add another admin **or** manager  |  ✅   |   ❌    |
| Change their **own** password     |  ✅   |   ❌    |
| Have their password reset by an admin | ✅ | ✅ |

- The **Admin Management** page (`/admin/users`) is only visible/reachable to admins.
  From there an admin adds team members (choosing admin or manager), resets any
  user's password, or deletes an account.
- A **manager cannot change their own password** — the form on *My Account* is
  replaced with a note telling them to ask an admin. Only an admin can reset it.
- Safety rails: you can't delete your own account, and you can't remove the last
  remaining admin (so the panel can never be left with no one who can manage it).

**Upgrading an existing install:** import `database/migration_roles.sql` once in
phpMyAdmin. It adds the `role` column and promotes your original login to admin so
you keep full access. Fresh installs get roles automatically from `schema.sql`.

## Employee onboarding

Adding a team member is treated like a company hiring an employee — the admin's
**Add a team member** form on `/admin/users` collects a full hire record up
front (mobile number, alternate mobile, address, Aadhar number, PAN number,
designation, date of joining, date of birth, emergency contact), not just a
name and email.

Fields are split three ways (`App\Models\User`):

| Fields | Who can edit |
|---|---|
| Email, mobile, alternate mobile, address, Aadhar #, PAN # | Locked once set — only an admin can change them afterwards |
| Name, role, designation, date of joining | Admin-controlled — view-only for the member |
| Date of birth, emergency contact | The member can edit these on **My Account** any time |
| Profile photo, Aadhar front/back, PAN card | Uploaded once by the member, then locked |

A new manager is **blocked from every page** (redirected to `/account/onboarding`)
until they upload their profile photo and both ID documents on first sign-in —
enforced in `Controller::requireAuth()`. Admins are exempt from this gate.
Uploaded files are never web-accessible directly: they live in `storage/uploads/`
(denied by `.htaccess`) and are only streamed through the authenticated
`/account/document` route to the owner or an admin.

**Upgrading an existing install:** import `database/migration_onboarding.sql`
once in phpMyAdmin — it adds the hire-record and document columns. Fresh
installs get them automatically from `schema.sql`. Existing members (created
before this feature) will have empty hire-record fields; an admin should fill
those in from **Admin Management → Edit**, and existing managers will be asked
to upload their documents next time they sign in.

## Security built in

- Passwords stored with `password_hash()` (bcrypt), verified with `password_verify()`.
- All DB access uses **PDO prepared statements** (no SQL injection).
- **CSRF tokens** on login and logout forms.
- Session ID regenerated on login (anti session-fixation); HttpOnly + SameSite cookies.
- `app/`, `config/`, `database/` blocked from direct web access by `.htaccess`.
- Login errors don't reveal whether the email or the password was wrong.

## Enquiry Management

Contact-form messages from your website land in **Admin → Enquiries** (`/admin/enquiries`).

| Capability                                  | Admin | Manager |
|---------------------------------------------|:-----:|:-------:|
| View enquiries, open full message + notes   |  ✅   |   ✅    |
| Mark **Important** (★) / change status       |  ✅   |   ✅    |
| Mark as **Client** (row turns green)         |  ✅   |   ✅    |
| Add internal notes                           |  ✅   |   ✅    |
| **Delete** an enquiry                        |  ✅   |   ❌    |

- **Important** floats an enquiry to the top of the list and highlights it.
- **Client** flags a genuine client enquiry — the row and detail view turn green
  and a "Client" tag appears (also shown on the dashboard).
- Filter tabs at the top narrow the list to Important, Clients, or any status
  (New / Contacted / Quoted / Won / Lost).

**Unread indicator (red dot):** every new enquiry is created unread
(`leads.is_read = 0`). While any enquiry is unread, a red dot shows next to
**Enquiries** in the sidebar (visible from every page) and next to the
enquiry's name in the list/dashboard. Opening an enquiry
(`EnquiryController::show`) marks it read and the dot clears — this is
independent of the **status** pipeline (New/Contacted/…), so moving an
enquiry's status doesn't affect whether it still shows as unread.

**Notes timeline:** "Internal notes" on an enquiry is a running timeline
(`lead_notes` table via `App\Models\LeadNote`), not a single field that gets
overwritten. Every submission adds a new, timestamped entry stamped with the
team member who wrote it, so the whole history of who said what is kept.

**How messages get captured:** the site's contact form (`contact.html`) now
POSTs to `contact.php` at the site root, which validates the input (plus a spam
honeypot) and saves it via `Lead::create(...)`. If PHP isn't reachable (e.g. the
page is previewed as a static file), the form gracefully falls back to opening
the visitor's mail client. `contact.php` reuses this panel's `config/config.php`,
so it must sit one level **above** the `admin/` folder (i.e. in `public_html/`).

**Upgrading an existing install:** import `database/migration_enquiries.sql` once
in phpMyAdmin — it adds the `is_important` and `is_client` columns. Then import
`database/migration_enquiry_unread_notes.sql` once — it adds the `is_read`
column and the `lead_notes` table, and copies any existing text from the old
`leads.notes` field in as each enquiry's first note. Fresh installs get all of
this automatically from `schema.sql`.

## Client Management (admin only)

**Admin → Client Management** (`/admin/clients`) is where an actual client — not
just an enquiry — is managed end to end: their details, every meeting held with
them, every invoice raised, and every payment received.

> **Managers cannot use this module at all.** The sidebar link is only rendered
> for admins, and *every* action in `App\Controllers\ClientController` starts
> with `requireAdmin()` — so a manager who types a `/admin/clients…` URL directly
> (or POSTs to one) is bounced to the dashboard with a permission error. Hiding
> the link is cosmetic; the controller check is what actually enforces it.

**Adding a client** captures what the job actually is, not just who they are:
alongside name, company, email, phone and address, the form takes the
**services they took** (e.g. "Website design, SEO, Hosting") and the
**total project cost** — the whole agreed value of the work. The
**+ Add new client** button opens that form right on the list page.

**The client list** shows one row per client with their services, how many
meetings you've had, and their money position — project cost, total invoiced,
total received, and what's still outstanding (highlighted in red when they owe
you). The stat tiles across the top total the same figures for every client at
once.

**One client's page** (`/admin/clients/view?id=…`) opens as a **read-only record**
— it shows only what has actually been logged. Each section's panel head carries
its own button (**Edit details**, **+ Log meeting**, **+ Create invoice**,
**+ Record payment**) that slides that section's form open, so the page stays a
clean summary until you're actually adding something.

| Section | What it tracks |
|---|---|
| **Client details** | Company, email, phone, address, services taken, total project cost, still-to-invoice, and free-form notes |
| **Meetings** | Every meeting with its **date and time**, how long it ran (minutes), what was discussed, and which team member logged it |
| **Invoices** | Invoice number, amount, issue date, due date, what it's for, and a status of Unpaid / Paid / Overdue / Cancelled |
| **Payments** | Amount, date received, method (cash, bank transfer, UPI, card, cheque, other), and an optional link to the invoice it settles |

- **Meeting count** is simply how many meetings are logged, so "how many times
  have we met this client" is answered on both the client's page and the list.
- **Outstanding** is total invoiced minus total received — what they owe on bills
  already raised. Cancelled invoices are excluded from the invoiced total, so
  cancelling one clears it from what's owed.
- **Still to invoice** is total project cost minus total invoiced — how much of
  the agreed job you haven't billed yet. It's shown on the client's details and
  repeated as a hint on the invoice form. Leave the project cost blank and it
  simply reads "—".
- A payment can only be linked to an invoice **belonging to that same client** —
  the controller re-checks ownership and drops the link if it doesn't match.
- **Deleting a client removes everything about them** (meetings, invoices and
  payments cascade via foreign keys), so the confirm dialog spells that out.

Data lives in four tables — `clients`, `client_meetings`, `client_invoices` and
`client_payments` — backed by `App\Models\Client`, `ClientMeeting`,
`ClientInvoice` and `ClientPayment`.

**Upgrading an existing install:** import `database/migration_clients.sql` once
in phpMyAdmin — it creates the four tables. Fresh installs get them
automatically from `schema.sql`. If you imported `migration_clients.sql` back
when it had no `services` / `project_cost` columns, import
`database/migration_client_services_cost.sql` once to add them (skip it
otherwise — it would error with "duplicate column").

## Project Management (admin + manager)

**Project Management** (`/admin/projects`) is where you and your developer(s)
track actual work — projects broken into tasks, each assigned to one person,
moved through a status pipeline, and discussed inline. Unlike Client
Management, both roles use this module, so it's visible in the sidebar for
everyone and every page is reachable by a manager.

> **Who can do what is enforced per action, not per page.** Viewing the
> project list and a project's task board is open to any signed-in user.
> Creating, editing or deleting a project or a task is admin-only
> (`requireAdmin()` in `App\Controllers\ProjectController`). Moving a task's
> status and adding a comment on it is allowed for an admin **or** whoever
> that task is assigned to — checked with `canActOnTask()`, which compares
> `project_tasks.assigned_to` against the signed-in user. A manager who isn't
> assigned to a task sees its status dropdown disabled and has no comment box.

**Creating a project** takes a name, an optional linked client (from Client
Management), status (Planning / In Progress / On Hold / Completed /
Cancelled), priority (Low / Medium / High), start/due dates, an optional
budget, and a description. The **+ New project** button on the list page
opens this form; it's only shown to admins.

**The project list** shows every project with its client, status, priority, a
task-progress bar (`done / total` tasks), and due date. Filter tabs narrow it
to one status at a time. Signed-in users also see a **My tasks** panel above
the list — every open (not-done) task assigned to them, across all projects,
with a one-click status dropdown and a link back to the parent project.

**One project's page** (`/admin/projects/view?id=…`) shows its details (with
an admin-only **Edit details** toggle, same pattern as Client Management) and
its full task list as a set of cards. Each task card shows its priority,
title, assignee, due date (flagged **overdue** in red once past due and not
done), description, and a collapsible **comments** thread — a running
timeline (`project_task_notes`, mirrors the enquiry notes pattern) rather than
a single field that gets overwritten, so the admin and the assigned developer
can go back and forth on one task without losing history.

| Section | What it tracks |
|---|---|
| **Project details** | Linked client, status, priority, start/due dates, budget, description |
| **Tasks** | Title, description, assignee, priority, status (To do / In progress / Review / Done), due date |
| **Comments** | A per-task timeline of notes, each stamped with author + time |

- **Task progress** on the list page is `done tasks / total tasks` per
  project — a plain count, so it stays accurate as tasks are added or
  completed.
- **Assigning a task** is done from the admin-only add/edit task form, picking
  from every user (admin or manager) in Admin Management.
- **Deleting a project removes all its tasks and their comments** (cascading
  foreign keys), so the confirm dialog spells that out, same as deleting a
  client.

Data lives in three tables — `projects`, `project_tasks` and
`project_task_notes` — backed by `App\Models\Project`, `ProjectTask` and
`ProjectTaskNote`.

**Upgrading an existing install:** import `database/migration_projects.sql`
once in phpMyAdmin — it creates the three tables. Fresh installs get them
automatically from `schema.sql`.

## Hosting & Domain Management (admin only)

**Hosting** (`/admin/hosting`) tracks every hosting plan **and every domain**
you manage for a client, so a renewal is never missed. Hosting and domains
share one module because they behave identically — both are bought from a
provider, both expire, both need the same reminders — and a `service_type`
field tells them apart.

### How status works

Nothing about status is stored in the database. It is always recalculated from
the renewal date against today, so a record can never go stale:

```
days remaining = renewal date − today

days > 30   🟢 Active
days 8–30   🟡 Renewing Soon
days 0–7    🔴 Renewal Due (urgent)
days < 0    🔴 Expired
```

Anything renewed in the current calendar month also carries a 🔵 **Renewed**
badge and is counted by the *Renewed this month* card. The two thresholds are
the constants `HostingService::SOON_DAYS` (30) and `URGENT_DAYS` (7) — making
them admin-configurable later means reading them from settings instead.

The same day count drives the reminder wording shown on the dashboard:
30 days → *Upcoming Renewal*, 15 → *Renewal Approaching*, 7 → *Urgent
Renewal*, 0 → *Renewal Due Today*, past → *Expired*.

### The page

- **Summary cards** — Total, Active, Renewing Soon, Expired, Renewed this
  month. Each card is a link that filters the table below it.
- **Reminder banner** — e.g. *"3 renewals are due within the next 30 days"* —
  jumps to the Upcoming renewals section.
- **Upcoming renewals** — everything expired or expiring within 30 days, most
  urgent first, with client, website, renewal date, days remaining and amount.
- **The full table** — ordered renewal date → days left → status → client →
  action, so the thing that decides what to act on is read first.
- **Search** across client, company, website, domain and provider, plus filters
  for status, type (hosting/domain), provider, billing cycle and a renewal-date
  range, and sorting by renewal date, client name, amount or urgency.

The main **dashboard** carries a compact version of the same alert (expired /
due within 7 days / due within 30 days) with the three most urgent records, and
the sidebar shows a red dot next to *Hosting* whenever something has expired or
falls due inside a week.

### Automatic renewal date

Enter a **purchase date** and a **billing cycle** (monthly, quarterly,
half-yearly, yearly, or a custom number of months) and the renewal date fills
itself in — 15 Sep 2026 + 1 year → 15 Sep 2027. It is a convenience, not a
constraint: the admin can overwrite it, and the server recalculates it anyway
when the field is left blank, so it works with JavaScript switched off.

Month-ends are clamped rather than allowed to overflow: 31 Jan + 1 month is
28 Feb (29 Feb in a leap year), never 3 Mar.

### Renewing

**Mark as Renewed** on a record's page opens a short form — renewal date,
amount, new expiry (pre-filled from the billing cycle), payment status,
payment reference and notes. Saving it:

1. moves the expiry date forward,
2. sets the status back to Active,
3. files the renewal in that record's **history**, and
4. drops the record off the urgent list.

The history is kept forever, so a client's full story stays answerable:
*purchased 15 Sep 2025 → renewed 15 Sep 2026 → next due 15 Sep 2027*.

### Credentials

**This module never stores hosting passwords.** It keeps the control-panel
**login URL** (public, safe) and a **pointer** to where the login actually
lives — e.g. `Bitwarden > Hosting > abc.com`. Keep the password in your
password manager and record only the reference here. Storing plain-text
provider passwords in the panel database would put every client's hosting one
database leak away from being taken over.

Data lives in two tables — `hosting_services` and `hosting_renewals` — backed
by `App\Models\HostingService` and `App\Models\HostingRenewal`.

**Upgrading an existing install:** import `database/migration_hosting.sql`
once in phpMyAdmin — it creates the two tables. Fresh installs get them
automatically from `schema.sql`.

## Billing (admin only)

**Billing** (`/admin/bills`) is where a bill is raised **the moment a client
pays**: pick the client, pick which of their projects the payment is for, list
the services being billed, and record what was received. The result is a
proper, printable bill — your business details top-right, the client's
details on the left, the itemised services, the project's total cost, what's
been paid to date, and the remaining balance — ready to **Print / Save as
PDF** straight from the browser (no extra software, same approach already
used for the Enquiries export).

**Raising a bill** (**+ Raise a bill** on the list page, or **+ Raise a
bill** from a client's own page, which preselects them):

1. Choose the **client**, then the **project** — the project list narrows to
   just that client's projects, and its budget/cost shows immediately.
2. Add one or more **service lines** (description + amount) describing what
   this payment covers — **+ Add another service** adds more rows.
3. Enter the **amount paid now**, the date, and the payment method. A live
   preview shows the running total paid and what will remain once this
   payment is saved.
4. **Raise bill** saves it, writes a matching entry into `client_payments` so
   the client's totals on their own page stay correct, and opens the
   printable bill.

**Bill history** lists every bill with its number, client, project, amount
paid and balance due, each linking to the printable view. The stat tiles
across the top show **bills raised**, **received this month**, **total
collected**, and **pending amount** — the sum of `project budget − paid` over
every project that has a budget set, whether or not it's been billed yet.

- **Balance due** is frozen onto each bill at the moment it's created (project
  cost minus everything paid toward that project, including this payment), so
  a bill stays an accurate historical document even if the project's budget
  changes later.
- A bill only makes sense against a **project with a budget** — without one,
  the bill still itemises the services and the payment, but shows "—" instead
  of a balance.
- **Deleting a bill** also removes the `client_payments` row it created, so
  the client's totals don't end up counting a payment whose bill no longer
  exists.
- Your business details shown on every bill (name, mobile, email, website)
  are set directly in `app/Views/bills/show.php` — edit them there if they
  ever change.

Data lives in one table — `bills` — backed by `App\Models\Bill`, joining out
to `clients` and `projects` for display.

**Upgrading an existing install:** import `database/migration_bills.sql` once
in phpMyAdmin — it creates the table. Fresh installs get it automatically
from `schema.sql`.
