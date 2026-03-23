# FLOWDESK_NEEDED_IMPROVEMENTS.md

Last updated: 2026-03-23

## Purpose
This document captures the main production-readiness improvements needed for Flowdesk before and shortly after go-live.

It is based on:
- full codebase review
- markdown/documentation review
- architecture and operations review
- automated test run result: `php artisan test` passed locally during review

---

## 1. Current Strengths

Flowdesk already has several strong foundations:

- Clear domain-oriented Laravel structure with meaningful separation across requests, vendors, expenses, assets, procurement, treasury, execution, platform, and settings.
- Strong tenant-aware design with `company_id` used broadly across business tables and policies.
- Good policy and entitlement coverage for many critical routes and module surfaces.
- Strong operational thinking already exists in the product:
  - execution health
  - payout queue
  - reconciliation desks
  - communications recovery
  - platform operations hub
- Good automation base through scheduled commands for:
  - SLA processing
  - billing automation
  - auto-billing
  - queued execution processing
  - alert summaries
  - auto-recovery
  - reminder delivery
  - communications retry
- Good short-lived caching approach for dashboard and reports metrics.
- Very strong automated test baseline for the current stage.

These are not small wins. The project is much more structured and production-minded than many apps at the same maturity level.

---

## 2. Main Improvement Areas

The following areas need attention to make Flowdesk more scalable, faster, clearer, safer, and easier to operate in production.

### 2.1 High-Priority Production Risks

#### A. Treasury auto-reconciliation will not scale well
Current issue:
- Auto-reconciliation processes statement lines in chunks, but for each line it opens a transaction and then loads candidate payout/expense records into memory before deciding a match.
- This will become slow as statement volume and tenant history grow.

Impact:
- Slow reconciliation runs
- Long lock times
- Risk of timeouts and operational backlog on large statement imports

Files:
- `app/Services/Treasury/AutoReconcileStatementService.php`

#### B. Some desk pages still do large PHP-side scanning
Current issue:
- Request Lifecycle Desk and Vendor Command Center still compute some lanes by scanning datasets in PHP using `cursor()` or `get()` and then filtering manually.
- This is acceptable for demo-sized tenants, but not for larger real tenants.

Impact:
- Slower page loads
- More memory usage
- Harder to keep p95 response time stable as data grows

Files:
- `app/Livewire/Requests/RequestLifecycleDeskPage.php`
- `app/Livewire/Vendors/VendorCommandCenterPage.php`
- parts of other operational desks should also be reviewed in the same pattern

#### C. Tenancy enforcement is not equally strong in HTTP and background contexts
Current issue:
- The `CompanyScoped` trait depends on authenticated user context.
- In console jobs, queue workers, scheduled commands, and some service-layer execution paths, that auth context may not exist.

Impact:
- Tenant safety depends too much on developer discipline in non-HTTP flows
- Higher long-term risk of cross-tenant mistakes
- Harder to guarantee safe background processing as the codebase grows

Files:
- `app/Traits/CompanyScoped.php`
- `routes/console.php`
- service-layer background jobs and schedulers broadly

#### D. Config mismatch exists in execution ops threshold display
Current issue:
- Platform Operations Hub reads `execution.ops_recovery.default_older_than_minutes`
- Actual recovery service uses `execution.ops_recovery.older_than_minutes`

Impact:
- UI can show the wrong threshold
- Operators may make decisions using incorrect platform information

Files:
- `app/Livewire/Platform/PlatformOperationsHubPage.php`
- `app/Services/Execution/ExecutionOpsAutoRecoveryService.php`

---

### 2.2 Medium-Priority Structural Risks

#### E. Security/rate-limit config is implicit instead of explicit
Current issue:
- App code reads `security.rate_limits.*`
- There is no dedicated `config/security.php`

Impact:
- Security tuning is less visible
- Production ops cannot easily review or override limits in one place
- Makes the security story less structured than it should be

Files:
- `app/Providers/AppServiceProvider.php`

#### F. Money representation is not fully consistent across docs and runtime
Current issue:
- Documentation says amounts are stored in kobo/cents
- Some runtime comments and formatting patterns behave more like major/base units
- Financial formatting is not fully standardized

