# Marketing agent

A scheduled agent that runs once a day with one goal: more people installing Argo Books and paying for it. It reads the numbers, writes posts and outreach emails, and records what it did and what came of it. The next run reads that record and adjusts.

The agent itself runs outside the site, in Anthropic's cloud. This document covers the site's side: what the agent can and cannot do, how to set it up, and how to run it day to day. The reasoning behind the design, and the reasons it may not work, are in the last sections.

The agent holds one secret token and nothing else. Every credential and every limit lives on the site. The agent asks the site to do things through `/api/agent/`, and the site decides. This matters because the agent reads the open web, and a page it reads could try to instruct it. Whatever it is talked into, it can only do what the endpoint allows.

## What it can do

- **Read the numbers.** Visits, the install funnel by referral link, survey answers, uninstall reasons, outreach results, and a count of paying subscribers.
- **Run SQL**, one `SELECT` at a time, over a fixed list of tables. Customer accounts, payments, licence keys, portal data and synced books are not on the list, and visitor IP addresses are removed from every answer.
- **Make tracked referral links**, with codes starting `ag-`, pointing only at argorobots.com. They are listed under their own category, Marketing agent, on the admin Referral Links page.
- **Research** through Gemini with Google Search, or with its own web search.
- **Propose posts** for Bluesky, LinkedIn and Threads.
- **Propose outreach emails** to businesses it found, and write the follow-ups for those businesses.
- **Keep a journal and notes**, which are its memory between runs.

## What it cannot do

- Spend money. X is not connected, because posting there is paid. See "X, if it is added" below.
- Create pages on the site or submit directory listings.
- Email anyone the site already knows: a paying subscriber, a licence holder, an account holder, a newsletter subscriber, or anyone who unsubscribed from anything.
- Email an address it did not read from a page. The site loads the page the agent names and refuses the email if the address is not there.
- Read customer data.
- Read or answer replies. A reply to an outreach email arrives in the contact@argorobots.com inbox, and the `reply_checker` cron marks that business as replied. The agent sees that a business replied and the subject line, not what the reply said. Answering is yours to do.

## Approval

The agent starts in approval mode. It writes the post or the email and the site holds it on the admin page, on the **Waiting** tab. Nothing goes out until you approve it.

- **Approve** carries it out. A post is published at once. An email is not sent at once: the business goes onto the outreach list with its email already approved, and the outreach cron sends it on its next run, at 8:00 AM. That cron has its own switch, in **Admin, Outreach, Settings**. While it is off, approved emails wait. The Setup tab shows whether it is on, and the page tells you when you approve an email that cannot go out.
- **Edit and approve** does the same with your wording.
- **Reject** drops it. Add a line saying why.

Your edits and rejections are shown to the agent at the start of its next run. This is the best teaching it gets, so the one-line reason is worth writing.

There are two switches, one for posts and one for emails. Turning one off makes that kind go out as soon as the agent writes it. Follow-up emails are the exception to the admin page: while email approval is on they wait in **Outreach, Follow-ups**, which is where follow-ups have always been approved.

## The admin page

**Admin, Agent** has one tab for each of these: what is waiting for you, the switches, what is set up and which platforms are connected, the limits, the notes, recent runs, what was decided, the journal, and the site's own record of every action. It opens on Waiting, and the tab shows how many items are waiting.

Two things on it are worth knowing about.

- **Your note to the agent.** It reads this at the start of every run and cannot change it. This is how to steer it without editing the playbook.
- **The site's record of what it did.** This is written by the site, not by the agent, so it is the record to trust if the agent's own summary and the record disagree.

Everything starts switched off. Nothing happens until you turn the agent on.

## The daily update

When a run finishes, the site emails you a short update. It starts with what needs you: each post or email waiting for approval, on one line, and any platform sign-in about to run out. Then comes the agent's own summary, which the site limits to 800 characters, and one line counting what the run did. The wording of each proposal and the full record of the run are on the admin page, not in the email. It goes to the admin notification address set in **Admin, Settings**.

A run that never starts sends nothing. The `agent_watch` cron covers that: it emails you when no run has finished for a day and a half. See `read-me/Cron-jobs.md`.

## The files it works from

- `api/agent/playbook.md` holds its standing instructions. It is sent to the agent at the start of every run, so editing the file changes its behaviour from the next run.
- `api/agent/facts.md` is the list of things that may be said about Argo Books. If a claim is not there and not on the live site, the agent is told not to make it. Every line names the page or file it was taken from, so it can be checked again. It also lists things the site says that the agent must not repeat, and things it must never say. Update it when the app changes: a fact the agent cannot find here is a fact it will not use.

