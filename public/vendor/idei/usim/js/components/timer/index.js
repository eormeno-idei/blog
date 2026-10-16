/**
 * Timer Component
 *
 * Non-visual component that triggers backend actions periodically (polling)
 * or once after a delay (one-shot).
 */
class TimerComponent extends UIComponent {
    constructor(id, config) {
        super(id, config);
        this.timerHandle = null;
        this.consecutiveFailures = 0;
    }

    render() {
        const element = document.createElement('span');
        element.className = 'usim-timer';
        element.style.display = 'none';
        element.setAttribute('aria-hidden', 'true');
        this.applyCommonAttributes(element);
        this.element = element;

        this.startTimer();

        return element;
    }

    update(newConfig) {
        this.config = { ...this.config, ...newConfig };
        this.consecutiveFailures = 0;
        this.startTimer();
    }

    getInterval() {
        const interval = Number(this.config.interval);
        return Number.isFinite(interval) && interval > 0 ? interval : 5000;
    }

    isEnabled() {
        return this.config.enabled !== false && Boolean(this.config.action);
    }

    startTimer() {
        this.clearTimer();

        if (!this.isEnabled()) {
            return;
        }

        if (this.config.immediate) {
            this.triggerAction();
        }

        const interval = this.getInterval();

        if (this.config.repeat) {
            this.timerHandle = setInterval(() => {
                this.triggerAction();
            }, interval);
        } else {
            this.timerHandle = setTimeout(() => {
                this.triggerAction();
            }, interval);
        }
    }

    clearTimer() {
        if (this.timerHandle !== null) {
            clearInterval(this.timerHandle);
            clearTimeout(this.timerHandle);
            this.timerHandle = null;
        }
    }

    async triggerAction() {
        const action = this.config.action;
        if (!action) {
            return;
        }

        const params = (this.config.parameters && typeof this.config.parameters === 'object')
            ? { ...this.config.parameters }
            : {};

        if (this.config.name) {
            params.timer_name = this.config.name;
        }

        try {
            const result = await this.sendEventToBackend('timeout', action, params);
            if (result === null) {
                this.consecutiveFailures++;
                if (this.consecutiveFailures >= 3) {
                    console.warn(`⚠️ Timer "${this.id}" paused after ${this.consecutiveFailures} consecutive failures.`);
                    this.clearTimer();
                }
            } else {
                this.consecutiveFailures = 0;
            }
        } catch (_err) {
            this.consecutiveFailures++;
            if (this.consecutiveFailures >= 3) {
                this.clearTimer();
            }
        }
    }

    destroy() {
        this.clearTimer();
    }
}

window.TimerComponent = TimerComponent;

if (window.USIM_COMPONENTS?.register) {
    window.USIM_COMPONENTS.register('timer', (id, config) => new TimerComponent(id, config), {
        source: 'builtin',
    });
}

