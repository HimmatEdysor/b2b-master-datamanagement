/**
 * Registration form: custom inline validation + AJAX submit.
 */
function initRegisterForm() {
    const form = document.getElementById('register-form');
    if (!form || form.dataset.registerInit === '1') {
        return;
    }
    form.dataset.registerInit = '1';

    const alertBox = document.getElementById('register-alert');
    const successPanel = document.getElementById('register-success-panel');
    const submitBtn = form.querySelector('[type="submit"]');
    const plansRequired = form.dataset.plansRequired === '1';
    const baseDomain = form.dataset.baseDomain || '';
    const tenantScheme = form.dataset.tenantScheme || 'https';
    const tenantPortSuffix = form.dataset.tenantPortSuffix || '';
    const storeUrl = form.action;

    const slugInput = document.getElementById('slug');
    const slugPreview = document.getElementById('slug-preview');
    const companyInput = document.getElementById('company_name');
    const customDomainInput = document.getElementById('custom_domain');

    const DOMAIN_RE = /^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i;
    const SLUG_RE = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;
    const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    const fieldLabels = {
        company_name: 'Company name',
        business_type: 'Business type',
        company_website: 'Company website',
        address_line: 'Office address',
        city: 'City',
        state: 'State / province',
        country: 'Country',
        slug: 'Subdomain slug',
        custom_domain: 'Custom domain',
        contact_name: 'Contact name',
        contact_designation: 'Job title',
        contact_email: 'Work email',
        contact_phone: 'Phone',
        support_email: 'Support email',
        subscription_plan_id: 'Subscription plan',
        terms: 'Terms of service',
        logo: 'Company logo',
    };

    function slugFromCompanyName(text) {
        return window.sanitizeTenantSlugInput(
            text
                .toLowerCase()
                .trim()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
        );
    }

    function updateSlugPreview(slug) {
        if (!slugPreview) {
            return;
        }
        slug = (slug || slugInput?.value || '').trim() || 'your-slug';
        slugPreview.textContent = tenantScheme + '://' + slug + '.' + baseDomain + tenantPortSuffix;
    }

    if (slugInput && window.bindTenantSlugInput) {
        window.bindTenantSlugInput(slugInput, { onChange: updateSlugPreview });
    }

    customDomainInput?.addEventListener('input', function () {
        const cleaned = customDomainInput.value.replace(/\s/g, '').toLowerCase();
        if (customDomainInput.value !== cleaned) {
            customDomainInput.value = cleaned;
        }
        clearFieldError('custom_domain');
    });
    customDomainInput?.addEventListener('keydown', function (e) {
        if (e.key === ' ') {
            e.preventDefault();
        }
    });

    companyInput?.addEventListener('blur', function () {
        if (!slugInput?.value.trim() && companyInput.value.trim()) {
            slugInput.value = slugFromCompanyName(companyInput.value);
            updateSlugPreview();
        }
    });

    document.querySelectorAll('.plan-option input[type=radio]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            document.querySelectorAll('.plan-option').forEach(function (el) {
                el.classList.remove('selected');
            });
            radio.closest('.plan-option')?.classList.add('selected');
            clearFieldError('subscription_plan_id');
        });
    });

    function getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function findFieldGroup(name) {
        const field = form.querySelector('[name="' + name + '"]');
        if (!field) {
            if (name === 'subscription_plan_id') {
                return form.querySelector('.plan-select-grid')?.closest('.form-section');
            }
            if (name === 'terms') {
                return form.querySelector('[name="terms"]')?.closest('.form-group');
            }
            if (name === 'logo') {
                return document.getElementById('logo-upload-root');
            }
            return null;
        }
        return field.closest('.form-group') || field.closest('.form-section') || field.closest('fieldset');
    }

    function getOrCreateErrorEl(name) {
        const group = findFieldGroup(name);
        if (!group) {
            return null;
        }
        let el = group.querySelector('.js-field-error[data-field="' + name + '"]');
        if (!el) {
            el = document.createElement('p');
            el.className = 'form-error js-field-error';
            el.dataset.field = name;
            el.setAttribute('role', 'alert');
            group.appendChild(el);
        }
        return el;
    }

    function clearFieldError(name) {
        const group = findFieldGroup(name);
        group?.classList.remove('has-error');
        const el = group?.querySelector('.js-field-error[data-field="' + name + '"]');
        if (el) {
            el.textContent = '';
            el.hidden = true;
        }
        const input = form.querySelector('[name="' + name + '"]');
        input?.classList.remove('input-invalid');
        if (name === 'subscription_plan_id') {
            form.querySelector('.plan-select-grid')?.classList.remove('has-error');
        }
    }

    function showFieldError(name, message) {
        const group = findFieldGroup(name);
        if (group) {
            group.classList.add('has-error');
        }
        const el = getOrCreateErrorEl(name);
        if (el) {
            el.textContent = message;
            el.hidden = false;
        }
        const input = form.querySelector('[name="' + name + '"]');
        input?.classList.add('input-invalid');
        if (name === 'subscription_plan_id') {
            form.querySelector('.plan-select-grid')?.classList.add('has-error');
        }
        if (name === 'logo') {
            const logoClient = document.getElementById('logo_client_error');
            if (logoClient) {
                logoClient.textContent = message;
                logoClient.hidden = false;
            }
        }
    }

    function clearAllErrors() {
        form.querySelectorAll('.js-field-error').forEach(function (el) {
            el.textContent = '';
            el.hidden = true;
        });
        form.querySelectorAll('.has-error').forEach(function (el) {
            el.classList.remove('has-error');
        });
        form.querySelectorAll('.input-invalid').forEach(function (el) {
            el.classList.remove('input-invalid');
        });
        const logoClient = document.getElementById('logo_client_error');
        if (logoClient) {
            logoClient.textContent = '';
            logoClient.hidden = true;
        }
    }

    function showAlert(message, type) {
        if (!alertBox) {
            return;
        }
        alertBox.textContent = message;
        alertBox.className = 'register-alert alert alert-' + (type === 'success' ? 'success' : 'error');
        alertBox.hidden = false;
        alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function hideAlert() {
        if (alertBox) {
            alertBox.hidden = true;
            alertBox.textContent = '';
        }
    }

    function val(name) {
        const el = form.querySelector('[name="' + name + '"]');
        if (!el) {
            return '';
        }
        if (el.type === 'checkbox') {
            return el.checked ? '1' : '';
        }
        if (el.type === 'radio') {
            const checked = form.querySelector('[name="' + name + '"]:checked');
            return checked ? checked.value : '';
        }
        return (el.value || '').trim();
    }

    function validateClient() {
        const errors = {};

        if (!val('company_name')) {
            errors.company_name = fieldLabels.company_name + ' is required.';
        }
        if (!val('business_type')) {
            errors.business_type = 'Please select a business type.';
        }
        const website = val('company_website');
        if (website) {
            try {
                new URL(website);
            } catch (e) {
                errors.company_website = 'Enter a valid URL (e.g. https://example.com).';
            }
        }
        if (!val('address_line')) {
            errors.address_line = fieldLabels.address_line + ' is required.';
        }
        if (!val('city')) {
            errors.city = fieldLabels.city + ' is required.';
        }
        if (!val('state')) {
            errors.state = fieldLabels.state + ' is required.';
        }
        if (!val('country')) {
            errors.country = fieldLabels.country + ' is required.';
        }

        const slug = slugInput ? window.sanitizeTenantSlugInput(slugInput.value) : val('slug');
        if (slugInput) {
            slugInput.value = slug;
        }
        if (!slug) {
            errors.slug = 'Subdomain is required.';
        } else if (!SLUG_RE.test(slug)) {
            errors.slug =
                'Subdomain can only use lowercase letters, numbers, and hyphens (no spaces).';
        }

        const customDomain = val('custom_domain');
        if (customDomain && !DOMAIN_RE.test(customDomain)) {
            errors.custom_domain = 'Enter a valid domain (e.g. crm.yourcompany.com).';
        }

        if (!val('contact_name')) {
            errors.contact_name = fieldLabels.contact_name + ' is required.';
        }
        if (!val('contact_designation')) {
            errors.contact_designation = fieldLabels.contact_designation + ' is required.';
        }
        const email = val('contact_email');
        if (!email) {
            errors.contact_email = fieldLabels.contact_email + ' is required.';
        } else if (!EMAIL_RE.test(email)) {
            errors.contact_email = 'Enter a valid email address.';
        }
        if (!val('contact_phone')) {
            errors.contact_phone = fieldLabels.contact_phone + ' is required.';
        }

        const supportEmail = val('support_email');
        if (supportEmail && !EMAIL_RE.test(supportEmail)) {
            errors.support_email = 'Enter a valid support email address.';
        }

        if (plansRequired && !val('subscription_plan_id')) {
            errors.subscription_plan_id = 'Please select a subscription plan.';
        }

        if (!val('terms')) {
            errors.terms = 'You must accept the terms of service to register.';
        }

        const logoError = window.getLogoUploadValidationError?.();
        if (logoError) {
            errors.logo = logoError;
        }

        return errors;
    }

    function applyServerErrors(errors) {
        clearAllErrors();
        const messages = [];
        Object.keys(errors).forEach(function (name) {
            const list = errors[name];
            const msg = Array.isArray(list) ? list[0] : String(list);
            showFieldError(name, msg);
            messages.push(msg);
        });
        if (messages.length) {
            showAlert(messages[0], 'error');
        }
    }

    function bindClearOnInput() {
        form.querySelectorAll('input, select, textarea').forEach(function (el) {
            if (!el.name) {
                return;
            }
            const event = el.type === 'checkbox' || el.type === 'radio' ? 'change' : 'input';
            el.addEventListener(event, function () {
                clearFieldError(el.name);
                hideAlert();
            });
        });
    }

    bindClearOnInput();

    function setSubmitting(loading) {
        form.classList.toggle('is-submitting', loading);
        if (submitBtn) {
            submitBtn.disabled = loading;
            submitBtn.dataset.originalText = submitBtn.dataset.originalText || submitBtn.textContent;
            submitBtn.textContent = loading ? 'Submitting…' : submitBtn.dataset.originalText;
        }
    }

    function showSuccess(data) {
        hideAlert();
        clearAllErrors();
        form.hidden = true;
        if (successPanel) {
            successPanel.hidden = false;
            const title = successPanel.querySelector('[data-success-company]');
            const msg = successPanel.querySelector('[data-success-message]');
            if (title && data.company) {
                title.textContent = data.company;
            }
            if (msg && data.message) {
                msg.textContent = data.message;
            }
            successPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else {
            showAlert(data.message || 'Registration submitted successfully.', 'success');
        }
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        hideAlert();
        clearAllErrors();

        if (customDomainInput?.value) {
            customDomainInput.value = customDomainInput.value.replace(/\s/g, '').toLowerCase();
        }

        const errors = validateClient();
        const keys = Object.keys(errors);
        if (keys.length) {
            keys.forEach(function (name) {
                showFieldError(name, errors[name]);
            });
            const firstGroup = findFieldGroup(keys[0]);
            firstGroup?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            showAlert('Please fix the highlighted fields below.', 'error');
            return;
        }

        const formData = new FormData(form);
        setSubmitting(true);

        fetch(storeUrl, {
            method: 'POST',
            body: formData,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        })
            .then(function (response) {
                return response
                    .json()
                    .catch(function () {
                        return {};
                    })
                    .then(function (data) {
                        return { ok: response.ok, status: response.status, data: data };
                    });
            })
            .then(function (result) {
                setSubmitting(false);
                if (result.ok && result.data.success) {
                    showSuccess(result.data);
                    return;
                }
                if (result.status === 422 && result.data.errors) {
                    applyServerErrors(result.data.errors);
                    const firstKey = Object.keys(result.data.errors)[0];
                    findFieldGroup(firstKey)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return;
                }
                showAlert(
                    result.data.message ||
                        'Something went wrong. Please try again or contact support.',
                    'error'
                );
            })
            .catch(function () {
                setSubmitting(false);
                showAlert('Network error. Check your connection and try again.', 'error');
            });
    });

    updateSlugPreview();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initRegisterForm);
} else {
    initRegisterForm();
}