Impact:
- Risk of display errors
- Confusing future development
- Dangerous for exports, reconciliations, billing, and finance reporting

Files:
- `FLOWDESK_DATABASE.md`
- `app/Services/Treasury/ImportBankStatementCsvService.php`
- `app/Livewire/Dashboard/DashboardShell.php`
- `app/Livewire/Reports/ReportsCenterPage.php`
- several views and export paths

#### G. Main README is not a real project README yet
Current issue:
- The root README is still mostly Laravel starter content with a small Flowdesk docs section.

Impact:
- Slower onboarding
- Weak deploy handoff
- Important operational knowledge is spread across many markdown files

Files:
- `README.md`

#### H. Documentation set is rich but too spread out
Current issue:
- Many markdown files exist and contain useful information, but there is no single operator/deployment index for production use.

Impact:
- Harder for new engineers or operators to know what to read first
- Higher support friction
- More risk of docs drifting apart

Files:
- all `FLOWDESK_*.md` docs in project root

---

### 2.3 Operational Readiness Gaps

#### I. Release checklist is not fully closed
Current issue:
- The final execution checklist still shows incomplete items for:
  - policy final pass
  - validation hardening completion
  - structured logging with correlation IDs
  - error tracking integration
  - backup/restore playbook
  - production validation checklist
  - UAT scripts and sign-off

Impact:
- Code may be functional, but production support posture is not fully mature yet

Files:
- `FLOWDESK_FINAL_EXECUTION_CHECKLIST.md`

#### J. Observability is not yet complete enough for production incident handling
Current issue:
- There is auditing and alert logging, which is good
- But there is not yet a full structured logging and correlation ID approach across critical flows
- Error tracking and recovery playbooks are not yet formalized

Impact:
- Harder debugging during live incidents
- Slower response when external providers or queues fail

---

## 3. Improvement Plan

This is how I intend to fix the issues if approved.

### Phase 1. Production Blockers First

#### 3.1 Refactor treasury reconciliation for scalability
Plan:
- Extract reconciliation candidate lookup into dedicated query services.
- Replace broad in-memory candidate loading with more selective DB-side filtering.
- Pre-limit candidate sets by:
  - company
  - amount window
  - date window
  - relevant execution statuses
- Reduce per-line transaction overhead where possible.
- Add performance guardrails for large statement batches.
- Add targeted indexes if query review shows missing support.

Expected result:
- Faster reconciliation
- Lower memory usage
- More predictable processing time for larger tenants

Validation:
- add focused tests for large-batch reconciliation behavior
- compare query count and runtime before/after

#### 3.2 Refactor heavy desk pages to DB-first lane building
Plan:
- Move lane-building logic out of Livewire components into dedicated read/query services.
- Push lane conditions into SQL instead of iterating in PHP where possible.
- Keep Livewire pages thin and mostly responsible for:
  - input normalization
  - authorization
  - rendering
- Apply this first to:
  - Request Lifecycle Desk
  - Vendor Command Center
- Then review similar patterns across platform and operations pages.

Expected result:
- faster desk load times
- cleaner component classes
- easier testing and maintenance

Validation:
- measure before/after render queries
- add feature tests for lane correctness after extraction

#### 3.3 Strengthen tenant isolation for jobs, commands, and services
Plan:
- Reduce reliance on auth-coupled global scoping for non-HTTP execution.
- Introduce a clearer tenant context pattern for background execution.
- Make service methods accept tenant/company scope explicitly where it matters.
- Audit scheduled commands and queue paths to ensure explicit company filtering.
- Keep `CompanyScoped` only as a convenience layer, not the main safety guarantee.

Expected result:
- safer queue and scheduler behavior
- clearer multi-tenant guarantees
- lower risk of accidental cross-tenant data access in future work

Validation:
- add regression tests for console/job execution under multi-tenant scenarios
- document approved tenant-scoping patterns for contributors

---

### Phase 2. Correctness and Configuration Clarity

#### 3.4 Fix execution recovery threshold mismatch
Plan:
- standardize on one config key
- update the UI and service to read the same setting
- add regression coverage so dashboard/platform displays cannot drift from runtime behavior again

Expected result:
- operator dashboards show real runtime thresholds

