import React from 'react';
import { AbsoluteFill, interpolate, useCurrentFrame, useVideoConfig } from 'remotion';
import { SceneData } from './types';

export const SceneRouter: React.FC<{ scene: SceneData }> = ({ scene }) => {
    const frame = useCurrentFrame();
    const { fps } = useVideoConfig();

    const fadeOpacity = scene.animation_config?.fade_in !== false
        ? interpolate(frame, [0, 15], [0, 1], { extrapolateRight: 'clamp', extrapolateLeft: 'clamp' })
        : 1;

    // Fondo institucional base
    const containerStyle: React.CSSProperties = {
        backgroundColor: '#002E5D', // Azul FCEFN
        color: 'white',
        fontFamily: 'sans-serif',
        padding: '80px',
        display: 'flex',
        flexDirection: 'column',
        justifyContent: 'center',
        opacity: fadeOpacity,
    };

    if (scene.layout_template === 'title_hero') {
        const scale = interpolate(frame, [0, 20], [scene.animation_config?.scale_start || 0.9, 1], {
            extrapolateRight: 'clamp',
            extrapolateLeft: 'clamp',
        });

        return (
            <AbsoluteFill style={{ ...containerStyle, alignItems: 'center', textAlign: 'center' }}>
                <div style={{ transform: `scale(${scale})` }}>
                    <div style={{ width: '120px', height: '8px', backgroundColor: '#FFD700', margin: '0 auto 40px auto', borderRadius: '4px' }} />
                    <h1 style={{ fontSize: '92px', textTransform: 'uppercase', lineHeight: 1.15, margin: 0 }}>
                        {scene.text_content.headline}
                    </h1>
                    {scene.text_content.subheadline && (
                        <h2 style={{ fontSize: '48px', color: '#FFD700', marginTop: '36px', fontWeight: 500 }}>
                            {scene.text_content.subheadline}
                        </h2>
                    )}
                </div>
            </AbsoluteFill>
        );
    }

    if (scene.layout_template === 'call_to_action') {
        const scale = interpolate(frame, [0, 20], [scene.animation_config?.scale_start || 0.85, 1], {
            extrapolateRight: 'clamp',
            extrapolateLeft: 'clamp',
        });

        return (
            <AbsoluteFill style={{ ...containerStyle, backgroundColor: '#001F3F', alignItems: 'center', textAlign: 'center' }}>
                <div style={{ transform: `scale(${scale})`, border: '4px solid #FFD700', borderRadius: '32px', padding: '64px 48px', width: '100%' }}>
                    <h1 style={{ fontSize: '84px', color: '#FFD700', textTransform: 'uppercase', margin: 0 }}>
                        {scene.text_content.headline}
                    </h1>
                    {scene.text_content.subheadline && (
                        <h2 style={{ fontSize: '46px', color: '#FFFFFF', marginTop: '28px' }}>
                            {scene.text_content.subheadline}
                        </h2>
                    )}
                    {Array.isArray(scene.text_content.body) && scene.text_content.body.length > 0 && (
                        <div style={{ marginTop: '40px', fontSize: '42px', color: '#E2E8F0' }}>
                            {scene.text_content.body.map((item, idx) => (
                                <p key={idx} style={{ margin: '12px 0' }}>{item}</p>
                            ))}
                        </div>
                    )}
                </div>
            </AbsoluteFill>
        );
    }

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
