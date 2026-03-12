<?php

namespace App\Services\AI;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Service for intelligent analysis of expense receipts using deterministic and AI-powered extraction.
 *
 * This service processes uploaded receipt files (images, PDFs, text) to extract key expense fields
 * such as vendor, date, amount, reference, category, and title. It combines deterministic pattern
 * matching with optional AI model assistance via Ollama for improved accuracy.
 *
 * Features:
 * - Supports multiple file types with OCR and PDF text extraction
 * - Vendor resolution from a provided vendor list
 * - Confidence scoring and fallback mechanisms
 * - Environment status checking for required binaries
 */
class ExpenseReceiptIntelligenceService
{
    private const MAX_FILES = 4;
    private const MODEL_MIN_CONFIDENCE = 55;

    /** @var array<string, bool> */
    private static array $ollamaReachabilityCache = [];

    /**
     * Checks the availability of required external tools for text extraction.
     *
     * @return array{image_ocr_available: bool, pdf_text_available: bool}
     */
    public function environmentStatus(): array
    {
        return [
            'image_ocr_available' => $this->binaryExists('tesseract'),
            'pdf_text_available' => $this->binaryExists('pdftotext'),
        ];
    }

    /**
     * Analyzes a batch of receipt files to extract expense fields.
     *
     * This method combines deterministic extraction with optional AI model assistance.
     * It processes up to MAX_FILES files, extracting text via OCR/PDF tools or filename hints.
     * Deterministic extraction provides a safe fallback, while model extraction enhances accuracy
     * when Ollama is available and confident.
     *
     * Flow:
     * 1) Always run deterministic extraction (safe fallback).
     * 2) Try model extraction when Ollama is reachable.
     * 3) Use model fields only when model output is valid + confident.
     *
     * @param array $files Uploaded file objects with getClientOriginalName() and getMimeType() methods
     * @param array $vendors Array of vendor data with 'id' and 'name' keys for resolution
     * @return array{
     *     summary: string,
     *     fields: array{vendor_id: int|null, expense_date: string|null, amount: int|null, title: string|null, reference: string|null, category: string|null},
     *     confidence: int,
     *     signals: array<array{source: string, message: string}>,
     *     engine: string,
     *     ai_model: string|null,
     *     fallback_used: bool
     * }
     */
    public function analyzeBatch(array $files, array $vendors = []): array
    {
        $status = $this->environmentStatus();
        $prepared = $this->prepareInputs($files, $status);
        $deterministic = $this->analyzeDeterministic($prepared['receipts'], $vendors, $prepared['signals']);
        $model = $this->analyzeWithModel($prepared['receipts'], $vendors);

        $fields = $deterministic['fields'];
        $signals = $deterministic['signals'];
        $confidence = (int) $deterministic['confidence'];
        $engine = 'deterministic';
        $fallbackUsed = false;
        $aiModel = null;

        if ($model !== null) {
            $signals = array_merge($signals, $model['signals']);
            $aiModel = $model['model'];
            $fallbackUsed = true;

            if ($model['valid']) {
                foreach (['vendor_id', 'expense_date', 'amount', 'title', 'reference', 'category'] as $key) {
                    $candidate = $model['fields'][$key] ?? null;
                    if ($candidate !== null && $candidate !== '') {
                        $fields[$key] = $candidate;
                    }
                }
                $engine = 'model_assisted';
                $confidence = max($confidence, (int) $model['confidence']);
            }
        }

        $fieldCount = count(array_filter([
            $fields['vendor_id'] !== null,
            $fields['expense_date'] !== null,
            $fields['amount'] !== null,
            $fields['title'] !== null,
            $fields['reference'] !== null,
            $fields['category'] !== null,
        ]));

        return [
            'summary' => $fieldCount > 0
                ? sprintf('Receipt Agent extracted %d field signal(s). Review and apply before posting.', $fieldCount)
                : 'Receipt Agent could not extract reliable fields from current files.',
            'fields' => $fields,
            'confidence' => $fieldCount > 0 ? $confidence : 0,
            'signals' => $signals,
            'engine' => $engine,
            'ai_model' => $aiModel,
            'fallback_used' => $fallbackUsed,
        ];
    }

