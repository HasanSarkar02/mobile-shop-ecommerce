<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\CompareService;
use Livewire\Attributes\On;
use Livewire\Component;

class CompareBadge extends Component
{
    #[On('compare-updated')]
    public function refresh(): void
    {
        // render() re-runs automatically; method exists purely to register the listener.
    }

    public function render(CompareService $compare)
    {
        return view('livewire.compare-badge', [
            'count' => count($compare->ids()),
        ]);
    }
}
