<?php
// @usim: feature="admin", type="screen"
namespace App\UI\Screens\Device;

use Idei\Usim\Components\Carousel;
use Idei\Usim\Components\Container;
use Idei\Usim\Contracts\DeviceSecurityGuardInterface;
use Idei\Usim\Contracts\PairableActorInterface;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Facades\Auth;

class KioskScreen extends Screen
{
    /**
     * Dispositivo / terminal Kiosk autenticado mediante guard 'device'.
     */
    public static ?string $guard = 'device';

    /**
     * El modo kiosco se despliega a pantalla completa sin barra de navegación superior.
     */
    public static ?string $layout = null;

    protected Carousel $device_carousel;

    protected int $state_current_index = 0;

    protected int $state_timeout_ms = 5000;

    protected string $store_token = '';

    public static function authorize(): bool
    {
        if (! self::requireAuth('device')) {
            return false;
        }

        if (app()->bound(DeviceSecurityGuardInterface::class)) {
            if (! app(DeviceSecurityGuardInterface::class)->isDevicePaired(request())) {
                return false;
            }
        } else {
            $device = Auth::guard('device')->user();
            if ($device instanceof PairableActorInterface && ! $device->isPaired()) {
                return false;
            }
        }

        return self::requirePermission('device.kiosk_screen.access', 'device');
    }

    /**
     * @param  mixed  ...$params
     */
    protected function buildBaseUI(Container $container, ...$params): void
    {
        $items = $this->mediaItems();
        $this->state_current_index = $this->normalizeIndex($this->state_current_index, \count($items));

        $currentItem = $items[$this->state_current_index];
        $this->state_timeout_ms = max(1000, $currentItem['duration_ms']);

        $container
            ->plain()
            ->padding(Spacing::px(0));

        $container->add(
            UI::carousel('device_carousel')
                ->modeAuto()
                ->autoplay(true)
                ->autoAction('carousel_tick')
                ->autoTimeoutMs($this->state_timeout_ms)
                ->loop(true)
                ->fullscreen(false)
                ->showPrev(false)
                ->showNext(false)
                ->knownCount(null)
                ->currentIndex($this->state_current_index)
                ->currentMedia($currentItem)
                ->hideIndicators()
        );
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onCarouselTick(array $params): void
    {
        $carouselName = $params['carousel_name'] ?? 'device_carousel';

        if ($carouselName !== 'device_carousel') {
            return;
        }

        $items = $this->mediaItems();
        $count = \count($items);
        $this->state_current_index = $this->normalizeIndex($this->state_current_index + 1, $count);

        $next = $items[$this->state_current_index];
        $this->state_timeout_ms = max(1000, $next['duration_ms']);

        $this->device_carousel
            ->currentIndex($this->state_current_index)
            ->currentMedia($next)
            ->autoTimeoutMs($this->state_timeout_ms)
            ->knownCount(null);
    }

    private function normalizeIndex(int $index, int $count): int
    {
        if ($count <= 0) {
            return 0;
        }

        return (($index % $count) + $count) % $count;
    }

    /**
     * @return list<array{id: string, kind: string, url: string, mime: string, title: string, duration_ms: int}>
     */
    protected function mediaItems(): array
    {
        return [
            [
                'id' => 'd1',
                'kind' => 'image',
                'url' => 'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=1200&q=80',
                'mime' => 'image/jpeg',
                'title' => t('screens.device.kiosk.media.welcome'),
                'duration_ms' => 5000,
            ],
            [
                'id' => 'd2',
                'kind' => 'video',
                'url' => 'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',
                'mime' => 'video/mp4',
                'title' => t('screens.device.kiosk.media.showcase'),
                'duration_ms' => 5000,
            ],
            [
                'id' => 'd3',
                'kind' => 'image',
                'url' => 'https://images.unsplash.com/photo-1491553895911-0055eca6402d?auto=format&fit=crop&w=1200&q=80',
                'mime' => 'image/jpeg',
                'title' => t('screens.device.kiosk.media.announcement'),
                'duration_ms' => 6000,
            ],
        ];
    }
}
