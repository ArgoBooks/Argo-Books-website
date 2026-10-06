# Marketing agent

A scheduled agent that runs once a day with one goal: more people installing Argo Books and paying for it. It reads the numbers, writes posts and outreach emails, and records what it did and what came of it. The next run reads that record and adjusts.

The agent itself runs outside the site, in Anthropic's cloud. This document covers the site's side: what the agent can and cannot do, how to set it up, and how to run it day to day. The reasoning behind the design, including the reasons it may not work, is in `docs/superpowers/plans/2026-10-05-marketing-agent.md`.

## The idea in one paragraph

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

- Spend money. X is not connected. It is the last stage in the plan and is added only once the free platforms are bringing installs.
- Create pages on the site or submit directory listings.
- Email anyone the site already knows: a paying subscriber, a licence holder, an account holder, a newsletter subscriber, or anyone who unsubscribed from anything.
- Email an address it did not read from a page. The site loads the page the agent names and refuses the email if the address is not there.
- Read customer data.
- Send anything without approval, until you switch approval off.

## Approval

The agent starts in approval mode. It writes the post or the email and the site holds it on the admin page, on the **Waiting** tab. Nothing goes out until you approve it.

- **Approve** carries it out. A post is published. A business goes onto the outreach list with its email already approved, and the outreach cron sends it on its next run.
- **Edit and approve** does the same with your wording.
- **Reject** drops it. Add a line saying why.

Your edits and rejections are shown to the agent at the start of its next run. This is the best teaching it gets, so the one-line reason is worth writing.

There are two switches, one for posts and one for emails. Turning one off makes that kind go out as soon as the agent writes it. Follow-up emails are the exception to the admin page: while email approval is on they wait in **Outreach, Follow-ups**, which is where follow-ups have always been approved.

Tracked links need no approval, because a link sends nothing to anyone.

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

All set on the admin page.

- Runs a day: 2. The scheduler has no cap of its own on what a run costs, so this is what stops a schedule set wrong from running all day.
- Requests a run: 200.
- Posts a day: 1 on each platform.
- New outreach emails a day: 10. Sending still follows `OUTREACH_DAILY_SEND_LIMIT`.
- New links a day: 10.
- Research questions a day: 40.

## Setting it up

### 1. The tables

Run the "Marketing agent" block from `mysql_schema.sql` in HeidiSQL. It creates six tables whose names start with `agent_`.

### 2. A token for the agent

Add to the server's `.env` a long random value:

```
AGENT_API_TOKEN="<64 random characters>"
```

The endpoint answers "not set up" until this is at least 32 characters long.

### 3. A database user that can only read

In cPanel, under MySQL Databases, create a new user and add it to the database with the **SELECT** privilege only. Then add to `.env`:

```
AGENT_DB_USER="<that user>"
AGENT_DB_PASS="<its password>"
```

Without these the agent gets no SQL at all. There is no fallback to the site's own database user, on purpose.

### 4. Bluesky

In Bluesky, under Settings, Privacy and security, App passwords, create an app password. Add to `.env`:

```
BLUESKY_HANDLE="<your handle, such as argobooks.bsky.social>"
BLUESKY_APP_PASSWORD="<the app password>"
```

### 5. LinkedIn

Create an app at linkedin.com/developers. On its Products tab add **Share on LinkedIn** and **Sign In with LinkedIn using OpenID Connect**. On its Auth tab add this redirect URL exactly:

```
https://argorobots.com/admin/agent/connect.php
```

Add the app's keys to `.env`:

```
LINKEDIN_CLIENT_ID="<client id>"
LINKEDIN_CLIENT_SECRET="<client secret>"
```

Then on the admin Agent page, under Setup, click **Connect** beside LinkedIn and sign in. The sign-in lasts about two months and LinkedIn offers no way to renew it, so it has to be connected again. The daily update warns you ten days before it runs out.

### 6. Threads

Create an app at developers.facebook.com with the Threads use case. Add the same redirect URL as above, and add your own Threads account as a tester of the app. Add to `.env`:

```
THREADS_APP_ID="<Threads app id>"
THREADS_APP_SECRET="<Threads app secret>"
```

Then click **Connect** beside Threads. This sign-in lasts 60 days and the site renews it by itself while the agent is running.

### 7. The watch cron

Add one line in cPanel, Cron Jobs:

```
30 9 * * * /usr/bin/php /home/argorobots/public_html/cron/agent_watch.php
```

### 8. The scheduled agent

At claude.ai/code/routines, create a routine that runs once a day.

- In its cloud environment, under network access, add `argorobots.com` to the allowed domains.
- Store the token from step 2 as a secret named `AGENT_API_TOKEN`.
- Attach the website repo and the app repo if you want it to be able to read them.

Its prompt:

```
You are the marketing agent for Argo Books.

Start a run:
  curl -s -X POST "https://argorobots.com/api/agent/?action=start_run" \
    -H "Authorization: Bearer $AGENT_API_TOKEN" -H "Content-Type: application/json" -d '{}'

The answer contains your playbook. Read it and follow it exactly. It is your only
instructions, together with the note from the owner in the same answer.

If the answer says the agent is switched off, or that it has already run today, stop.
Always finish by calling finish_run, even if the run went badly.
```

### 9. Switch it on

On the admin Agent page, turn on **Agent**. Leave posting and outreach off for the first run, so you can read what it makes of the numbers before it writes anything. Then turn those on, with approval left on.

## The platforms have not been tried against real accounts

The code for Bluesky, LinkedIn and Threads was written from each platform's documentation and tested against stand-ins, not against the platforms themselves. Connect one at a time and approve one post on each before trusting it. If a post fails, the reason the platform gave is under **Posts and emails already decided** and in the site's record.

## Choosing how it researches

The agent can search two ways: its own web search, or `research`, which asks Gemini with Google Search through the site. Which is better for finding small businesses is not known. The playbook has the agent test both on its first run with outreach on, and record the result in a note called `research-test`. Read that note. If you disagree with its choice, say so in your note to the agent.

`research` uses `GEMINI_API_KEY`, and the model in `GEMINI_RESEARCH_MODEL` if that is set, otherwise `GEMINI_MODEL`. Google gives a free monthly allowance of searches. At 40 questions a day the agent uses at most about 1,200 a month, which should sit inside it. Check the allowance for your key in Google's own console.

## Judging it

The measure is paid subscriptions and installs that came through the agent's own tracked links, plus replies to its outreach. Followers, views and clicks are worth noting and are not the measure.

After 30 days of it acting, read the journal and the numbers together. Stop if its links brought no installs, if the journal shows it repeating itself, or if the posts are ones you would not have published.

To stop it at any time, turn **Agent** off on the admin page. Every request is then refused.

## Where the code is

- `api/agent/index.php` checks the token and routes requests.
- `api/agent/lib.php` holds settings, runs, the journal, notes, and reading.
- `api/agent/actions.php` holds links, research, proposals and carrying them out, and the daily update.
- `api/agent/platforms.php` holds Bluesky, LinkedIn and Threads.
- `admin/agent/index.php` is the admin page, and `admin/agent/connect.php` the platform sign-in.
- `cron/agent_watch.php` is the check that a run happened.
- `cron/lib/outreach_helpers.php` has `outreach_known_contact()`, the rule for who outreach must never reach. The outreach cron uses it too, for every lead, not only the agent's.
- `tests/Integration/Agent/AgentTest.php` covers the limits.