    /**
     * Prepares input files for analysis by extracting text and generating signals.
     *
     * Processes each file to extract readable text via OCR/PDF tools or filename hints,
     * combines filename and extracted text, and generates warning signals for missing tools
     * or empty content.
     *
     * @param array $files Uploaded file objects
     * @param array $status Environment status from environmentStatus()
     * @return array{receipts: array<array{source: string, mime: string, combined: string}>, signals: array<array{source: string, message: string}>}
     */
    private function prepareInputs(array $files, array $status): array
    {
        $signals = [];
        $receipts = [];

        foreach (array_slice($files, 0, self::MAX_FILES) as $file) {
            if (! $file || ! method_exists($file, 'getClientOriginalName')) {
                continue;
            }

            $source = (string) $file->getClientOriginalName();
            $mime = strtolower((string) ((method_exists($file, 'getMimeType') ? $file->getMimeType() : '') ?: ''));
            $filenameText = $this->normalizeText(pathinfo($source, PATHINFO_FILENAME));
            $ocrText = $this->extractText($file, $status);
            $combined = trim($filenameText.' '.$ocrText);

            if (str_starts_with($mime, 'image/') && ! $status['image_ocr_available']) {
                $signals[] = ['source' => $source, 'message' => 'Image OCR unavailable (tesseract missing); using filename hints.'];
            }
            if ($mime === 'application/pdf' && ! $status['pdf_text_available']) {
                $signals[] = ['source' => $source, 'message' => 'PDF text extraction unavailable (pdftotext missing); using filename hints.'];
            }
            if ($combined === '') {
                $signals[] = ['source' => $source, 'message' => 'No readable text extracted from this file.'];
            }

            $receipts[] = ['source' => $source, 'mime' => $mime, 'combined' => $combined];
        }

        return ['receipts' => $receipts, 'signals' => $signals];
    }

    /**
     * Performs deterministic extraction of expense fields from receipt text.
     *
     * Uses pattern matching and heuristics to extract vendor, amount, date, reference,
     * category, and title without requiring AI models. Provides a reliable fallback.
     *
     * @param array $receipts Prepared receipt data from prepareInputs()
     * @param array $vendors Vendor list for resolution
     * @param array $baseSignals Initial signals from preparation
     * @return array{fields: array, confidence: int, signals: array}
     */
    private function analyzeDeterministic(array $receipts, array $vendors, array $baseSignals): array
    {
        $signals = $baseSignals;
        $vendorHits = [];
        $amountHits = [];
        $dateHits = [];
        $referenceHits = [];
        $categoryHits = [];
        $titleCandidates = [];

        foreach ($receipts as $receipt) {
            $combined = (string) ($receipt['combined'] ?? '');
            $source = (string) ($receipt['source'] ?? 'Receipt');
            if ($combined === '') {
                continue;
            }

            $signals[] = ['source' => $source, 'message' => 'Parsed receipt filename and OCR hints for extraction.'];
            $titleCandidates[] = $this->buildTitleFromFilename($source);

            $vendor = $this->resolveVendorByText($combined, $vendors);
            if ($vendor !== null) {
                $vendorHits[] = $vendor;
            }

            $amount = $this->extractAmount($combined);
            if ($amount !== null) {
                $amountHits[] = $amount;
            }

            $date = $this->extractDate($combined);
            if ($date !== null) {
                $dateHits[] = $date;
            }

            $reference = $this->extractReference($combined);
            if ($reference !== null) {
                $referenceHits[] = $reference;
            }

            $category = $this->suggestCategory($combined);
            if ($category !== null) {
                $categoryHits[] = $category;
            }
        }

        $fields = [
            'vendor_id' => $this->modeInt($vendorHits),
            'expense_date' => $this->modeString($dateHits),
            'amount' => $amountHits !== [] ? max($amountHits) : null,
            'title' => $this->modeString(array_values(array_filter($titleCandidates))),
            'reference' => $this->modeString($referenceHits),
            'category' => $this->modeString($categoryHits),
        ];

        return [
            'fields' => $fields,
            'confidence' => $this->calculateConfidence($fields),
            'signals' => $signals,
        ];
    }

