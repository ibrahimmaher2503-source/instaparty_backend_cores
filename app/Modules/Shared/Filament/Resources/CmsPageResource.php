<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Resources;

use App\Modules\Shared\Application\Actions\PublishCmsPageAction;
use App\Modules\Shared\Application\Actions\UnpublishCmsPageAction;
use App\Modules\Shared\Domain\Models\CmsPage;
use App\Modules\Shared\Filament\Resources\CmsPageResource\Pages;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use FilamentTiptapEditor\TiptapEditor;

class CmsPageResource extends Resource
{
    protected static ?string $model = CmsPage::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.settings');
    }

    protected static ?string $navigationLabel = 'CMS Pages';

    protected static ?string $modelLabel = 'CMS Page';

    protected static ?string $pluralModelLabel = 'CMS Pages';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make('Translations')
                ->tabs([
                    Tabs\Tab::make('English')
                        ->schema([
                            TextInput::make('title.en')
                                ->label('Title (English)')
                                ->required()
                                ->maxLength(255),
                            TiptapEditor::make('body.en')
                                ->label('Body (English)')
                                ->profile('default')
                                ->required()
                                ->columnSpanFull(),
                            TextInput::make('meta_description.en')
                                ->label('Meta Description (English)')
                                ->maxLength(255),
                        ]),
                    Tabs\Tab::make('Ø§Ù„Ø¹Ø±Ø¨ÙŠØ©')
                        ->schema([
                            TextInput::make('title.ar')
                                ->label('Ø§Ù„Ø¹Ù†ÙˆØ§Ù† (Ø¹Ø±Ø¨ÙŠ)')
                                ->required()
                                ->maxLength(255),
                            TiptapEditor::make('body.ar')
                                ->label('Ø§Ù„Ù…Ø­ØªÙˆÙ‰ (Ø¹Ø±Ø¨ÙŠ)')
                                ->profile('default')
                                ->required()
                                ->columnSpanFull(),
                            TextInput::make('meta_description.ar')
                                ->label('ÙˆØµÙ Ø§Ù„Ù…ÙŠØªØ§ (Ø¹Ø±Ø¨ÙŠ)')
                                ->maxLength(255),
                        ]),
                ])
                ->columnSpanFull(),

            Section::make('Page Info')
                ->schema([
                    TextInput::make('slug')
                        ->label('Slug')
                        ->disabled()
                        ->dehydrated(false),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('slug')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Title')
                    ->formatStateUsing(fn (CmsPage $record): string => $record->getTranslation('title', app()->getLocale()) ?: 'â€”')
                    ->limit(50),
                IconColumn::make('is_published')
                    ->boolean()
                    ->label('Published'),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('â€”'),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('is_published')
                    ->label('Published'),
            ])
            ->actions([
                Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (CmsPage $record): bool => ! $record->is_published)
                    ->requiresConfirmation()
                    ->action(function (CmsPage $record): void {
                        app(PublishCmsPageAction::class)->execute($record);
                        Notification::make()
                            ->title('Page published successfully.')
                            ->success()
                            ->send();
                    }),
                Action::make('unpublish')
                    ->label('Unpublish')
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->visible(fn (CmsPage $record): bool => $record->is_published)
                    ->requiresConfirmation()
                    ->action(function (CmsPage $record): void {
                        app(UnpublishCmsPageAction::class)->execute($record);
                        Notification::make()
                            ->title('Page unpublished.')
                            ->warning()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCmsPages::route('/'),
            'edit' => Pages\EditCmsPage::route('/{record}/edit'),
        ];
    }
}
