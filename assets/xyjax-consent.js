(function () {
  'use strict';

  if (typeof XyjaxConsentLogger === 'undefined') {
    return;
  }

  const config = XyjaxConsentLogger;

  function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) {
      return parts.pop().split(';').shift();
    }
    return '';
  }

  function setCookie(name, value, days) {
    const expires = new Date(Date.now() + days * 864e5).toUTCString();
    document.cookie = `${name}=${encodeURIComponent(value)}; expires=${expires}; path=/; SameSite=Lax`;
  }

  function hasCurrentConsent() {
    const raw = getCookie(config.cookieName);
    if (!raw) return false;
    try {
      const parsed = JSON.parse(decodeURIComponent(raw));
      return parsed.version === config.consentVersion;
    } catch (e) {
      return false;
    }
  }

  function buildBanner() {
    const banner = document.createElement('div');
    banner.className = 'xyjax-consent-banner';
    banner.setAttribute('role', 'dialog');
    banner.setAttribute('aria-live', 'polite');
    banner.innerHTML = `
      <div class="xyjax-consent-box">
        <h2>${escapeHtml(config.title)}</h2>
        <p>${escapeHtml(config.message)}</p>
        <div class="xyjax-consent-options" hidden>
          <label><input type="checkbox" checked disabled data-category="necessary"> ${escapeHtml(config.labels.necessary)}</label>
          <label><input type="checkbox" data-category="analytics"> ${escapeHtml(config.labels.analytics)}</label>
          <label><input type="checkbox" data-category="marketing"> ${escapeHtml(config.labels.marketing)}</label>
          <label><input type="checkbox" data-category="affiliate"> ${escapeHtml(config.labels.affiliate)}</label>
        </div>
        <div class="xyjax-consent-actions">
          <button type="button" data-action="reject">${escapeHtml(config.labels.rejectAll)}</button>
          <button type="button" data-action="customize">${escapeHtml(config.labels.customize)}</button>
          <button type="button" data-action="accept" class="xyjax-primary">${escapeHtml(config.labels.acceptAll)}</button>
          <button type="button" data-action="save" class="xyjax-primary" hidden>${escapeHtml(config.labels.save)}</button>
        </div>
        ${config.privacyUrl ? `<a href="${escapeAttribute(config.privacyUrl)}">${escapeHtml(config.labels.privacy)}</a>` : ''}
      </div>`;
    document.body.appendChild(banner);
    bindBanner(banner);
  }

  function bindBanner(banner) {
    banner.addEventListener('click', function (event) {
      const target = event.target;
      if (!(target instanceof HTMLElement)) return;
      const action = target.getAttribute('data-action');
      if (!action) return;

      if (action === 'customize') {
        banner.querySelector('.xyjax-consent-options').hidden = false;
        banner.querySelector('[data-action="save"]').hidden = false;
        banner.querySelector('[data-action="accept"]').hidden = true;
        target.hidden = true;
        return;
      }

      if (action === 'accept') {
        saveConsent({ analytics: 1, marketing: 1, affiliate: 1 }, banner);
      }

      if (action === 'reject') {
        saveConsent({ analytics: 0, marketing: 0, affiliate: 0 }, banner);
      }

      if (action === 'save') {
        saveConsent({
          analytics: banner.querySelector('[data-category="analytics"]').checked ? 1 : 0,
          marketing: banner.querySelector('[data-category="marketing"]').checked ? 1 : 0,
          affiliate: banner.querySelector('[data-category="affiliate"]').checked ? 1 : 0
        }, banner);
      }
    });
  }

  function saveConsent(preferences, banner) {
    const payload = {
      version: config.consentVersion,
      necessary: 1,
      analytics: preferences.analytics ? 1 : 0,
      marketing: preferences.marketing ? 1 : 0,
      affiliate: preferences.affiliate ? 1 : 0
    };

    setCookie(config.cookieName, JSON.stringify(payload), 365);
    document.dispatchEvent(new CustomEvent('xyjaxConsentSaved', { detail: payload }));

    const formData = new FormData();
    formData.append('action', 'xyjax_save_consent');
    formData.append('nonce', config.nonce);
    formData.append('analytics', String(payload.analytics));
    formData.append('marketing', String(payload.marketing));
    formData.append('affiliate', String(payload.affiliate));
    formData.append('page_url', window.location.href);

    fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: formData })
      .finally(function () {
        if (banner && banner.parentNode) {
          banner.parentNode.removeChild(banner);
        }
      });
  }

  function escapeHtml(value) {
    return String(value).replace(/[&<>'"]/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' })[char];
    });
  }

  function escapeAttribute(value) {
    return escapeHtml(value).replace(/`/g, '&#096;');
  }

  document.addEventListener('click', function (event) {
    const target = event.target;
    if (target instanceof HTMLElement && target.classList.contains('xyjax-consent-open')) {
      event.preventDefault();
      buildBanner();
    }
  });

  document.addEventListener('DOMContentLoaded', function () {
    if (!hasCurrentConsent()) {
      buildBanner();
    }
  });
})();