    /**
     * Attempts AI-powered extraction using Ollama models.
     *
     * Retrieves AI runtime profile, checks Ollama availability, constructs a structured prompt
     * with receipt data and vendor list, sends to Ollama API, and processes the JSON response.
     * Validates model output against confidence threshold and normalizes extracted fields.
     *
     * Returns null if Ollama is not configured as provider.
     * Returns failure result if Ollama unreachable, request fails, or response invalid.
     * Returns success result with normalized fields if output is valid and confident.
     *
     * @param array $receipts Prepared receipt data from prepareInputs()
     * @param array $vendors Vendor list for resolution
     * @return array{
     *     valid: bool,
     *     model: string,
     *     confidence: int,
     *     fields: array,
     *     signals: array<array{source: string, message: string}>
     * }|null Model extraction results or null
     */
    private function analyzeWithModel(array $receipts, array $vendors): ?array
    {
        /** @var array<string, mixed> $profile */
        $profile = app(AiRuntimeProfileService::class)->profile();
        $runtime = (array) ($profile['runtime'] ?? []);
        $models = (array) ($profile['models'] ?? []);

        if (strtolower((string) ($runtime['provider'] ?? '')) !== 'ollama') {
            return null;
        }

        $baseUrl = rtrim((string) ($runtime['base_url'] ?? ''), '/');
        $model = trim((string) ($models['primary'] ?? ''));
        $timeout = max(3, min(20, (int) ($runtime['request_timeout_seconds'] ?? 25)));

        if ($baseUrl === '' || $model === '') {
            return null;
        }

        if (! $this->canReachOllama($baseUrl, $timeout)) {
            return [
                'valid' => false,
                'model' => $model,
                'confidence' => 0,
                'fields' => $this->emptyFields(),
                'signals' => [['source' => 'Flow Agent', 'message' => 'Ollama runtime unavailable; deterministic fallback in use.']],
            ];
        }

        $inputs = [];
        foreach ($receipts as $receipt) {
            $combined = trim((string) ($receipt['combined'] ?? ''));
            if ($combined === '') {
                continue;
            }
            $inputs[] = [
                'source' => (string) ($receipt['source'] ?? 'Receipt'),
                'mime' => (string) ($receipt['mime'] ?? ''),
                'text' => mb_substr($combined, 0, 4000),
            ];
        }

        if ($inputs === []) {
            return [
                'valid' => false,
                'model' => $model,
                'confidence' => 0,
                'fields' => $this->emptyFields(),
                'signals' => [['source' => 'Flow Agent', 'message' => 'No text available for model extraction; deterministic fallback in use.']],
            ];
        }

        $vendorNames = array_values(array_map(fn (array $vendor): string => (string) ($vendor['name'] ?? ''), array_slice($vendors, 0, 80)));
        $payload = [
            'task' => 'Extract receipt fields for expense entry',
            'rules' => ['return_json_only' => true, 'date_format' => 'YYYY-MM-DD', 'amount_integer_ngn' => true, 'null_for_unknown' => true],
            'vendors' => $vendorNames,
            'receipts' => $inputs,
            'schema' => [
                'vendor_name' => 'string|null',
                'expense_date' => 'string|null',
                'amount' => 'int|null',
                'reference' => 'string|null',
                'category' => 'string|null',
                'title' => 'string|null',
                'confidence' => 'int 0..100',
                'notes' => 'array<string>',
            ],
        ];

        $prompt = "Return exactly one JSON object only, no markdown.\n".json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        try {
            $response = Http::timeout($timeout)->acceptJson()->post($baseUrl.'/api/generate', [
                'model' => $model,
                'prompt' => $prompt,
                'stream' => false,
                'options' => ['temperature' => 0.1],
            ]);
        } catch (Throwable) {
            return [
                'valid' => false,
                'model' => $model,
                'confidence' => 0,
                'fields' => $this->emptyFields(),
                'signals' => [['source' => 'Flow Agent', 'message' => 'Model request failed; deterministic fallback in use.']],
            ];
        }

        if (! $response->successful()) {
            return [
                'valid' => false,
                'model' => $model,
                'confidence' => 0,
                'fields' => $this->emptyFields(),
                'signals' => [['source' => 'Flow Agent', 'message' => 'Model response error; deterministic fallback in use.']],
            ];
        }

        $decoded = $this->decodeJsonObject((string) ($response->json('response') ?? ''));
        if (! is_array($decoded)) {
            return [
                'valid' => false,
                'model' => $model,
                'confidence' => 0,
                'fields' => $this->emptyFields(),
                'signals' => [['source' => 'Flow Agent', 'message' => 'Model output is not valid JSON; deterministic fallback in use.']],
            ];
        }

        $fields = $this->normalizeModelFields($decoded, $vendors);
        $confidence = $this->normalizeConfidence($decoded['confidence'] ?? 0);
        $notes = array_values(array_filter(array_map(fn ($note): string => trim((string) $note), (array) ($decoded['notes'] ?? []))));
        $valid = $this->countStructuredFields($fields) > 0 && $confidence >= self::MODEL_MIN_CONFIDENCE;

        $signals = [['source' => 'Flow Agent', 'message' => 'Model extraction completed using '.$model.'.']];
        foreach (array_slice($notes, 0, 2) as $note) {
            if ($note !== '') {
                $signals[] = ['source' => 'Flow Agent', 'message' => $note];
            }
        }
        if (! $valid) {
            $signals[] = ['source' => 'Flow Agent', 'message' => 'Model output low-confidence or incomplete; deterministic fallback remains primary.'];
        }

        return [
            'valid' => $valid,
            'model' => $model,
            'confidence' => $confidence,
            'fields' => $fields,
            'signals' => $signals,
        ];
    }

