# FLOWDESK_FINAL_PRODUCTION_APPROVAL_ITEMS.md

Last updated: 2026-03-23

## Purpose
This document captures the final production-hardening items that still need approval before the last launch-readiness implementation pass begins.

It is intended to answer one question clearly:

**What still needs to be fixed before Flowdesk can be considered ready for a confident production launch and stronger scale posture?**

---

## Current Position

Flowdesk is in a much stronger state now than it was at the start of the review.

Already improved:
- request lifecycle desk heavy reads were refactored into a dedicated service
- vendor command center heavy reads were refactored into a dedicated service
- treasury auto-reconciliation was optimized to reduce repeated full-history candidate scans
- execution ops threshold config mismatch was fixed
- tenant context handling was strengthened for non-HTTP flows
- security rate-limit configuration is now explicit
- root project README was replaced with real Flowdesk project guidance
- automated tests passed after the improvement pass

Current verification status:
- `php artisan test`
- Result: `296 passed`, `1037 assertions`, `0 failed`

That said, there are still a few important launch blockers before I would call the system fully ready for a broad production-and-scale rollout.

---

## Final Approval Items

### 1. Money Normalization Decision and Implementation

#### Final decision
Flowdesk will use **major currency units everywhere in app logic**.

That means:
- no implicit `x100` conversion
- no hidden kobo/cents scaling
- integer business columns will be treated as whole major-unit values already entered by users or imports
- decimal provider-facing tables may remain major-unit decimals where gateway compatibility requires it

#### Why this still needs work
- Some docs still reflect the old minor-unit assumption
- Many pages still format raw amounts directly
- Financial display and reporting consistency is not yet fully standardized

#### What I intend to fix
- remove the old kobo/cents assumption from docs
- create one shared money formatting/parsing utility
- standardize display formatting across:
  - dashboard
  - reports
  - treasury
  - procurement
  - vendors
  - request summaries
- add tests around formatting and conversion boundaries

#### Outcome
- one clear money rule across the app
- lower financial correctness risk
- easier future maintenance

---

### 2. Observability Baseline

#### Why this still needs work
- Logging exists, but it is not yet fully structured for production incident tracing
- Critical flows do not yet have a consistent correlation ID approach

#### What I intend to fix
- add correlation IDs for:
  - HTTP requests
  - webhooks
  - scheduled commands
  - queued/background flows
- add structured log context for:
  - `company_id`
  - `actor_id`
  - `pipeline`
  - `entity_type`
  - `entity_id`
  - `correlation_id`
- tighten production logging defaults so incident review is easier

#### Outcome
- faster root-cause analysis
- easier tracing of cross-service and queue-driven flows

---

### 3. Error Tracking Integration

#### Why this still needs work
- Production exceptions are not yet wired into an external error tracking workflow
- Filesystem-only logs are not enough for reliable live incident handling

#### What I intend to fix
- wire real production exception reporting
- add environment and release tagging
- reduce noisy/non-actionable exception reporting
- scrub sensitive tenant/payment data before reporting

#### Outcome
- real-time visibility into production failures
- safer production support operations

---

### 4. Production Guardrails for Placeholder and Fallback Providers

#### Why this still needs work
- The system still supports placeholder/manual/null providers for development and staged rollout
- That is useful, but risky if production env is misconfigured

#### What I intend to fix
- add production startup validation for:
  - SMS provider configuration
  - execution provider configuration
  - webhook secrets
  - unsafe fallback/null provider usage
- fail loudly when production configuration is incomplete or unsafe

#### Outcome
- lower risk of silent production misconfiguration
- safer provider activation

---

### 5. Backup, Restore, and Recovery Runbook

#### Why this still needs work
- There is not yet a fully written, production-grade restore and recovery playbook

#### What I intend to fix
- create the actual production runbook covering:
  - backup cadence
  - restore verification
  - rollback approach
  - queue recovery steps
  - scheduler/worker expectations
  - incident triage flow

#### Outcome
- safer go-live
- better operational resilience

---

### 6. Queue and Scheduler Health Visibility

#### Why this still needs work
- Automated flows exist, but production operators still need faster visibility into whether automation is healthy

#### What I intend to fix
- add health visibility for:
  - failed jobs
  - stale queued jobs
  - scheduler heartbeat expectations
  - worker drift / stuck processing conditions

#### Outcome
- earlier detection of production automation failures
- better operational response time

---

### 7. Scale Validation Pass

#### Why this still needs work
- The app is tested for correctness, but not yet fully proven under realistic production load patterns

#### What I intend to fix
- add a repeatable benchmark/smoke validation pass for the heaviest flows:
  - request lifecycle desk
  - vendor command center
  - reports center
  - treasury reconciliation
  - queued execution processing

#### Outcome
- real evidence for performance confidence
- fewer assumptions about “scale readiness”

---

### 8. Final Tenant-Bound Background Regression Coverage

#### Why this still needs work
- Tenant context handling is stronger now
- But I still want explicit regression proof that background execution stays tenant-safe without auth context

#### What I intend to fix
- add tests for scheduled/console/background paths where tenant scope must remain explicit

#### Outcome
- stronger multi-tenant confidence in non-HTTP flows

---

### 9. Production Environment Validation Checklist Enforcement

#### Why this still needs work
- README guidance exists, but launch should rely on stricter checks than documentation alone

#### What I intend to fix
- turn production launch expectations into a strict validation gate for:
  - `APP_ENV`
  - `APP_DEBUG`
  - queue driver
  - cache driver
  - mailer
  - webhook secrets
  - provider credentials
  - required workers and scheduler

#### Outcome
- fewer preventable deployment mistakes
- cleaner go-live process

---

### 10. UAT and Release Sign-Off Pack

#### Why this still needs work
- Final launch should be based on explicit operational and stakeholder checks

#### What I intend to fix
- prepare the final operator smoke/UAT set for:
  - owner
  - finance
  - manager
  - staff
  - auditor
- produce final sign-off checklist for:
  - engineering
  - product
  - finance/process owner
  - release readiness

#### Outcome
- evidence-based release confidence
- cleaner launch approval workflow

---

## What I Consider Already Good Enough

These areas should be preserved and built on, not rewritten:
- domain structure
- module separation
- policy and entitlement direction
- platform vs tenant separation
- automation mindset through scheduler and commands
- improved desk query structure
- improved treasury reconciliation approach
- strong automated test baseline

---

## Recommended Execution Order

If approved, I recommend this order:

1. money normalization
2. observability and correlation IDs
3. error tracking and provider guardrails
4. queue and scheduler health visibility
5. backup/restore runbook and production env validation
6. scale validation and tenant-background regression coverage
7. UAT and release sign-off pack

---

## Approval Note

Approval of this document means:
- these 10 items are accepted as the final production-hardening scope
- implementation can begin in the order above
- launch readiness will be re-evaluated after this phase is completed