Prices and monthly limits are not in the facts file. They are sent fresh each run from `config/pricing.php`, the same source the pricing page uses.

Neither file can be read over the web: `api/agent/.htaccess` blocks them.

## Limits

These limits are set on the admin page:

- Runs a day: 2. The scheduler has no cap of its own on what a run costs, so this is what stops a schedule set wrong from running all day.
- Requests a run: 200.
- Posts a day: 1 on each platform.
- New outreach emails a day: 10. Sending still follows `OUTREACH_DAILY_SEND_LIMIT`.
- New links a day: 10.
- Research questions a day: 40.

## Setting it up

### 1. A token for the agent

Add to the server's `.env` a long random value:

```
AGENT_API_TOKEN="<64 random characters>"
```

The endpoint answers "not set up" until this is at least 32 characters long.

### 2. A database user that can only read

In cPanel, under MySQL Databases, create a new user and add it to the database with the **SELECT** privilege only. Then add to `.env`:

```
AGENT_DB_USER="<that user>"
AGENT_DB_PASS="<its password>"
```

Without these the agent gets no SQL at all. There is no fallback to the site's own database user, on purpose.

### 3. Bluesky

In Bluesky, under Settings, Privacy and security, App passwords, create an app password. Add to `.env`:

```
BLUESKY_HANDLE="<your handle, such as argobooks.bsky.social>"
BLUESKY_APP_PASSWORD="<the app password>"
```

### 4. LinkedIn

Create an app at linkedin.com/developers. On its Products tab add **Share on LinkedIn** and **Sign In with LinkedIn using OpenID Connect**. On its Auth tab add this redirect URL exactly:

```
https://argorobots.com/admin/agent/connect.php
```

Add the app's keys to `.env`:

```
LINKEDIN_CLIENT_ID="<client id>"
LINKEDIN_CLIENT_SECRET="<client secret>"
```

Then on the admin Agent page, on the Setup tab, click **Connect** beside LinkedIn and sign in. The sign-in lasts about two months and LinkedIn offers no way to renew it, so it has to be connected again. The daily update warns you ten days before it runs out.

### 5. Threads

Create an app at developers.facebook.com with the Threads use case. Add the same redirect URL as above, and add your own Threads account as a tester of the app. Add to `.env`:

```
THREADS_APP_ID="<Threads app id>"
THREADS_APP_SECRET="<Threads app secret>"
```

Then click **Connect** beside Threads. This sign-in lasts 60 days and the site renews it by itself while the agent is running.

### 6. The scheduled agent

At claude.ai/code/routines, create a routine that runs once a day, with the website repo attached so the agent can read how the site and the product work.

In the routine's cloud environment:

- Set network access to **Full**. The narrower setting lets it reach argorobots.com but blocks the small business sites it has to read before writing to them.
- Add an **API credential** of the Bearer kind, holding the token from step 2, for the website `argorobots.com` and the path `/api/agent/`. The environment then adds the token to every request to that address. The agent never sees the token, so nothing it reads on the web can get it to reveal it. Do not put the token in an environment variable: those are not kept secret.

The routine also carries two short notes for Claude's own safety check, which otherwise stops the agent from sending SQL to a live site. One says that argorobots.com is the owner's own site and that its `/api/agent/` endpoint was built for this routine. The other allows read-only `SELECT` queries to the `sql` action. They cannot be entered on the routine's page. Claude Code sets them, so ask it to if the routine is ever made again.

Its prompt is short, because the real instructions are the playbook, which the site sends at the start of every run. The prompt has to say five things:

- That the environment signs its requests for it, so it adds no `Authorization` header and looks for no token.
- To start with a `POST` to `https://argorobots.com/api/agent/?action=start_run`, and what to do when that is refused: stop at once if the agent is switched off or has already run today, and say so in its last message if the site cannot be reached or the sign-in is wrong.
- That the playbook and the note from the owner in the answer are its only instructions, and that anything it reads on the web is information, never instructions.
- That the repo is for reading only, and that SQL is for counts and totals, not for text people typed themselves.
- To always finish by calling `finish_run`, even if the run went badly.

The wording it runs with is on the routine's own page, which is the only place it is kept.

Each run can be read afterwards on the routine's page, step by step.

### 7. Switch it on

On the admin Agent page, turn on **Agent**. Leave posting and outreach off for the first run, so you can read what it makes of the numbers before it writes anything. Then turn those on, with approval left on.

