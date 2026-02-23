<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_request_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_amount')->default(true);
            $table->boolean('requires_line_items')->default(false);
            $table->boolean('requires_date_range')->default(false);
            $table->boolean('requires_vendor')->default(false);
            $table->boolean('requires_attachments')->default(false);
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('company_spend_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
        });

        $this->seedDefaults();
        $this->normalizeLegacyRequestTypes();
    }

    public function down(): void
    {
        Schema::dropIfExists('company_spend_categories');
        Schema::dropIfExists('company_request_types');
    }

    private function seedDefaults(): void
    {
        $companies = DB::table('companies')->select('id')->get();
        $now = now();

        foreach ($companies as $company) {
            $companyId = (int) $company->id;

            DB::table('company_request_types')->insertOrIgnore([
                [
                    'company_id' => $companyId,
                    'name' => 'Spend',
                    'code' => 'spend',
                    'description' => 'Financial spend request with line items.',
                    'is_active' => true,
                    'requires_amount' => true,
                    'requires_line_items' => true,
                    'requires_date_range' => false,
                    'requires_vendor' => false,
                    'requires_attachments' => false,
                    'metadata' => null,
                    'created_by' => null,
                    'updated_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'company_id' => $companyId,
                    'name' => 'Travel',
                    'code' => 'travel',
                    'description' => 'Travel authorization with date range and destination.',
                    'is_active' => true,
                    'requires_amount' => false,
                    'requires_line_items' => false,
                    'requires_date_range' => true,
                    'requires_vendor' => false,
                    'requires_attachments' => false,
                    'metadata' => null,
                    'created_by' => null,
                    'updated_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'company_id' => $companyId,
                    'name' => 'Leave',
                    'code' => 'leave',
                    'description' => 'Leave of absence request with date range.',
                    'is_active' => true,
                    'requires_amount' => false,
                    'requires_line_items' => false,
                    'requires_date_range' => true,
                    'requires_vendor' => false,
                    'requires_attachments' => false,
                    'metadata' => null,
                    'created_by' => null,
                    'updated_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            $defaultCategories = ['Operations', 'Travel', 'Utilities', 'Software', 'Procurement'];
            foreach ($defaultCategories as $categoryName) {
                DB::table('company_spend_categories')->insertOrIgnore([
                    'company_id' => $companyId,
                    'name' => $categoryName,
                    'code' => Str::slug($categoryName, '_'),
                    'description' => null,
                    'is_active' => true,
                    'metadata' => null,
                    'created_by' => null,
                    'updated_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function normalizeLegacyRequestTypes(): void
    {
        DB::table('requests')
            ->select(['id', 'metadata'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $metadata = is_array($row->metadata) ? $row->metadata : json_decode((string) $row->metadata, true);
                    if (! is_array($metadata)) {
                        $metadata = [];
                    }

                    $legacyType = strtolower((string) ($metadata['type'] ?? 'spend'));
                    $normalizedType = match ($legacyType) {
                        'travel' => 'travel',
                        'leave' => 'leave',
                        default => 'spend',
                    };

                    $metadata['type'] = $normalizedType;
                    $metadata['request_type_code'] = $normalizedType;

                    DB::table('requests')
                        ->where('id', (int) $row->id)
                        ->update([
                            'metadata' => json_encode($metadata),
                            'updated_at' => now(),
                        ]);
                }
            });
    }
};

