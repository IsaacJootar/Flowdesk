# Flowdesk Mail Implementation Plan (MailerSend + Unified Flow)

## Objective
Deliver a full, production‑ready mail implementation across all Flowdesk email surfaces without breaking existing behavior. This plan unifies operational mail (requests/vendors/assets/execution alerts), onboarding mail, and auth mail under one provider while preserving the current communication‑log pipeline and auditability. Queueing is used only when needed (bulk or non‑blocking workflows), not by default.

## Current State Summary (Flowdesk)
Flowdesk has three distinct email paths today:

1. **Operational/Business mail (queued)**  
   Requests, vendors, assets, and some execution alerts use communication logs and queue jobs.  
   Key entry points:  
   - `C:\xampp\htdocs\flowdesk\app\Services\RequestCommunicationLogger.php`  
   - `C:\xampp\htdocs\flowdesk\app\Services\VendorCommunicationLogger.php`  
   - `C:\xampp\htdocs\flowdesk\app\Services\AssetReminderService.php`  
   Delivery chain:  
   - Log row → `Process*CommunicationLog` job → `*DeliveryManager` → `TransactionalEmailSender` → `Mail::mailer(...)->raw(...)`

2. **Staff onboarding mail (inline)**  
   Sent immediately from `StaffOnboardingMessenger` via `TransactionalEmailSender`.  
   - `C:\xampp\htdocs\flowdesk\app\Services\StaffOnboardingMessenger.php`

3. **Auth mail (Laravel notifications)**  
   Verification and reset emails are sent through Laravel’s default notification flow.  
   - `C:\xampp\htdocs\flowdesk\app\Http\Controllers\Auth\EmailVerificationNotificationController.php`  
   - `C:\xampp\htdocs\flowdesk\app\Http\Controllers\Auth\PasswordResetLinkController.php`

## Learning From `app1` (Resend Example)
`app1` demonstrates the correct Laravel‑native shape:

- Uses provider transport via `config/mail.php` (`resend` transport).  
- Uses **Mailables** with Blade templates:
  - `C:\xampp\htdocs\app1\app\Mail\StaffWelcomeMail.php`
  - `C:\xampp\htdocs\app1\app\Mail\PatientWelcomeMail.php`
  - Templates:
    - `C:\xampp\htdocs\app1\resources\views\emails\staff-welcome.blade.php`
    - `C:\xampp\htdocs\app1\resources\views\emails\patient-welcome.blade.php`
- Uses `DB::afterCommit` to prevent “email sent for rolled‑back data.”

We will adopt the same structure in Flowdesk with MailerSend.

## Target Architecture (Production‑Ready)
1. **Single provider: MailerSend** for all email surfaces.  
2. **Mailables + Blade templates** for all Flowdesk email bodies.  
3. **Keep the communication‑log pipeline** as the source of truth.  
4. **After‑commit guarantees** for any email that depends on newly created rows.  
5. **Provider webhook integration** to capture delivered/bounced/blocked outcomes.  
6. **Queue only when needed** (bulk or non‑blocking flows). Critical user‑facing emails (e.g. password reset) must still respond quickly and reliably.

## Implementation Phases

### Phase 1 — Provider Integration (No Behavior Change)
Goal: add MailerSend transport while keeping existing behavior intact.

- Add `mailersend` transport config in `config/mail.php`.
- Add `services.mailersend` config in `config/services.php`.
- Add `.env.example` keys:
  - `MAILERSEND_API_KEY`
  - `MAILERSEND_DOMAIN`
  - `MAIL_MAILER=mailersend`
  - `MAIL_TRANSACTIONAL_MAILER=mailersend`
- Update production guardrails to validate MailerSend config (replace Resend‑specific checks).
- Keep existing `TransactionalEmailSender` working; do not break the mail pipeline.

Outcome: same behavior, just switching provider.

### Phase 2 — Unified Mailables + Templates
Goal: eliminate raw string email bodies and move to Blade templates.

1. Create Flowdesk mailables:
   - `RequestStatusMail`
   - `VendorInvoiceReminderMail`
   - `AssetReminderMail`
   - `ExecutionAlertMail`
   - `StaffWelcomeMail`
   - Optional: `AuthEmailVerificationMail`, `AuthResetPasswordMail` (only if we want branding)

