export interface AnimationConfig {
    entrance_type: string;
    fade_in: boolean;
    stagger_ms: number;
    scale_start?: number;
    rotate_start_deg?: number;
    easing?: string;
}

export interface TextContent {
    headline: string;
    subheadline: string | null;
    body: string[];
}

export interface VisualAssets {
    image_url: string | null;
    qr_image_url: string | null;
}

export interface SceneData {
    scene_index: number;
    layout_template: string;
    duration_seconds: number;
    animation_config: AnimationConfig;
    text_content: TextContent;
    visual_assets: VisualAssets;
}

export interface ReelJson {
    metadata: {
        autor: string;
        tema: string;
        duracion_total_segundos: number;
    };
    scenes: SceneData[];
}