#### 3.5 Add explicit `config/security.php`
Plan:
- create a dedicated security config file
- move rate limit defaults there
- group security-related operational settings under one clear config surface
- update `.env.example` where useful

Expected result:
- better production visibility
- easier tuning and audit review

#### 3.6 Standardize money handling across code and docs
Plan:
- decide and document one canonical rule:
  - whether all persisted amounts are minor units
  - how imports are normalized
  - how formatting helpers convert for display
- introduce shared money formatting helpers/value objects so views stop formatting raw integers inconsistently
- align exports, dashboard metrics, reports, statement output, and documentation

Expected result:
- lower financial correctness risk
- easier future development
- cleaner reporting consistency

Validation:
- add tests for money formatting, imports, exports, and summary metrics

---

### Phase 3. Structure and Maintainability

#### 3.7 Thin out Livewire components further
Plan:
- continue enforcing the rule already written in project docs:
  - Livewire should not hold heavy business logic
- move large read-model assembly into dedicated services/query builders
- keep actions/services responsible for writes and heavy orchestration

Expected result:
- clearer component code
- easier onboarding
- better test isolation

#### 3.8 Improve documentation structure
Plan:
- replace the root README with a real Flowdesk README containing:
  - project overview
  - stack
  - local setup
  - queues/scheduler requirements
  - env setup
  - test command
  - deploy checklist links
- create a documentation index page that points to:
  - architecture
  - database
  - module status
  - operations guides
  - release checklist
  - production runbook

Expected result:
- much easier onboarding
- less documentation sprawl
- better deployment handoff

---

### Phase 4. Production Operations Readiness

#### 3.9 Finish observability baseline
Plan:
- add correlation IDs for critical flows:
  - webhook processing
  - payout attempts
  - billing attempts
  - reconciliation runs
- improve structured logging conventions
- identify where to wire external error tracking
- document incident drill-down flow for operators

Expected result:
- faster debugging
- better incident response

#### 3.10 Add production runbook and backup/restore guidance
Plan:
- create a production operations runbook covering:
  - deploy steps
  - required workers
  - scheduler requirements
  - cache/queue/session recommendations
  - backup cadence
  - restore verification
  - rollback procedure
- link it from README and checklist

Expected result:
- safer production rollout
- clearer support ownership

#### 3.11 Close release checklist gaps
Plan:
- convert checklist gaps into executable tasks
- close remaining:
  - policy final pass
  - validation final pass
  - observability tasks
  - production env validation
  - UAT scripts
  - sign-off items

Expected result:
- deploy confidence based on explicit readiness, not assumption

---

## 4. Recommended Order of Execution

If approved, I recommend this order:

1. treasury reconciliation scaling refactor
2. request lifecycle desk and vendor command center DB-first refactor
3. tenant isolation hardening for jobs/commands/services
4. config cleanup:
   - execution threshold mismatch
   - security config file
5. money consistency pass
6. README and docs restructuring
7. observability and production runbook
8. final checklist closure

---

## 5. What I Would Not Change

These parts are already good and should mostly be preserved:

- domain-first structure
- module entitlement approach
- policy-driven authorization direction
- platform vs tenant split
- automation mindset via scheduler/commands
- Livewire + Laravel fit for current product stage
- short-lived cache approach for dashboard/reports
- broad feature-test coverage

The goal is not to rewrite Flowdesk. The goal is to tighten the heavy paths, remove ambiguity, and make production behavior more explicit and reliable.

---

## 6. Definition of Done For This Improvement Plan

I would consider this improvement plan successful when:

- heavy desk pages no longer rely on broad PHP-side scanning
- treasury reconciliation performs predictably for larger statement loads
- tenant scoping in jobs/commands is explicit and test-backed
- security and execution config are clear and centralized
- money handling is consistent across storage, import, UI, and export
- README becomes a real Flowdesk project guide
- production runbook exists
- release checklist gaps are either closed or consciously deferred with owner/date

---

## 7. Approval Note

If this plan is approved, implementation should happen in controlled phases rather than one giant rewrite.

Preferred execution style:
- phase-by-phase
- with tests added or updated along the way
- keeping current functionality stable
- validating performance and tenant safety after each phase

