// Consentement cookies (T22) : initialisation de tarteaucitron + bouton « Gérer mes préférences ».
// Le script inline du gabarit (components/site/consent.blade.php) a déjà posé
// `gtag('consent', 'default', denied)` AVANT ce fichier. Sans <script id="consent-config">
// (local, préprod), rien ne se charge et le bouton du pied de page est absent.

const PANEL_RETRY_DELAY_MS = 100;
const PANEL_RETRY_MAX = 50; // 5 secondes au total

/** Met à jour Google Consent Mode quand l'utilisateur accepte/refuse Google Tag Manager. */
function updateConsent(granted) {
    if (typeof window.gtag !== 'function') {
        return;
    }
    window.gtag('consent', 'update', {
        analytics_storage: granted ? 'granted' : 'denied',
    });
}

/** Ouvre le panneau tarteaucitron ; renvoie false s'il n'est pas encore prêt. */
function openPanel() {
    const tac = window.tarteaucitron;
    if (!tac || !tac.userInterface || !document.getElementById('tarteaucitron')) {
        return false;
    }
    tac.userInterface.openPanel();
    return true;
}

/** tarteaucitron construit son panneau au « load » de la page : on attend qu'il soit prêt. */
function openPanelWhenReady(attempt = 0) {
    if (openPanel() || attempt >= PANEL_RETRY_MAX) {
        return;
    }
    window.setTimeout(() => openPanelWhenReady(attempt + 1), PANEL_RETRY_DELAY_MS);
}

function initConsent() {
    const configElement = document.getElementById('consent-config');
    if (!configElement || !window.tarteaucitron) {
        return;
    }

    let config;
    try {
        config = JSON.parse(configElement.textContent);
    } catch (e) {
        return;
    }

    // Écouteurs posés avant init() : tarteaucitron peut émettre « consentModeOk »
    // dès l'initialisation si un choix est déjà mémorisé.
    document.addEventListener('googletagmanager_consentModeOk', () => updateConsent(true));
    document.addEventListener('googletagmanager_consentModeKo', () => updateConsent(false));

    window.tarteaucitron.init({
        privacyUrl: config.privacyUrl,
        hashtag: '#cookies',
        cookieName: 'tarteaucitron',
        orientation: 'bottom',
        showAlertSmall: false,
        cookieslist: false,
        showIcon: false, // le bouton permanent est dans le pied de page
        highPrivacy: true, // rien ne se charge avant un choix explicite
        AcceptAllCta: true,
        DenyAllCta: true,
        removeCredit: true,
        moreInfoLink: true,
        useExternalCss: false,
        useExternalJs: false,
        mandatory: true,
        // Consent Mode géré ici (default dans le gabarit, update ci-dessus) :
        // GTM n'est jamais chargé tant que l'utilisateur n'a pas accepté.
        googleConsentMode: false,
    });

    window.tarteaucitron.user.googletagmanagerId = config.gtmId;
    (window.tarteaucitron.job = window.tarteaucitron.job || []).push('googletagmanager');
}

function bindManagerButtons() {
    document.querySelectorAll('[data-tarteaucitron-manager]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            openPanelWhenReady();
        });
    });
}

function boot() {
    bindManagerButtons();
    // tarteaucitron.min.js est chargé en `defer` avant ce module : il est déjà défini ici.
    initConsent();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
