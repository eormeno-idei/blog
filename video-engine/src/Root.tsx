import { Composition } from 'remotion';
import { KioskReelPreview } from './KioskReelPreview';
import { ReelJson } from './types';

// Un JSON de prueba quemado para cuando abras la vista previa en el navegador
const testJson: ReelJson = {
    metadata: { autor: "UNSJ", tema: "Prueba", duracion_total_segundos: 10 },
    scenes: [
        {
            scene_index: 1,
            layout_template: "bullet_points",
            duration_seconds: 10,
            animation_config: { entrance_type: "slide_from_bottom", fade_in: true, stagger_ms: 600 },
            text_content: { headline: "Backend-Driven UI", subheadline: "PDTS 2025", body: ["Valida arquitectura", "App móvil +65", "Transfiere tecnología"] },
            visual_assets: { image_url: null, qr_image_url: null }
        }
    ]
};

export const RemotionRoot: React.FC = () => {
    return (
        <>
            <Composition
                id="ReelInstitucional"
                component={KioskReelPreview as React.FC<any>}
                fps={30}
                width={1080}
                height={1920}
                defaultProps={testJson}

                calculateMetadata={({ props }) => {
                    // 1. Convertimos los props genéricos a nuestra interfaz segura internamente
                    const reelProps = props as unknown as ReelJson;

                    // 2. Ahora TypeScript autocompleta y no arroja errores
                    const totalSeconds = reelProps.scenes.reduce(
                        (acumulado: number, scene: any) => acumulado + scene.duration_seconds,
                        0
                    );

                    return {
                        durationInFrames: totalSeconds * 30,
                    };
                }}
            />
        </>
    );
};
