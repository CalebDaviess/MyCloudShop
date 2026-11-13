const menuButton = document.querySelector('.mobile-menu-toggle');
const navigation = document.querySelector('#primary-navigation, .store-nav, .header-actions');

if (menuButton && navigation) {
    const closeMenu = () => {
        menuButton.setAttribute('aria-expanded', 'false');
        navigation.classList.remove('is-open');
    };

    menuButton.addEventListener('click', () => {
        const isOpen = menuButton.getAttribute('aria-expanded') === 'true';
        menuButton.setAttribute('aria-expanded', String(!isOpen));
        navigation.classList.toggle('is-open', !isOpen);
    });

    navigation.addEventListener('click', (event) => {
        if (event.target instanceof HTMLAnchorElement) {
            closeMenu();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMenu();
        }
    });
}

document.querySelectorAll('.sort-select select').forEach((select) => {
    select.addEventListener('change', () => {
        select.form?.requestSubmit();
    });
});

// Live countdown timer for Lightning Deals & Prime next-day cutoff
const countdownElements = document.querySelectorAll('[data-deal-countdown]');
if (countdownElements.length > 0) {
    let remainingSeconds = 4 * 3600 + 27 * 60 + 18;
    const formatTime = (totalSec) => {
        const hrs = String(Math.floor(totalSec / 3600)).padStart(2, '0');
        const mins = String(Math.floor((totalSec % 3600) / 60)).padStart(2, '0');
        const secs = String(totalSec % 60).padStart(2, '0');
        return `${hrs}:${mins}:${secs}`;
    };
    window.setInterval(() => {
        remainingSeconds = remainingSeconds > 0 ? remainingSeconds - 1 : 4 * 3600 + 27 * 60 + 18;
        const formatted = formatTime(remainingSeconds);
        countdownElements.forEach((el) => {
            el.textContent = formatted;
        });
    }, 1000);
}

const trackingStatus = document.querySelector('[data-tracking-live]');
if (trackingStatus) {
    const refreshTracking = async () => {
        try {
            const response = await fetch('order_status.php', {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) {
                throw new Error(`Order updates returned ${response.status}`);
            }
            const data = await response.json();
            const statuses = ['Order received', 'Being prepared', 'On its way', 'Delivered'];
            data.orders.forEach((order) => {
                const card = document.querySelector(`.order-card[data-order-id="${order.id}"]`);
                if (!card || !statuses.includes(order.status)) {
                    return;
                }
                const currentStatus = card.querySelector('[data-order-status]');
                if (currentStatus) {
                    currentStatus.textContent = order.status;
                }
                const currentIndex = statuses.indexOf(order.status);
                card.querySelectorAll('[data-order-step]').forEach((step) => {
                    const stepIndex = statuses.indexOf(step.getAttribute('data-order-step'));
                    step.classList.toggle('is-complete', stepIndex <= currentIndex);
                    if (stepIndex === currentIndex) {
                        step.setAttribute('aria-current', 'step');
                    } else {
                        step.removeAttribute('aria-current');
                    }
                });
                const updated = card.querySelector('[data-order-updated]');
                if (updated && order.updated_at) {
                    updated.textContent = `Updated ${new Date(order.updated_at).toLocaleString()}`;
                }
            });
            trackingStatus.textContent = 'Order updates refresh automatically.';
        } catch (error) {
            console.error('CloudCart live order updates are unavailable.', error);
            trackingStatus.textContent = 'Live updates are temporarily unavailable. Refresh to see the latest tracking.';
        }
    };

    window.setInterval(refreshTracking, 20000);
}
