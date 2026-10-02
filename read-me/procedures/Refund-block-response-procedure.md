# Hard-Block Response Procedure

When the refund system hard-blocks a refund, it locks the user's portal account and sends a notification email to `contact@argorobots.com`. It also tells the user to email you. This document walks through what to do when you receive one of those emails.

## The default policy: if they reply, unlock

The realistic threat model here is **not** "attacker drains the merchant" because refunds return money to the original customer's card, not to an attacker-controlled destination, so refunds aren't a viable theft vector. The actual scenarios the system catches are:

- **Buggy automation**: script error, accidental loop
- **Sabotage**: by a disgruntled employee or competitor
- **A confused user**: who's panicking and refunding too many things
- **False positive**: system fired on a legitimate big refund

In all four cases, a real user replies to your email and explains. A saboteur or fraudster typically doesn't.

**So the default policy is:** if the user replies to your email at all, unlock them. Don't interrogate. Don't ask for proof. Rely on bad actors self-selecting out by not responding.

You only need to do real investigation if **(a)** they reply but something obviously seems off (incoherent reply, suspicious story, asks you to do something weird), or **(b)** the trigger looks so extreme that you want a sanity check before unlocking (e.g., 50+ refunds in 5 minutes. That's almost certainly a script malfunction worth confirming).

---

## 1. Read the alert email

The email subject is `[Argo Books] Refund hard-block: company #N (Company Name)`. The body has four sections:

- **Company**: id, name, owner email, environment (sandbox vs production).
- **Refund that tripped the check**: request id, invoice number, provider, amount, user-provided reason.
- **Diagnostic**: the specific trigger, today's total refund amount, refund attempts in the last hour.
- **Next steps**: link into the admin panel.

Trigger codes:

| Trigger code | What it means |
|---|---|
| `new_account_floor` | Account is < 7 days old and refunded ≥ $5,000 today |
| `young_account_hard` | Account is 7-30 days old and refunded ≥ $10,000 today |
| `hard_threshold` | Established account (30+ days). Either 25+ refunds in the last hour, OR today's refund total ≥ 50% of last 30 days' revenue |

---

## 2. Wait for the user to reply

The user gets the same alert at roughly the same time you do, with an "Email contact@argorobots.com" button right in the refund modal. They'll typically email within minutes.

When they reply:
- **They explained their refund** → unlock (section 4). Reply to them (section 5).
- **They didn't reply within a few business days** → leave locked. No further action needed. If they ever do reach out later, unlock then.

That's the whole flow most of the time.

---

## 3. (Optional) Investigate

You don't need to do this. Skip to section 4 unless you want to dig deeper.

To see the full picture of what happened:

```sql
SELECT created_at, event_type, actor_type, payload_json
FROM refund_audit_log
WHERE company_id = <company_id>
ORDER BY created_at DESC
LIMIT 50;
```

What you'll see (most recent first):

- `account_locked`: payload has the velocity tier, reason, today_cents, hour_count
- `failed`: payload has `reason: hard_block, velocity_reason: <code>`
- `velocity_tier_assigned`
- `code_verified`: confirms the refund was authenticated (the user typed the 6-digit code from their email)
- `request_created`

That `code_verified` event is meaningful. It means whoever triggered the refund had access to the merchant's email inbox. That's a real authentication signal independent of anything else.

For broader context, the user's refund history:

```sql
SELECT id, invoice_number, amount_cents, currency, state, velocity_tier, created_at, completed_at, reason
FROM refund_requests
WHERE company_id = <company_id>
ORDER BY created_at DESC LIMIT 30;
```

For their payment history, you don't need SQL. just open the **Transactions** tab on `admin/payments` and filter by the company in the Company dropdown. Add a Status filter if you only want refunds or only completed payments.

If the established-account tier fired, also:

```sql
SELECT * FROM refund_velocity_baselines WHERE company_id = <company_id>;
```

If `daily_avg_refund_cents` or `revenue_30d_cents` look way off from reality, the baseline cron may not have run recently. Verify `refund_velocity_baseline_recompute.php` is installed and running nightly.

---

## 4. Unlock the account

Once you've decided to unlock:

1. Sign in to `www.argorobots.com/admin`.
2. Go to the 'Payment Portal' tab and find the 'Companies' section.
3. Find the locked company. Click 'Unlock'.
4. Enter a free-text reason: e.g., `User confirmed legitimate refund via email`. The reason is required and gets logged.
5. Click submit.

The user can immediately retry the original refund from the desktop.

---

## 5. (Optional) Raise their thresholds

If the user is likely to hit the threshold again, set a per-company override:

```sql
INSERT INTO refund_velocity_config
    (company_id, soft_warn_multiplier, cooling_multiplier, cooling_revenue_pct,
     hard_revenue_pct, cooling_off_minutes,
     new_account_soft_cents, new_account_cooling_cents, new_account_floor_cents,
     young_account_soft_cents, young_account_cooling_cents, young_account_floor_cents)
VALUES
    (<company_id>,
     3.0, 10.0, 0.25, 0.50, 15,
     50000, 100000, 500000,
     200000, 800000, 2000000)   -- $2k soft / $8k cooling / $20k hard
ON DUPLICATE KEY UPDATE
    young_account_soft_cents = VALUES(young_account_soft_cents),
    young_account_cooling_cents = VALUES(young_account_cooling_cents),
    young_account_floor_cents = VALUES(young_account_floor_cents);
```

To remove an override and revert to global defaults:

```sql
DELETE FROM refund_velocity_config WHERE company_id = <company_id>;
```

---

## 6. Reply to the user

### Default: they replied, you unlocked

> Hi <Name>,
>
> Thanks for reaching out. I've unlocked refunds on your account. You can retry from the desktop and it should go through now.
>
> What happened: Argo Books has an automated safety check that pauses refunds when certain patterns trip a threshold. It's tuned conservatively and often triggers on completely legitimate refunds, especially for newer accounts or larger amounts. Sorry for the friction.
>
> [Optional: I've also raised the threshold on your account so you shouldn't hit this again for similar refunds.]
>
> Let me know if anything else comes up.
>
> Evan
> Argo Books

### Rare: something seems off in their reply

Only use this if the reply itself raises real concern (incoherent, evasive, asks you to do something unusual). Otherwise default to unlocking.

> Hi <Name>,
>
> Thanks for getting back to me. Before I unlock, can you help me confirm a couple of details about the refund:
>
> 1. Was the refund for <amount> on invoice <invoice_number> something you initiated?
> 2. Has anything unusual happened with your account recently (someone else accessed it, password changed, etc.)?
>
> Once I hear back I'll get this sorted within a few business hours.
>
> Evan
> Argo Books

---

## 7. Post-incident: refine the rules?

If you've unlocked the same user twice for the same legitimate reason, the system's defaults are wrong for that account. Two paths:

1. **Per-company override**: solve it for this user only (section 5).
2. **Adjust global defaults**: solve it for everyone. Edit the row in `refund_velocity_config` where `company_id IS NULL`. Be careful: lowering defaults means more friction; raising defaults means more chance of letting buggy/malicious refund bursts through.

If you find yourself adjusting global defaults more than twice in a quarter, the system's design probably needs to change.

## 8. What the user sees

For reference, while you're handling the alert:

- **In the refund modal at the moment of hard-block**: *"This refund was flagged by our automated safety check. The system sometimes flags legitimate refunds. Please email contact@argorobots.com and we will review and process this refund within one business day. Other parts of your account continue to work normally."* Plus a clickable "Email contact@argorobots.com" button that opens their mail client with a pre-filled message.
- **In their inbox**: an email at the owner_email set in Payment Portal settings, subject `Refund paused: <amount> on invoice <number>`. Body explains it was paused by the automated safety check, that this is often a false positive, and to email us to get refunds resumed.
- **On every subsequent refund attempt**: the modal says: *"Refunds on this account are paused while our automated safety check reviews recent activity. The system sometimes flags legitimate refunds. Email contact@argorobots.com and we will resume refunds within one business day."*
- **The customer being refunded**: sees nothing different. They just don't get a refund yet.
- **Other parts of the user's account**: sending invoices, receiving payment, etc. continue to work normally. Only refunds are paused.
