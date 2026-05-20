// ==UserScript==
// @name         EMR Successful Login Logger (SPA – SIGN OFF)
// @namespace    http://hospital.local/
// @version      5.2
// @description  Logs only successful logins by detecting the SIGN OFF button
// @author       IT Department
// @include      *://*/origin/*
// @grant        GM_xmlhttpRequest
// ==/UserScript==

(function() {
    'use strict';

    // Prevent duplicate script injection
    if (window.__emr_logger_instance) {
        console.error('EMR Logger: duplicate instance detected, aborting.');
        return;
    }
    window.__emr_logger_instance = true;

    // ===== CONFIGURATION =====
    const LOG_SERVER = 'http://172.16.88.69:4444/log';   // dev
    // const LOG_SERVER = 'http://172.16.88.59:4444/log'; // prod
    const TOKEN = 'change-me-to-a-random-string';
    const SUCCESS_SELECTOR = 'a.red[onclick*="Logout"]';
    const STORAGE_KEY = 'emr_pending_login';
    // =========================

    function generateNonce() {
        if (window.crypto && window.crypto.getRandomValues) {
            var arr = new Uint32Array(2);
            window.crypto.getRandomValues(arr);
            return arr[0].toString(16) + '-' + arr[1].toString(16) + '-' + Date.now().toString(16);
        }
        return Math.random().toString(36).substring(2) + Date.now().toString(36);
    }

    function flushPendingLogin() {
        const stored = sessionStorage.getItem(STORAGE_KEY);
        if (!stored) return;

        let data;
        try {
            data = JSON.parse(stored);
        } catch(e) {
            sessionStorage.removeItem(STORAGE_KEY);
            return;
        }

        if (!data.username || !data.timestamp) {
            sessionStorage.removeItem(STORAGE_KEY);
            return;
        }

        // Clear immediately to prevent any possible double‑send
        sessionStorage.removeItem(STORAGE_KEY);

        const payload = {
            nonce: generateNonce(),   // ← fixed unique ID
            timestamp: data.timestamp,
            username: data.username,
            userAgent: navigator.userAgent,
            platform: navigator.platform,
            language: navigator.language,
            url: window.location.href,
            token: TOKEN
        };

        GM_xmlhttpRequest({
            method: 'POST',
            url: LOG_SERVER,
            data: JSON.stringify(payload),
            headers: { 'Content-Type': 'application/json' },
            onload: function(resp) {
                if (resp.status === 200) {
                    console.log('EMR Login Logger: successful login recorded for', data.username);
                } else {
                    console.warn('EMR Login Logger: send failed, status', resp.status);
                }
            },
            onerror: function(err) {
                console.error('EMR Login Logger: network error', err);
            }
        });
    }

    function attachToLoginForm(loginForm) {
        if (loginForm.__emr_logger_attached) return;
        loginForm.__emr_logger_attached = true;

        console.log('EMR Login Logger: login form found, will store credentials on submit');
        loginForm.addEventListener('submit', function(e) {
            const usernameField = document.getElementById('username');
            const username = usernameField ? usernameField.value.trim() : 'unknown';
            if (username === '') return;

            const pending = {
                username: username,
                timestamp: new Date().toISOString()
            };
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(pending));
            console.log('EMR Login Logger: stored pending login for', username);
        });
    }

    function startObserver() {
        const observer = new MutationObserver(function(mutations) {
            const successElement = document.querySelector(SUCCESS_SELECTOR);
            if (successElement && sessionStorage.getItem(STORAGE_KEY)) {
                console.log('EMR Login Logger: SIGN OFF button detected, flushing pending login');
                flushPendingLogin();
                observer.disconnect();
                return;
            }

            const loginForm = document.querySelector('form.login-form');
            if (loginForm) {
                attachToLoginForm(loginForm);
            }
        });

        observer.observe(document.documentElement, {
            childList: true,
            subtree: true
        });

        // Immediate check
        const alreadySuccess = document.querySelector(SUCCESS_SELECTOR);
        const alreadyForm = document.querySelector('form.login-form');

        if (alreadySuccess && sessionStorage.getItem(STORAGE_KEY)) {
            console.log('EMR Login Logger: already on success page with pending login');
            flushPendingLogin();
            return;
        }

        if (alreadyForm) {
            attachToLoginForm(alreadyForm);
        }
    }

    startObserver();
})();