2. Add Blade templates in `resources/views/emails/`
   - Start from `app1` templates and adapt:
     - Use Flowdesk branding (logo colors, headings)
     - Replace Cureva wording with Flowdesk wording
     - Keep the layout, spacing, and table‑based HTML structure

3. Update Flowdesk delivery managers and onboarding messenger to send Mailables instead of `Mail::raw(...)`.

Outcome: proper HTML mail, maintainable templates, consistent branding.

### Phase 3 — Queue & After‑Commit Hardening
Goal: ensure consistency and reduce risk under production load while avoiding unnecessary queueing.

- Ensure all mail dispatches happen after DB commit:
  - Use `DB::afterCommit` in logger services when creating communication logs
  - For onboarding, send after commit; queue only if the action is non‑blocking for the UI
- Queue policy:
  - **Inline**: password reset, verification, and immediate onboarding feedback
  - **Queued**: bulk reminders, scheduled communications, retry/recovery jobs
- Optional queue split if needed at scale:
  - `mail-default` for routine operational mail
  - `mail-recovery` for retry processing

Outcome: no “ghost mails” from rolled‑back transactions; predictable throughput.

### Phase 4 — Delivery Event Webhooks (Production Observability)
Goal: know what happened *after* sending.

- Add MailerSend webhook endpoint.
- Persist delivery events onto communication logs:
  - delivered, opened, clicked, bounced, blocked, spam complaint
- Update Communications Recovery Desk to show provider delivery outcome.

Outcome: real production feedback loop, not just “we tried.”

## Template Mapping (From `app1` to Flowdesk)
We will copy the layout structure from these templates:
- `C:\xampp\htdocs\app1\resources\views\emails\staff-welcome.blade.php`
- `C:\xampp\htdocs\app1\resources\views\emails\patient-welcome.blade.php`

Flowdesk equivalents:
- `resources/views/emails/staff-welcome.blade.php`  
  Replace “Cureva” with “Flowdesk”, and map user fields to Flowdesk’s `User` model.
- `resources/views/emails/request-update.blade.php`  
  Derived from staff template layout, adapted for request status update.
- `resources/views/emails/vendor-reminder.blade.php`  
  Derived from staff template layout, adapted for vendor invoice info.
- `resources/views/emails/asset-reminder.blade.php`  
  Derived from staff template layout, adapted for asset reminder info.
- `resources/views/emails/execution-alert.blade.php`  
  Derived from staff template layout, adapted for execution alert summary.

## Important Nuances & Solutions

- **Three distinct mail flows today**  
  Solution: unify provider and rendering across all flows, while keeping the communication‑log pipeline for business mail and leaving auth mail as standard Laravel notifications.

- **Inline onboarding mail**  
  Solution: queue onboarding mail with `afterCommit` to avoid ghost sends.

- **Raw string emails**  
  Solution: convert to Mailables + Blade templates.

- **Provider‑specific headers (Resend)**  
  Solution: remove Resend‑specific headers and use provider‑agnostic metadata.

- **Delivery vs send**  
  Solution: implement MailerSend webhooks to persist delivery outcomes.

- **Queue pressure under scale**  
  Solution: isolate queues and assign worker priorities.

## Acceptance Criteria
1. All Flowdesk emails send through MailerSend.
2. All Flowdesk emails use Mailables with HTML templates.
3. No mail is sent before DB commit.
4. Password reset and verification flows continue to work.
5. Communications Recovery Desk shows delivery outcomes (sent/delivered/bounced).
6. All tests pass; no regressions in communication logs.

## Rollout Strategy
1. Implement Phase 1 and deploy to staging.
2. Add Phase 2 Mailables and confirm output with sample data.
3. Enable Phase 3 queue split and after‑commit logic.
4. Enable Phase 4 webhooks and confirm event ingestion.
5. Run full test suite and staging UAT.

## Required Inputs From You
- MailerSend domain and API key
- Preferred “From” address
- Decision on whether auth mail should use Flowdesk branded templates or default Laravel

---
When you approve this plan, I will execute it exactly in this order and keep changes scoped to avoid breaking any existing behavior.
