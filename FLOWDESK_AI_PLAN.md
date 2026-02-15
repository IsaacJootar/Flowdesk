# FLOWDESK_AI_PLAN.md
Flowdesk AI Plan (Phase 1 → Phase 3)
Target VPS: 4 CPU / 6GB RAM / 120GB SSD
Goal: add high-ROI AI features without slowing the app.

---

## 0) GLOBAL RULES (NON-NEGOTIABLE)

### Tenancy & Security
- All AI outputs and indexes MUST be scoped to `company_id`.
- Never process or store another company’s documents in the same record.
- Any stored file must be protected (use private storage) and served via authorized routes.

### Performance
- AI work MUST run in Laravel queues (no heavy processing in web requests).
- Queue jobs must be idempotent (safe to retry).
- Use small CPU-friendly models; avoid large LLMs on this VPS.

### Trust & UX
- AI outputs are “suggestions” — users must confirm before finalizing.
- Always display confidence and “Edit/Confirm” actions.
- Provide clear errors: “Couldn’t extract total amount. Please enter manually.”

### Audit & Logging
- Log all AI actions to `activity_logs`:
  - receipt.ocr.started / receipt.ocr.completed / receipt.ocr.failed
  - expense.categorized (auto)
  - embeddings.updated
  - anomaly.flagged (future)

---

## 1) PHASE 1 — RECEIPT OCR AUTOFILL (MUST SHIP FIRST)

### User Value
- Upload receipt → auto-extract:
  - vendor name (best guess)
  - total amount
  - date
  - invoice/receipt number (optional)
- Pre-fill expense form → finance only confirms

### Tech Choice (Free + CPU-friendly)
- OCR: Tesseract
- Image cleanup: OpenCV (deskew, denoise, threshold)

### Flowdesk Integration Points
- Expenses module → Receipt upload
- After upload, show button: **“Extract details”**
- Automatically run OCR in background queue when receipt uploaded (recommended)

### Database Changes (Phase 1)
Add fields to `expense_receipts` (or create a new table `receipt_extractions` if you prefer more normalization).

Recommended additions to `expense_receipts`:
- extracted_text (LONGTEXT) nullable
- extracted_vendor_name (string) nullable
- extracted_total_amount (bigint) nullable  // in kobo/cents
- extracted_currency_code (string) nullable // default company currency
- extracted_receipt_date (date) nullable
- extracted_invoice_number (string) nullable
- extraction_confidence (decimal 5,2) nullable (0-100)
- extraction_status (string) default 'pending'  // pending|processing|completed|failed
- extraction_error (text) nullable

Indexes:
- company_id (if present via expense relation) and expense_id

### Storage Rules
- Store receipt files in private storage:
  storage/app/private/receipts/{company_id}/{expense_id}/...
- Serve via controller that checks:
  - auth user company_id == expense.company_id
  - user has permission to view expense

### Queue Jobs (Phase 1)
Create job:
- `ProcessReceiptOCRJob(receipt_id)`

Job responsibilities:
1) Mark extraction_status=processing
2) Load receipt file path
3) Preprocess image/PDF:
   - If image: OpenCV: grayscale → denoise → threshold → deskew
   - If PDF: convert first page to image (if needed) then preprocess
4) Run Tesseract OCR
5) Parse extracted text for:
   - total amount (choose highest/most-likely "TOTAL" value)
   - date (most likely purchase date)
   - vendor name (top line(s) heuristic)
   - invoice number (regex)
6) Save extracted_* fields
7) Save confidence score (basic heuristic)
8) Mark extraction_status=completed
9) Activity log events for started/completed/failed

### UI Requirements (Phase 1)
Expense detail slide-over/page:
- Receipts list with:
  - file preview/download (authorized)
  - extraction status pill: Pending / Processing / Completed / Failed
  - “Extract details” action (if pending/failed)
  - Show extracted fields (vendor, total, date, invoice) + confidence
  - Button: “Apply to Expense”
    - copies extracted_total_amount → expense.amount (if empty or user confirms)
    - extracted_vendor_name → tries matching vendor list (suggest vendor)
    - extracted_receipt_date → expense_date suggestion

Loading states:
- show spinner while extraction job dispatching
- poll status (or refresh button)

---

## 2) PHASE 1 — AUTO-CATEGORIZATION (LIGHT NLP)

### User Value
- Automatically categorize:
  - expenses (fuel, repairs, office, travel, maintenance, utilities, logistics)
  - requests (purchase/payment/travel/expense) (optional)

### Tech Choice (Free)
- Start with rules + keyword patterns (fastest)
- Add spaCy later for better entity extraction if desired

### Database Changes
Add to `expenses`:
- category (string) nullable
- category_confidence (decimal 5,2) nullable
- categorized_by (enum: 'auto'|'manual') default 'auto'

(Or store in separate `expense_ai_metadata` table if you prefer.)

