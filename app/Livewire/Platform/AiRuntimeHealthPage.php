<?php

namespace App\Livewire\Platform;

use App\Livewire\Platform\Concerns\InteractsWithTenantCompanies;
use App\Services\AI\AiRuntimeHealthService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('AI Runtime Health')]
class AiRuntimeHealthPage extends Component
{
    use InteractsWithTenantCompanies;

    public bool $readyToLoad = false;

    public function mount(): void
    {
        $this->authorizePlatformOperator();
    }

    public function loadData(): void
    {
        if ($this->readyToLoad) {
            return;
        }

        $this->readyToLoad = true;
    }

    public function refreshSnapshot(): void
    {
        $this->authorizePlatformOperator();
        $this->readyToLoad = true;
    }

    public function render(): View
    {
        $this->authorizePlatformOperator();

        return view('livewire.platform.ai-runtime-health-page', [
            'snapshot' => $this->readyToLoad
                ? app(AiRuntimeHealthService::class)->snapshot()
                : $this->emptySnapshot(),
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function emptySnapshot(): array
    {
        return [
            'runtime' => [
                'provider' => 'unknown',
                'base_url' => '',
                'timeout_seconds' => 0,
                'primary_model' => '',
                'fallback_model' => '',
                'fast_model' => '',
                'allowed_models' => [],
            ],
            'checks' => [
                'ollama_reachable' => null,
                'primary_model_loaded' => null,
                'loaded_models_count' => null,
                'image_ocr_available' => false,
                'pdf_text_available' => false,
            ],
            'metrics' => [
                'window_hours' => 24,
                'analyses' => 0,
                'model_assisted' => 0,
                'deterministic' => 0,
                'fallback_rate_percent' => 0.0,
                'sample_truncated' => false,
            ],
            'last_model_success' => null,
            'last_analysis' => null,
            'recent_analyses' => [],
        ];
    }
}

