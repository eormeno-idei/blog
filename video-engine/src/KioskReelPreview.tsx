import React from 'react';
import { Series, useVideoConfig } from 'remotion';
import { ReelJson } from './types';
import { SceneRouter } from './SceneRouter';

export const KioskReelPreview: React.FC<ReelJson> = (props) => {
    const { fps } = useVideoConfig();

    return (
        <Series>
            {props.scenes.map((scene) => (
                <Series.Sequence
                    key={scene.scene_index}
                    // Convertimos la duración del JSON (segundos) a fotogramas para Remotion
                    durationInFrames={scene.duration_seconds * fps}
                >
                    <SceneRouter scene={scene} />
                </Series.Sequence>
            ))}
        </Series>
    );
};
