<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources\CampaignResource\Pages;

use App\Modules\Communication\Domain\Enums\CampaignRecipientStatus;
use App\Modules\Communication\Domain\Models\CampaignRecipient;
use App\Modules\Communication\Domain\Models\CampaignRun;
use App\Modules\Communication\Filament\Resources\CampaignResource;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ViewCampaign extends ViewRecord implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = CampaignResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Campaign Details')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('name')->label('Name'),
                        TextEntry::make('channel')
                            ->label('Channel')
                            ->formatStateUsing(fn ($state) => $state->label()),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn ($state) => $state->color())
                            ->formatStateUsing(fn ($state) => $state->label()),
                    ]),
                ]),

            Section::make('Run Statistics')
                ->schema([
                    Grid::make(4)->schema([
                        TextEntry::make('latestRun.recipients_total')
                            ->label('Total Recipients')
                            ->default('—'),
                        TextEntry::make('latestRun.recipients_sent')
                            ->label('Sent')
                            ->default('—'),
                        TextEntry::make('latestRun.recipients_failed')
                            ->label('Failed')
                            ->default('—'),
                        TextEntry::make('skipped_count')
                            ->label('Skipped')
                            ->state(function ($record) {
                                $run = $record->runs()->latest()->first();
                                if ($run === null) {
                                    return '—';
                                }

                                return $run->recipients_total - $run->recipients_sent - $run->recipients_failed;
                            }),
                    ]),
                ])
                ->visible(fn ($record) => $record->runs()->exists()),
        ]);
    }

    public function table(Table $table): Table
    {
        $campaign = $this->record;
        $run = CampaignRun::where('campaign_id', $campaign->id)->latest()->first();

        return $table
            ->query(
                $run
                    ? CampaignRecipient::query()->where('campaign_run_id', $run->id)->with(['user', 'dispatch'])
                    : CampaignRecipient::query()->whereRaw('1=0')
            )
            ->columns([
                TextColumn::make('user.name')
                    ->label('Recipient')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (CampaignRecipientStatus $state) => $state->color())
                    ->formatStateUsing(fn (CampaignRecipientStatus $state) => ucfirst($state->value)),
                TextColumn::make('dispatch.locale')
                    ->label('Locale')
                    ->default('—'),
                TextColumn::make('dispatch.provider')
                    ->label('Provider')
                    ->default('—'),
                TextColumn::make('dispatch.error_message')
                    ->label('Error')
                    ->default('—')
                    ->limit(60),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(CampaignRecipientStatus::cases())->mapWithKeys(fn ($c) => [$c->value => ucfirst($c->value)])),
            ])
            ->heading('Recipients')
            ->paginated([20, 50]);
    }
}
