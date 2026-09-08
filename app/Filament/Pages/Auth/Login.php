<?php

namespace App\Filament\Pages\Auth;

use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Auth\Login as BaseLogin;

class Login extends BaseLogin
{
    protected static string $view = 'filament.pages.auth.login';

    // Menggunakan layout dasar tanpa card pembungkus default Filament
    protected static string $layout = 'filament-panels::components.layout.base';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ])
            ->statePath('data');
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('EMAIL PENGGUNA')
            ->email()
            ->required()
            ->autocomplete()
            ->autofocus()
            ->placeholder('admin@skytrack.id')
            ->prefixIcon('heroicon-m-user');
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('KATA SANDI')
            ->password()
            ->revealable()
            ->required()
            ->placeholder('••••••••')
            ->prefixIcon('heroicon-m-lock-closed');
    }

    protected function getRememberFormComponent(): Component
    {
        return Checkbox::make('remember')
            ->label('Ingatkan Saya');
    }

    protected function getAuthenticateFormAction(): Action
    {
        return Action::make('authenticate')
            ->label('Masuk ke Sistem')
            ->icon('heroicon-m-arrow-right-end-on-rectangle')
            ->submit('authenticate');
    }
}
