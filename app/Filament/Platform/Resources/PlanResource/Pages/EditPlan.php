<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources\PlanResource\Pages;

use App\Filament\Platform\Resources\PlanResource;
use App\Models\Plan;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPlan extends EditRecord
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('delete')
                ->color('danger')
                ->action(function (): void {
                    $plan = $this->record;

                    if ($plan instanceof Plan && PlanResource::isPlanReferenced($plan)) {
                        Notification::make()
                            ->title('Cannot delete plan')
                            ->body('This plan is referenced by existing subscriptions or pending plan-change requests.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $plan->delete();
                    $this->redirect(PlanResource::getUrl('index'));
                }),
        ];
    }
}
