# FLOWDESK_UI_GUIDE.md
Flowdesk UI must look like a modern fintech + productivity SaaS.
Design reference mix: Stripe + Ramp/Brex + Linear + Notion.

## A) Principles (non-negotiable)
1. Clean whitespace, no clutter.
2. Card-driven dashboards (Stripe/Ramp vibe).
3. Crisp tables + filters (Linear vibe).
4. Simple “workspace” layout (Notion vibe).
5. Everything should feel premium: soft borders, rounded corners, subtle shadows.

## B) Layout
### Sidebar (left, fixed)
- Icons + labels
- Sections:
  Dashboard
  Requests & Approvals
  Expenses
  Vendors
  Budgets
  Assets
  Reports
  Settings

### Topbar
- Global search (later)
- Notifications (later)
- User menu

### Content Area
- Page title + short subtext
- Main actions on right (New Request / New Asset)

## C) Dashboard (must be the selling screen)
### Row 1: Metric cards (4)
- Total Spend (This Month)
- Pending Approvals (count + amount)
- Budget Remaining (this period)
- Assets Overview (Total / Assigned / Missing)

### Row 2: Charts + lists (later stage)
- Spend by department
- Monthly trend
- Top vendors

## D) Tables (Linear-style)
Tables must include:
- Search input (top left)
- Filters (status, department, date range)
- Status badges (pill style)
- Row click opens details slide-over
- Inline row actions as kebab menu (optional)

## E) Slide-over panels (Ramp/Brex style)
Use slide-over for:
- Request details + approve/reject
- Asset details + assign/return
- Vendor details
Panel opens from right, does not navigate away.

## F) Modals (Notion simple)
Use modals for:
- Create/edit forms (small/medium)
- Confirm actions (delete, mark lost, etc.)

## G) Visual Style System
### Typography
- Use Inter font if available; otherwise system sans.
- Heading scale:
  H1: 24-28px
  H2: 18-20px
  Body: 14-16px
  Muted: 12-13px

### Colors
- Base: white background
- Borders: light gray
- Text: near-black primary, gray secondary
- Status colors:
  Pending: amber/orange
  Approved: green
  Rejected: red
  Draft: gray

### Buttons
- Primary: dark/near-black fill
- Secondary: subtle border
- Destructive: red

## H) Loading states (mandatory)
- Buttons: disable + “Saving…” while wire:loading
- Tables: skeleton rows while loading
- Cards: skeleton blocks while loading
- Prevent double submit always

## I) Accessibility + UX
- Visible focus states for inputs/buttons
- Large click targets
- Clear empty states:
  “No requests yet. Create your first request.”

## J) Implementation notes (Laravel + Livewire)
- Livewire used for SPA-like interactions where helpful:
  filters, tables, modals, slide-over panels.
- Major section navigation can remain normal routes.
