<?php

namespace App\UI\Screens\Post\Parts;

use App\UI\Screens\Post\Parts\ReelSceneViewer;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Screen;
use Idei\Usim\UI;

class OldReelStudio extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;
    protected Container $reel_preview_slot;
    protected Button $load;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $this->reel_preview_slot = UI::container('reel_preview_slot');
        $container
            ->add(
                $this->load
                    ->label("Load Example Reel")
                    ->style('primary')
                    ->action('load_example_reel')
            );

        $container->add($this->reel_preview_slot);

        $this->reel_preview_slot->embed(ReelSceneViewer::class);
    }

    public function load_example_reel(): void
    {
        $json = file_get_contents(base_path('_data.json'));
        $this->reel_preview_slot->embed(ReelSceneViewer::class, [
            'data' => $json,
        ]);
    }
}
