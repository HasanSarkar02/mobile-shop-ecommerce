<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Plan;
use App\Models\User;

class PlanObserver
{
    public function created(Plan $plan): void
    {
        $this->record('plan.created', $plan);
    }

    public function updated(Plan $plan): void
    {
        $this->record('plan.updated', $plan);
    }

    public function deleted(Plan $plan): void
    {
        $this->record('plan.deleted', $plan);
    }

    private function record(string $event, Plan $plan): void
    {
        $activity = activity('plans')
            ->performedOn($plan)
            ->event($event)
            ->withProperties($this->properties($event, $plan));

        $actor = auth('platform')->user();

        if ($actor instanceof User) {
            $activity->causedBy($actor);
        } else {
            $activity->causedByAnonymous();
        }

        $activity->log($event);
    }

    /**
     * @return array<string, mixed>
     */
    private function properties(string $event, Plan $plan): array
    {
        $properties = [
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'slug' => $plan->slug,
        ];

        if ($event === 'plan.updated') {
            $properties['changed'] = $plan->getChanges();
        }

        return $properties;
    }
}