### Categorization Logic (Phase 1)
- Use keyword dictionaries:
  - Fuel: fuel, diesel, petrol, filling station
  - Repairs: repair, maintenance, fix, spare parts, mechanic
  - Office: stationery, printer, paper, office supplies
  - Travel: hotel, flight, transport, ticket, trip
  - Utilities: electricity, internet, airtime, data
- Look at: expense.title + description + OCR text (if any)

Confidence:
- 90 if exact match with multiple keywords
- 60 if single keyword match
- 30 if weak match

### Queue Job
- `CategorizeExpenseJob(expense_id)`
Trigger:
- on expense created/updated
- after OCR completes (since OCR text improves accuracy)

UI:
- show category pill on expense list + details
- allow manual override (dropdown)
- log: expense.categorized (auto) + expense.category.updated (manual)

---

## 3) PHASE 1.5 — SEMANTIC SEARCH (PREMIUM FEEL)

### User Value
- Search by meaning, not exact words:
  - “fuel for trucks” finds “diesel for fleet”
  - “printer repair” finds “HP servicing”

### Tech Choice (Free)
- Sentence-Transformers (small embeddings model)
- FAISS for vector search

### Data Scope
Index at least:
- vendors (name, notes)
- expenses (title, description, vendor name, category)
- requests (title, description, items)
Optionally: assets (name, serial, notes)

### Storage Strategy
Option A (recommended for simplicity):
- Store embeddings in DB as BLOB or JSON + maintain FAISS index per company on disk.
Option B:
- Store all embeddings in files and keep minimal pointers in DB.

Recommended DB table:
`ai_embeddings`
- id
- company_id
- entity_type (vendor|expense|request|asset)
- entity_id
- text_hash (for change detection)
- embedding (BLOB/JSON)
- created_at, updated_at
Indexes:
- company_id, entity_type, entity_id unique

FAISS index files:
storage/app/private/ai/indexes/{company_id}/faiss.index

### Queue Jobs
- `UpdateEmbeddingsJob(entity_type, entity_id)`
- `RebuildCompanyIndexJob(company_id)` (weekly or manual)

UI:
- Add global search bar (topbar) later
- For Phase 1.5: add “Smart search” on Expenses and Vendors pages

---

## 4) PHASE 2 — FORECASTING (BUDGET INTELLIGENCE)

### User Value
- Predict:
  - next month spend
  - department burn rate
  - vendor trend

### Tech Choice (Free)
- Prophet (time-series forecasting)

Execution:
- nightly job to generate forecasts
- store results in table `spend_forecasts`

UI:
- dashboard card “Projected spend next month”
- chart pages (Phase 2)

---

## 5) PHASE 2/3 — ANOMALY FLAGS (FRAUD-LIKE DETECTION)

### User Value
- Flag:
  - duplicated receipts
  - weird spikes
  - unusual vendor activity

Tech:
- PyOD (outlier detection)
- plus rule-based checks:
  - same amount repeated N times
  - same receipt number reused
  - new vendor + large spend immediately

Storage:
`anomaly_flags`
- company_id
- entity_type
- entity_id
- reason
- severity (low/med/high)
- status (open|reviewed|dismissed)

UI:
- Finance “Alerts” page (Phase 3)

---

## 6) IMPLEMENTATION ORDER (RECOMMENDED)

Phase 1:
1) Receipt OCR Autofill (Tesseract + OpenCV + queues)
2) Auto-categorization (rules first)

Phase 1.5:
3) Semantic search (Sentence-Transformers + FAISS)

Phase 2:
4) Forecasting (Prophet)

Phase 3:
5) Anomaly flags (PyOD + rules)
6) AI assistant chat (use hosted API; do NOT run LLM locally on this VPS)

---

## 7) CODEX TASK PROMPTS (HOW TO EXECUTE)

When ready to implement AI, use this in Codex:

A) OCR Step
"Read AGENTS.md + FLOWDESK_AI_PLAN.md and implement Phase 1 Receipt OCR Autofill only. Use queues and secure storage. Add UI in expense receipts panel. Stop and report."

B) Categorization Step
"Read AGENTS.md + FLOWDESK_AI_PLAN.md and implement Phase 1 Auto-categorization only. Trigger jobs after OCR and expense create/update. Stop and report."

C) Semantic Search Step
"Read AGENTS.md + FLOWDESK_AI_PLAN.md and implement Phase 1.5 Semantic Search only. Stop and report."

---

## 8) DEFINITION OF DONE (PHASE 1 AI)

Receipt OCR done when:
- upload receipt → extraction job runs
- extracted total/date/vendor shown in UI
- user can apply to expense
- failures handled gracefully
- all company scoped
- activity logs recorded

Categorization done when:
- new expense gets category automatically
- user can override
- audit log records the change

---

END OF DOCUMENT