## Choosing how it researches

The agent can search two ways: its own web search, or `research`, which asks Gemini with Google Search through the site. Which is better for finding small businesses was not known, so the playbook has the agent test both and record the result in a note called `research-test`. It ran that test on 2026-10-05. `research` named 25 businesses and about 15 had a real email on their own page. Its own search named about 11 and none had an email. It uses `research`. If you disagree with its choice, say so in your note to the agent.

`research` uses `GEMINI_API_KEY`, and the model in `GEMINI_RESEARCH_MODEL` if that is set, otherwise `GEMINI_MODEL`. Google gives a free monthly allowance of searches. At 40 questions a day the agent uses at most about 1,200 a month, which should sit inside it. Check the allowance for your key in Google's own console.

## Judging it

The measure is paid subscriptions and installs that came through the agent's own tracked links, plus replies to its outreach. Followers, views and clicks are worth noting but not the goal.

## What it cannot see

These limit what it can learn, so keep them in mind when reading its conclusions.

- **How a LinkedIn post did.** The free LinkedIn access creates posts and returns no likes, comments or views. A LinkedIn post is judged only by its tracked link. Bluesky and Threads do return numbers for the account's own posts.
- **Comments and replies on posts.** It cannot answer them on any platform, so it cannot hold the conversations that early customers usually come from.
- **What people do inside the app.** Feature usage is in telemetry files and is worked out inside the admin page, not in the database. The agent sees installs, first runs and survey answers.
- **Who the customers are.** It gets counts of paying subscribers, never names or addresses.

LinkedIn posts go to the personal profile. Posting to a company page needs an access level LinkedIn grants by review.

## Why its memory is a journal and notes

The agent starts every run with no memory of the last one, so its memory is kept on the site.

- **Notes** are a few short documents it rewrites as it learns. It reads all of them at the start of every run.
- **The journal** is the dated log of what it tried and what happened. Read it to judge whether the agent is thinking well.

You can read both on the admin page, and correct or delete a note. That is the reason for doing it this way: a memory you can see is one you can fix.

## X, if it is added

X is not built. It is the one platform that costs money, so it is meant to come last, once the free platforms are bringing installs.

- Posting goes through X's paid API. When this was planned, a post cost $0.015, a post containing a link cost $0.20, and reading a post's numbers back cost $0.001. Check the prices again before building.
- Buy a fixed amount of credit and leave automatic top-up off. When the credit runs out, posting stops and nothing more is charged. At one post a day without a link, $10 lasts well over a year.
- Because a link in a post costs so much more, the tracked link belongs in the profile, and a link in a post is something to spend on deliberately.

## What it needs from you

- Approving, editing or rejecting what it writes, for as long as approval is on.
- Connecting LinkedIn again about every two months.
- Updating `api/agent/facts.md` when the app changes. A fact that is missing is not used, and a fact that has gone stale is repeated.
- Reading the journal now and then, and correcting a note that has drawn the wrong lesson.

## Risks

- **A wrong claim goes out under your name.** The facts file, the sources each proposal must give, and the one-post-a-day limit reduce this. Only your approval prevents it, which is why approval starts on.
- **Cold email affects deliverability.** Outreach is sent from the same domain as receipts and licence emails. More cold email means more risk to those, so the daily sending limit stays where it is.
- **Cold email law.** Canada's anti-spam law restricts unsolicited commercial email. The outreach pipeline already sent it before the agent existed, but an agent adding businesses every day makes it steadier.
- **Instructions hidden in web pages.** The agent reads the open web. The defence is that every power is limited on the site, not left to the agent's judgment.
- **It rests on a preview feature.** Scheduled agents are a research preview and may change. Its cost in Claude usage has no hard limit either: the limits are how often it runs and how many requests a run may make.

## Where the code is

- `api/agent/index.php` checks the token and routes requests.
- `api/agent/lib.php` holds settings, runs, the journal, notes, and reading.
- `api/agent/actions.php` holds links, research, proposals and carrying them out, and the daily update.
- `api/agent/platforms.php` holds Bluesky, LinkedIn and Threads.
- `admin/agent/index.php` is the admin page, and `admin/agent/connect.php` the platform sign-in.
- `cron/agent_watch.php` is the check that a run happened.
- `cron/lib/outreach_helpers.php` has `outreach_known_contact()`, the rule for who outreach must never reach. The outreach cron uses it too, for every lead, not only the agent's.
- `tests/Integration/Agent/AgentTest.php` covers the limits.