    /**
     * Extracts readable text from uploaded files using OCR or PDF tools.
     *
     * Supports text files, images (via Tesseract OCR), and PDFs (via pdftotext).
     * Falls back to filename-only extraction if tools are unavailable.
     *
     * @param mixed $file Uploaded file object
     * @param array|null $status Environment status from environmentStatus()
     * @return string Normalized extracted text
     */
    private function extractText($file, ?array $status = null): string
    {
        if (! method_exists($file, 'getRealPath') || ! method_exists($file, 'getMimeType')) {
            return '';
        }
        $path = (string) ($file->getRealPath() ?: '');
        $mime = strtolower((string) ($file->getMimeType() ?: ''));
        $status = $status ?? $this->environmentStatus();

        if ($path === '' || ! is_file($path)) {
            return '';
        }
        if ($mime === 'text/plain') {
            return $this->normalizeText((string) @file_get_contents($path));
        }
        if (str_starts_with($mime, 'image/') && $status['image_ocr_available']) {
            return $this->runProcess(['tesseract', $path, 'stdout', '--dpi', '300']);
        }
        if ($mime === 'application/pdf' && $status['pdf_text_available']) {
            return $this->runProcess(['pdftotext', $path, '-']);
        }

        return '';
    }

    private function resolveVendorByText(string $text, array $vendors): ?int
    {
        $normalizedText = $this->normalizeText($text);
        if ($normalizedText === '' || $vendors === []) {
            return null;
        }
        $bestVendorId = null;
        $bestScore = 0;

        foreach ($vendors as $vendor) {
            $id = (int) ($vendor['id'] ?? 0);
            $name = $this->normalizeText((string) ($vendor['name'] ?? ''));
            if ($id <= 0 || $name === '') {
                continue;
            }
            if (str_contains($normalizedText, $name)) {
                $score = strlen($name);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestVendorId = $id;
                }
            }
        }

