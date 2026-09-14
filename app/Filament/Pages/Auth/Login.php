<?php

namespace App\Filament\Pages\Auth;

use Filament\Actions\Action;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

class Login extends BaseLogin
{
    // Pakai view custom
    protected string $view = 'filament.pages.auth.login';

    // Pakai layout base (tanpa wrapper default Filament)
    protected static string $layout = 'filament-panels::components.layout.base';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),

                Actions::make([
                    $this->getAuthenticateFormAction(),
                ]),
            ])
            ->statePath('data');
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Alamat Email')
            ->email()
            ->required()
            ->autocomplete()
            ->autofocus()
            ->placeholder('admin@mutiaraskytrack.id')
            ->prefixIcon('heroicon-m-user');
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('Kata Sandi')
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
            ->label('Masuk Sekarang')
            ->icon('heroicon-m-arrow-right-end-on-rectangle')
            ->iconPosition('after')
            ->submit('authenticate')
            ->extraAttributes(['class' => 'w-full justify-center']);
    }
}
