<?php

namespace App\Filament\Pages;

use App\Actions\Orders\CommercePolicyActions;
use App\Enums\UserRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class CommerceSettings extends Page
{
    protected string $view = 'filament.pages.commerce-settings';

    protected static string|\UnitEnum|null $navigationGroup = 'Commerce';

    protected static ?string $title = 'Commerce settings';

    public ?array $data = [];

    #[Locked]
    public int $expectedVersion;

    #[Locked]
    public string $operationId;

    public static function canAccess(): bool
    {
        $actor = auth()->user()?->fresh();

        return $actor !== null && $actor->hasVerifiedEmail() && $actor->hasAnyRole([UserRole::Administrator->value, UserRole::SuperAdmin->value]) && $actor->checkPermissionTo('Manage:CommerceSettings');
    }

    public function mount(): void
    {
        $policy = app(CommercePolicyActions::class)->read(auth()->user());
        $this->expectedVersion = $policy->version;
        $this->operationId = (string) Str::uuid();
        $this->form->fill($policy->content);
    }

    public function hydrate(): void
    {
        abort_unless(self::canAccess(), 403);
    }

    public function form(Schema $schema): Schema
    {
        $integer = fn (string $field, string $label, int $min, int $max) => TextInput::make($field)->label($label)->required()->integer()->minValue($min)->maxValue($max)->dehydrateStateUsing(fn ($state): int => (int) $state);

        return $schema->statePath('data')->components([
            Section::make('Tax and documents')->description('Configure the merchant tax profile before real sales. Unconfigured adds no calculated tax and does not mean tax-exempt. This document is an order summary.')->schema([
                Select::make('tax_mode')->options(['unconfigured' => 'Unconfigured', 'exclusive' => 'Tax added to price', 'inclusive' => 'Tax included in price'])->required(),
                $integer('tax_basis_points', 'Tax rate (basis points; 1200 = 12%)', 0, 10000),
                Select::make('document_kind')->options(['order_summary' => 'Order summary'])->required(),
            ]),
            Section::make('Review and required fields')->schema([
                $integer('approval_days', 'Approval validity (days)', 1, 365),
                Select::make('required_brief')->multiple()->options(['goal' => 'Project goal', 'scope' => 'Requested scope', 'website' => 'Website']),
                Select::make('required_billing')->multiple()->options(['name' => 'Name', 'email' => 'Email', 'country' => 'Country', 'address' => 'Address', 'company' => 'Company', 'phone' => 'Phone']),
            ]),
            Section::make('Commerce terms and privacy notice')->description('Starter wording needs to reflect your actual business before launch. New versions apply to future submissions and updated offers; accepted requests retain their terms.')->schema([
                Textarea::make('terms')->required()->maxLength(50000)->rows(8), Textarea::make('privacy')->required()->maxLength(50000)->rows(8),
            ]),
            Section::make('Retention policy')->description('Carts do not expire. These settings do not run deletion. Financial retention needs its filing anchor; legal and dispute holds take precedence.')->schema([
                $integer('retention.draft_days', 'Draft inactivity limit (days)', 1, 3650),
                $integer('retention.closed_request_days', 'Closed nonfinancial requests (days)', 1, 3650),
                $integer('retention.financial_years', 'Financial records after filing anchor (years)', 1, 100),
                $integer('retention.audit_days', 'Nonfinancial audit records (days)', 1, 3650),
            ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);
        $content = $this->form->getState();
        try {
            $policy = app(CommercePolicyActions::class)->publish(auth()->user(), $content, $this->operationId, $this->expectedVersion);
        } catch (HttpException $exception) {
            if (! in_array($exception->getStatusCode(), [409, 422], true)) {
                throw $exception;
            }
            Notification::make()->danger()->title('Settings not saved')->body($exception->getStatusCode() === 409 ? 'Settings changed. Reload before publishing another version.' : 'Unconfigured tax must use a zero tax rate.')->send();

            return;
        }
        $this->expectedVersion = $policy->version;
        $this->operationId = (string) Str::uuid();
        Notification::make()->success()->title('Settings version published')->send();
    }
}
