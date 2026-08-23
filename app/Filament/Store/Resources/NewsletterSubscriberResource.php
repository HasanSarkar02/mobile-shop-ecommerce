<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Filament\Store\Concerns\RestrictsToOwner;
use App\Filament\Store\Resources\NewsletterSubscriberResource\Pages;
use App\Models\NewsletterSubscriber;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

class NewsletterSubscriberResource extends Resource
{
    use RestrictsToOwner;

    protected static ?string $model = NewsletterSubscriber::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('email')->email()->required()->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('subscribed_at')
                    ->label('Subscribed')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('subscribed_at')
                    ->form([
                        DatePicker::make('subscribed_from')->label('Subscribed from'),
                        DatePicker::make('subscribed_until')->label('Subscribed until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['subscribed_from'] ?? null, fn (Builder $q, $date): Builder => $q->where('subscribed_at', '>=', Carbon::parse($date)))
                            ->when($data['subscribed_until'] ?? null, fn (Builder $q, $date): Builder => $q->where('subscribed_at', '<=', Carbon::parse($date)->endOfDay()));
                    }),
            ])
            ->actions([DeleteAction::make()])
            ->bulkActions([
                BulkAction::make('delete')
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->each->delete()),
                BulkAction::make('exportCsv')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function (Collection $records): StreamedResponse {
                        $filename = 'newsletter-subscribers-'.now()->format('Y-m-d').'.csv';

                        return response()->streamDownload(function () use ($records): void {
                            $out = fopen('php://output', 'w');
                            fputcsv($out, ['email', 'subscribed_at']);

                            $subscribers = NewsletterSubscriber::query()
                                ->whereIn('id', $records->modelKeys())
                                ->orderBy('subscribed_at')
                                ->get();

                            foreach ($subscribers as $record) {
                                fputcsv($out, [$record->email, optional($record->subscribed_at)->toDateTimeString()]);
                            }

                            fclose($out);
                        }, $filename, ['Content-Type' => 'text/csv']);
                    }),
            ])
            ->defaultSort('subscribed_at', 'desc')
            ->emptyStateHeading('No subscribers yet')
            ->headerActions([
                Action::make('exportAll')
                    ->label('Export All (CSV)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(function (): StreamedResponse {
                        $filename = 'newsletter-subscribers-all-'.now()->format('Y-m-d').'.csv';

                        return response()->streamDownload(function (): void {
                            $out = fopen('php://output', 'w');
                            fputcsv($out, ['email', 'subscribed_at']);

                            foreach (NewsletterSubscriber::query()->orderBy('subscribed_at')->cursor() as $subscriber) {
                                fputcsv($out, [$subscriber->email, optional($subscriber->subscribed_at)->toDateTimeString()]);
                            }

                            fclose($out);
                        }, $filename, ['Content-Type' => 'text/csv']);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNewsletterSubscribers::route('/'),
        ];
    }
}
