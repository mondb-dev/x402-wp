/**
 * Public JavaScript for X402 Paywall
 *
 * @package X402_Paywall
 */

(function($) {
    'use strict';

    function getCookie(name) {
        var nameEQ = name + '=';
        var cookies = document.cookie ? document.cookie.split(';') : [];

        for (var i = 0; i < cookies.length; i++) {
            var cookie = cookies[i].trim();

            if (cookie.indexOf(nameEQ) === 0) {
                return cookie.substring(nameEQ.length);
            }
        }

        return null;
    }

    function deleteCookie(name, path) {
        var attributes = ['Max-Age=0', 'path=' + (path || '/'), 'SameSite=Lax'];

        if (window.location && window.location.protocol === 'https:') {
            attributes.push('Secure');
        }

        document.cookie = name + '=; ' + attributes.join('; ');
    }

    function decodeErrorPayload(rawValue) {
        if (typeof rawValue !== 'string' || rawValue === '') {
            return null;
        }

        if (typeof window.atob !== 'function') {
            return null;
        }

        try {
            var decoded = decodeURIComponent(rawValue);
            var json = window.atob(decoded);
            return JSON.parse(json);
        } catch (error) {
            console.error('X402 Paywall: Failed to decode error payload', error);
            return null;
        }
    }

    function buildMetaText(data) {
        var parts = [];

        if (data.status) {
            parts.push('HTTP ' + data.status);
        }

        if (data.code) {
            parts.push(data.code);
        }

        return parts.length ? parts.join(' · ') : '';
    }

    function getLocalizedString(key, fallback) {
        if (window.x402PaywallData && Object.prototype.hasOwnProperty.call(window.x402PaywallData, key)) {
            return window.x402PaywallData[key];
        }

        return fallback;
    }

    function renderErrorNotice(data) {
        var container = document.querySelector('.x402-paywall-message');

        if (!container) {
            return;
        }

        var message = data.message || getLocalizedString('defaultErrorMessage', 'We were unable to verify your payment. Please try again.');
        var metaText = buildMetaText(data);
        var referenceLabel = getLocalizedString('referenceLabel', 'Support reference: %s');
        var dismissLabel = getLocalizedString('dismissLabel', 'Dismiss');
        var heading = getLocalizedString('errorTitle', 'Payment Error');

        var notice = document.createElement('div');
        notice.className = 'x402-paywall-error-notice';
        notice.setAttribute('role', 'alert');
        notice.setAttribute('aria-live', 'polite');

        var title = document.createElement('div');
        title.className = 'x402-paywall-error-header';
        title.textContent = heading;
        notice.appendChild(title);

        var body = document.createElement('p');
        body.className = 'x402-paywall-error-message';
        body.textContent = message;
        notice.appendChild(body);

        if (metaText) {
            var meta = document.createElement('p');
            meta.className = 'x402-paywall-error-meta';
            meta.textContent = metaText;
            notice.appendChild(meta);
        }

        if (data.reference) {
            var reference = document.createElement('p');
            reference.className = 'x402-paywall-error-reference';
            reference.textContent = referenceLabel.replace('%s', data.reference);
            notice.appendChild(reference);
        }

        var dismissButton = document.createElement('button');
        dismissButton.type = 'button';
        dismissButton.className = 'x402-paywall-error-dismiss';
        dismissButton.setAttribute('aria-label', dismissLabel);
        dismissButton.textContent = dismissLabel;
        dismissButton.addEventListener('click', function() {
            notice.remove();
        });

        notice.appendChild(dismissButton);

        container.insertBefore(notice, container.firstChild);
    }

    function displayErrorFromCookie() {
        var rawPayload = getCookie('x402_paywall_error');

        if (!rawPayload) {
            return;
        }

        var path = getLocalizedString('cookiePath', '/');
        deleteCookie('x402_paywall_error', path);

        var data = decodeErrorPayload(rawPayload);

        if (!data) {
            return;
        }

        renderErrorNotice(data);
    }

    $(document).ready(function() {
        displayErrorFromCookie();
    });

})(jQuery);