        return $bestVendorId;
    }

    private function resolveVendorFromName(?string $vendorName, array $vendors): ?int
    {
        $needle = $this->normalizeText((string) $vendorName);
        if ($needle === '' || $vendors === []) {
            return null;
        }
        $bestVendorId = null;
        $bestScore = 0;

        foreach ($vendors as $vendor) {
            $id = (int) ($vendor['id'] ?? 0);
            $name = $this->normalizeText((string) ($vendor['name'] ?? ''));
            if ($id <= 0 || $name === '') {
                continue;
            }
            if ($name === $needle || str_contains($name, $needle) || str_contains($needle, $name)) {
                $score = strlen($name);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestVendorId = $id;
                }
            }
        }

        return $bestVendorId;
    }

    private function extractAmount(string $text): ?int
    {
        $normalized = $this->normalizeText($text);
        if ($normalized === '') {
            return null;
        }

        preg_match_all('/(?:\bngn|\bnaira|\bn\b|\x{20A6})\s*[:=]?\s*([0-9][0-9,]*(?:\.[0-9]{1,2})?)/iu', $normalized, $matches);
        $candidates = $matches[1] ?? [];
        if ($candidates === []) {
            preg_match_all('/(?:\btotal\b|\bamount\b|\bpaid\b|\bpayment\b|\bdebit\b|\bcharge\b|\bsum\b)\s*(?:due|paid)?\s*[:=]?\s*([0-9][0-9,]*(?:\.[0-9]{1,2})?)/i', $normalized, $keywordMatches);
            $candidates = $keywordMatches[1] ?? [];
        }
        if ($candidates === []) {
            preg_match_all('/\b([0-9]{2,3}(?:,[0-9]{3})+(?:\.[0-9]{1,2})?)\b/', $normalized, $commaSeparated);
            $candidates = $commaSeparated[1] ?? [];
        }
        if ($candidates === []) {
            preg_match_all('/\b([0-9]{4,}(?:\.[0-9]{1,2})?)\b/', $normalized, $plainLarge);
            $candidates = $plainLarge[1] ?? [];
        }

        $amounts = [];
        foreach ($candidates as $candidate) {
            $parsed = $this->parseAmountCandidate((string) $candidate);
            if ($parsed !== null) {
                $amounts[] = $parsed;
            }
        }

        return $amounts === [] ? null : max($amounts);
    }

    private function extractDate(string $text): ?string
    {
        $normalized = $this->normalizeText($text);
        if ($normalized === '') {
            return null;
        }
        foreach (['/\b([0-9]{4}-[0-9]{2}-[0-9]{2})\b/', '/\b([0-9]{2}\/[0-9]{2}\/[0-9]{4})\b/', '/\b([0-9]{2}-[0-9]{2}-[0-9]{4})\b/'] as $pattern) {
            if (! preg_match($pattern, $normalized, $match)) {
                continue;
            }
            try {
                return Carbon::parse((string) ($match[1] ?? ''))->toDateString();
            } catch (Throwable) {
                // Continue trying other patterns.
            }
        }

        return null;
    }

    private function extractReference(string $text): ?string
    {
        $normalized = $this->normalizeText($text);
        if ($normalized === '') {
            return null;
        }
        if (preg_match('/\b(?:ref|reference|txn|trx|receipt|invoice)\s*[:#-]?\s*([a-z0-9-]{4,})\b/i', $normalized, $match)) {
            return strtoupper((string) ($match[1] ?? ''));
        }

        return null;
    }

    private function suggestCategory(string $text): ?string
    {
        $normalized = $this->normalizeText($text);
        if ($normalized === '') {
            return null;
        }
        $map = [
            'fuel' => ['fuel', 'diesel', 'petrol', 'generator'],
            'travel' => ['travel', 'flight', 'hotel', 'transport', 'uber', 'bolt', 'taxi'],
            'utilities' => ['electricity', 'internet', 'airtime', 'water', 'utility'],
            'meals' => ['food', 'meal', 'catering', 'lunch', 'dinner'],
            'office_supplies' => ['stationery', 'printer', 'paper', 'toner', 'office'],
            'software' => ['subscription', 'license', 'saas', 'software', 'hosting'],
            'maintenance' => ['repair', 'maintenance', 'service', 'spare'],
        ];
        foreach ($map as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($normalized, $keyword)) {
                    return $category;
                }
            }
        }

        return null;
    }

    private function buildTitleFromFilename(string $filename): ?string
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $clean = trim(preg_replace('/[_-]+/', ' ', (string) $base) ?? '');

        return $clean !== '' ? Str::title(mb_substr($clean, 0, 120)) : null;
    }

    private function normalizeText(string $value): string
    {
        $lower = Str::of($value)->lower()->replaceMatches('/[^\x{20A6}a-z0-9\-\.\,\/\s]/u', ' ')->value();

        return trim(preg_replace('/\s+/', ' ', $lower) ?? $lower);
    }

    private function parseAmountCandidate(string $candidate): ?int
    {
        $clean = str_replace(',', '', trim($candidate));
        if ($clean === '' || ! preg_match('/^[0-9]+(?:\.[0-9]{1,2})?$/', $clean)) {
            return null;
        }
        $digitsOnly = preg_replace('/\D/', '', $clean) ?? '';
        if ($digitsOnly === '' || (str_starts_with($digitsOnly, '0') && strlen($digitsOnly) >= 8)) {
            return null;
        }
        $value = (float) $clean;
        if ($value <= 0) {
            return null;
        }
        $rounded = (int) round($value);

        return ($rounded < 50 || $rounded > 500000000) ? null : $rounded;
    }

    private function calculateConfidence(array $fields): int
    {
        $structuredCount = $this->countStructuredFields($fields);
        $hasTitle = ($fields['title'] ?? null) !== null;
        if ($structuredCount === 0) {
            return $hasTitle ? 10 : 0;
        }

        return min(95, 20 + ($structuredCount * 15) + ($hasTitle ? 5 : 0));
    }

    private function countStructuredFields(array $fields): int
    {
        return count(array_filter([
            ($fields['vendor_id'] ?? null) !== null,
            ($fields['expense_date'] ?? null) !== null,
            ($fields['amount'] ?? null) !== null,
            ($fields['reference'] ?? null) !== null,
            ($fields['category'] ?? null) !== null,
        ]));
    }

    private function emptyFields(): array
    {
        return [
            'vendor_id' => null,
            'expense_date' => null,
            'amount' => null,
            'title' => null,
            'reference' => null,
            'category' => null,
        ];
    }

    private function normalizeModelFields(array $decoded, array $vendors): array
    {
        $vendorName = trim((string) ($decoded['vendor_name'] ?? ''));
        $expenseDate = trim((string) ($decoded['expense_date'] ?? ''));
        $title = trim((string) ($decoded['title'] ?? ''));
        $reference = strtoupper(trim((string) ($decoded['reference'] ?? '')));
        $category = strtolower(trim((string) ($decoded['category'] ?? '')));
        $allowedCategories = ['fuel', 'travel', 'utilities', 'meals', 'office_supplies', 'software', 'maintenance'];

        $parsedDate = null;
        if ($expenseDate !== '') {
            try {
                $parsedDate = Carbon::parse($expenseDate)->toDateString();
            } catch (Throwable) {
                $parsedDate = null;
            }
        }

        if (! in_array($category, $allowedCategories, true)) {
            $category = '';
        }
        if ($reference !== '' && ! preg_match('/^[A-Z0-9-]{4,}$/', $reference)) {
            $reference = '';
        }

        return [
            'vendor_id' => $this->resolveVendorFromName($vendorName, $vendors),
            'expense_date' => $parsedDate,
            'amount' => $this->parseAmountCandidate((string) ($decoded['amount'] ?? '')),
            'title' => $title !== '' ? mb_substr($title, 0, 120) : null,
            'reference' => $reference !== '' ? $reference : null,
            'category' => $category !== '' ? $category : null,
        ];
    }

    private function normalizeConfidence($value): int
    {
        $confidence = is_numeric((string) $value) ? (int) $value : 0;

        return max(0, min(100, $confidence));
    }

    private function decodeJsonObject(string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }
        $candidate = substr($text, $start, ($end - $start) + 1);
        $decoded = json_decode($candidate, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function canReachOllama(string $baseUrl, int $timeout): bool
    {
        if (array_key_exists($baseUrl, self::$ollamaReachabilityCache)) {
            return self::$ollamaReachabilityCache[$baseUrl];
        }

        $probeTimeout = max(1, min(3, $timeout));
        try {
            $response = Http::timeout($probeTimeout)->acceptJson()->get($baseUrl.'/api/tags');
            self::$ollamaReachabilityCache[$baseUrl] = $response->successful();
        } catch (Throwable) {
            self::$ollamaReachabilityCache[$baseUrl] = false;
        }

        return self::$ollamaReachabilityCache[$baseUrl];
    }

    private function binaryExists(string $binary): bool
    {
        $command = PHP_OS_FAMILY === 'Windows' ? ['where', $binary] : ['which', $binary];
        $process = new Process($command);
        $process->run();

        return $process->isSuccessful();
    }

    private function runProcess(array $command): string
    {
        $process = new Process($command);
        $process->setTimeout(10);
        try {
            $process->run();
            if (! $process->isSuccessful()) {
                return '';
            }
        } catch (Throwable) {
            return '';
        }

        return $this->normalizeText($process->getOutput());
    }

    private function modeInt(array $values): ?int
    {
        if ($values === []) {
            return null;
        }
        $counts = array_count_values(array_values(array_filter($values, fn ($value): bool => (int) $value > 0)));
        if ($counts === []) {
            return null;
        }
        arsort($counts);
        $key = array_key_first($counts);

        return is_numeric((string) $key) ? (int) $key : null;
    }

    private function modeString(array $values): ?string
    {
        $clean = array_values(array_filter(array_map(fn ($value): string => trim((string) $value), $values)));
        if ($clean === []) {
            return null;
        }
        $counts = array_count_values($clean);
        arsort($counts);
        $key = array_key_first($counts);

        return is_string($key) && $key !== '' ? $key : null;
    }
}
