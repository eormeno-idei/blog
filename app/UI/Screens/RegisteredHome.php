<?php

namespace App\UI\Screens;

use Idei\Usim\Components\Container;
use Idei\Usim\Components\NewLabel;
use Idei\Usim\Enums\FontWeight;
use Idei\Usim\Enums\Style;
use Idei\Usim\Enums\TextSize;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Illuminate\Support\Facades\Auth;

class RegisteredHome extends Screen
{
    protected NewLabel $welcome_message;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $userName = Auth::user()->name ?? 'Usuario';

        $container
            ->maxWidth(Size::half())
            ->add(
                $this->welcome_message = UI::newLabel('welcome_message')
                    ->text($this->welcomeMessage($userName))
                    ->size(TextSize::XL)
                    ->style(Style::INFO)
                    ->fontWeight(FontWeight::MEDIUM)
                    ->markdown(true)
                    ->center()
            )->add(
                UI::newLabel('completion_message')
                    ->text('Mientras tanto, puedes completar tu perfil.')
                    ->style(Style::DEFAULT)
                    ->fontWeight(FontWeight::LIGHT)
                    ->size(TextSize::XL)
            );
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onUpdatedProfile(array $params): void
    {
        $user = $params['user'] ?? null;
        if (is_object($user) && isset($user->name)) {
            $this->welcome_message
                ->text($this->welcomeMessage($user->name));
        }
    }

    private function welcomeMessage(string $userName): string
    {
        return "ℹ️
        Hola, **{$userName}**.
        Estás en la sala de espera.
        En breve recibirás un correo con la confirmación de tu registro.";
    }
}
