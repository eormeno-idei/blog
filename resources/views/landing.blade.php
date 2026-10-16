<div class="wf">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap');

        .wf {
            --bg: #0b1220;
            --bg-soft: #121c31;
            --surface: rgba(14, 23, 40, 0.86);
            --surface-strong: #101a2d;
            --border: rgba(133, 157, 198, 0.085);
            --text: #eef3ff;
            --muted: #b2c0dd;
            --accent: #4fc3f7;
            --accent-strong: #1e88e5;
            --ok: #2ec4b6;
            position: relative;
            overflow: hidden;
            padding: clamp(0.5rem, 0.5vw, 0.5rem);
            color: var(--text);
            transition: background 0.25s ease, color 0.25s ease;
            font-family: "Plus Jakarta Sans", "Segoe UI", sans-serif;
        }

        .wf[data-theme="light"],
        html[data-theme="light"] .wf,
        body[data-theme="light"] .wf {
            --bg: #f4f8ff;
            --bg-soft: #eaf1fb;
            --surface: rgba(255, 255, 255, 0.84);
            --surface-strong: #ffffff;
            --border: rgba(89, 117, 157, 0.28);
            --text: #11203a;
            --muted: #455d82;
            --accent: #1976d2;
            --accent-strong: #0d47a1;
            --ok: #00796b;
        }

        .landing-shell {
            max-width: 1280px;
            margin: 0 auto;
        }

        .landing {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: clamp(1.25rem, 1vw, 1rem);
            background: var(--surface);
            backdrop-filter: blur(8px);
            border: 1px solid var(--border);
            padding: clamp(1.25rem, 3vw, 2.25rem);
            box-shadow: 0 30px 80px rgba(4, 10, 20, 0.28);
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.4rem 0.7rem;
            border: 1px solid var(--border);
            border-radius: 999px;
            font-size: 0.78rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--muted);
            background: rgba(255, 255, 255, 0.04);
            margin-bottom: 1rem;
        }

        .dot {
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 50%;
            background: var(--ok);
            box-shadow: 0 0 0 5px rgba(46, 196, 182, 0.18);
            flex: 0 0 auto;
        }

        .hero-title {
            margin: 0;
            font-family: "Space Grotesk", "Segoe UI", sans-serif;
            font-size: clamp(2rem, 4vw, 2.1rem);
            line-height: 1.05;
            letter-spacing: -0.025em;
        }

        .hero-lead {
            margin: 1rem 0 0;
            max-width: 62ch;
            font-size: clamp(1rem, 1.8vw, 1.14rem);
            line-height: 1.4;
            color: var(--muted);
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1.4rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            padding: 0.72rem 0.72rem;
            border: 1px solid transparent;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.94rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-strong) 100%);
            color: #fff;
            box-shadow: 0 12px 28px rgba(25, 118, 210, 0.34);
        }

        .btn-secondary {
            border-color: var(--border);
            color: var(--text);
            background: rgba(255, 255, 255, 0.06);
        }

        .highlights {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.7rem;
            margin-top: 1.4rem;
        }

        .chip {
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.7rem;
            background: rgba(255, 255, 255, 0.03);
        }

        .chip strong {
            display: block;
            font-family: "Space Grotesk", "Segoe UI", sans-serif;
            font-size: 0.98rem;
            margin-bottom: 0.15rem;
        }

        .chip span {
            color: var(--muted);
            font-size: 0.86rem;
            line-height: 1.45;
        }

        .panel {
            background: var(--surface-strong);
            border: 1px solid var(--border);
            padding: 1rem;
            display: grid;
            gap: 0.85rem;
            align-content: start;
        }

        .panel-title {
            margin: 0;
            font-size: 0.94rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 600;
        }

        .code {
            margin: 0;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.22);
            padding: 0.85rem;
            color: #d7f1ff;
            font-size: 0.7rem;
            line-height: 1.55;
            overflow-x: auto;
        }

        .panel-list {
            margin: 0;
            padding: 0;
            list-style: none;
            display: grid;
            gap: 0.5rem;
        }

        .panel-list li {
            color: var(--muted);
            font-size: 0.9rem;
            padding: 0.55rem 0.65rem;
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.03);
        }

        @media (max-width: 1024px) {
            .landing {
                grid-template-columns: 1fr;
            }

            .highlights {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <main class="landing-shell">
        <section class="landing" aria-labelledby="landing-title">
            <div class="hero">
                <p class="eyebrow"><span class="dot"></span>{{ t('landing.badge') }}</p>
                <h1 id="landing-title" class="hero-title">{{ $title }}</h1>
                <p class="hero-lead">
                    {{ t('landing.lead') }}
                </p>

                <div class="actions">
                    <a class="btn btn-primary" href="/">{{ t('landing.actions.open_app') }}</a>
                    <a class="btn btn-secondary" href="https://github.com/idei/usim-framework" target="_blank" rel="noopener">{{ t('landing.actions.documentation') }}</a>
                </div>

                <div class="highlights">
                    <div class="chip">
                        <strong>{{ t('landing.highlights.screen_backend_title') }}</strong>
                        <span>{{ t('landing.highlights.screen_backend_text') }}</span>
                    </div>
                    <div class="chip">
                        <strong>{{ t('landing.highlights.incremental_title') }}</strong>
                        <span>{{ t('landing.highlights.incremental_text') }}</span>
                    </div>
                    <div class="chip">
                        <strong>{{ t('landing.highlights.auth_i18n_title') }}</strong>
                        <span>{{ t('landing.highlights.auth_i18n_text') }}</span>
                    </div>
                </div>
            </div>

            <aside class="panel" aria-label="Quick start summary">
                <p class="panel-title">{{ t('landing.quickstart.title') }}</p>
                <pre class="code">class Home extends Screen {
    protected function buildBaseUI(Container $c): void {
        $c->add(UI::card('welcome')
            ->body('Your first screen is ready.'));
    }
}</pre>
                <ul class="panel-list">
                    <li>{{ t('landing.quickstart.steps.first') }}</li>
                    <li>{{ t('landing.quickstart.steps.second') }}</li>
                    <li>{{ t('landing.quickstart.steps.third') }}</li>
                </ul>
            </aside>
        </section>
    </main>
</div>
