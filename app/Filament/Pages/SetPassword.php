<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\HasMaxWidth;
use Filament\Pages\Concerns\HasTopbar;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

/**
 * Forced set-password screen for invite-only accounts. Reached only when a
 * pastor still carries the Head Pastor's temporary password (guarded by the
 * ForcePasswordChange middleware). Rendered with the simple, chrome-less login
 * layout so there is nowhere to navigate until a new password is chosen.
 *
 * @property-read Schema $form
 */
class SetPassword extends Page
{
    use HasMaxWidth;
    use HasTopbar;

    protected string $view = 'filament.pages.set-password';

    // Not a normal destination — it is only reached via the forced redirect.
    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        // Nothing to do here if the password is already the pastor's own.
        if (! (Auth::user()?->mustChangePassword() ?? false)) {
            redirect()->intended(Filament::getUrl());

            return;
        }

        $this->form->fill();
    }

    public function getLayout(): string
    {
        return 'filament-panels::components.layout.simple';
    }

    public function hasLogo(): bool
    {
        return true;
    }

    protected function getLayoutData(): array
    {
        return [
            'hasTopbar' => false,
            'maxContentWidth' => $maxWidth = $this->getMaxWidth() ?? $this->getMaxContentWidth(),
            'maxWidth' => $maxWidth,
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('password')
                    ->label('New password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->rule(Password::default())
                    ->autocomplete('new-password')
                    ->same('passwordConfirmation'),

                TextInput::make('passwordConfirmation')
                    ->label('Confirm new password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->autocomplete('new-password')
                    ->visible(fn (Get $get): bool => filled($get('password')))
                    ->dehydrated(false),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $user = Auth::user();
        $user->update([
            'password' => $data['password'], // the model's "hashed" cast hashes it
            'must_change_password' => false,
        ]);

        AuditLog::record('set_password', $user);

        Notification::make()
            ->title('Password updated')
            ->body('You can now use the system.')
            ->success()
            ->send();

        redirect()->intended(Filament::getUrl());
    }

    public function getHeading(): string
    {
        return 'Set your password';
    }

    public function getSubheading(): string
    {
        return 'Your account was created with a temporary password. Choose your own to continue.';
    }
}