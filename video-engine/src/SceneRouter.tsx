import React from 'react';
import { AbsoluteFill, interpolate, useCurrentFrame, useVideoConfig } from 'remotion';
import { SceneData } from './types';

export const SceneRouter: React.FC<{ scene: SceneData }> = ({ scene }) => {
    const frame = useCurrentFrame();
    const { fps } = useVideoConfig();

    // Fondo institucional base
    const containerStyle: React.CSSProperties = {
        backgroundColor: '#002E5D', // Azul FCEFN
        color: 'white',
        fontFamily: 'sans-serif',
        padding: '80px',
        display: 'flex',
        flexDirection: 'column',
        justifyContent: 'center',
    };

    if (scene.layout_template === 'bullet_points') {
        return (
            <AbsoluteFill style={containerStyle}>
                <h1 style={{ fontSize: '80px', textTransform: 'uppercase' }}>
                    {scene.text_content.headline}
                </h1>
                {scene.text_content.subheadline && (
                    <h2 style={{ fontSize: '50px', color: '#FFD700' }}>
                        {scene.text_content.subheadline}
                    </h2>
                )}

                <ul style={{ fontSize: '45px', lineHeight: '1.5', marginTop: '40px', listStyleType: 'none', paddingLeft: '0' }}>
                    {scene.text_content.body.map((text, index) => {

                        // Si la IA no envía animation_config o stagger_ms, usamos 600ms por defecto
                        const staggerMs = scene.animation_config?.stagger_ms ?? 600;

                        // Calculamos el stagger (retraso) en fotogramas
                        const delayFrames = (staggerMs / 1000) * fps;
                        const startFrame = index * delayFrames;

                        // Animación de opacidad y posición (fade in up)
                        const opacity = interpolate(frame, [startFrame, startFrame + 15], [0, 1], { extrapolateRight: 'clamp', extrapolateLeft: 'clamp' });
                        const translateY = interpolate(frame, [startFrame, startFrame + 15], [50, 0], { extrapolateRight: 'clamp', extrapolateLeft: 'clamp' });

                        return (
                            <li key={index} style={{ opacity, transform: `translateY(${translateY}px)` }}>
                                • {text}
                            </li>
                        );
                    })}
                </ul>
            </AbsoluteFill>
        );
    }

    // Fallback genérico para otros layouts no implementados aún
    return (
        <AbsoluteFill style={containerStyle}>
            <h1 style={{ fontSize: '80px' }}>{scene.text_content.headline}</h1>
        </AbsoluteFill>
    );
};
