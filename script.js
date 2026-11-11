/**
 * CommitTrack - Client-Side Interactive Logic
 * Vanilla JavaScript (No Node.js / React / external CDNs required)
 */

document.addEventListener('DOMContentLoaded', () => {
    initPasswordToggles();
    initRoleSelector();
    initSignupValidation();
    initLoginValidation();
    initRememberMe();
    initGlobalSearch();
});

/**
 * Toggle password visibility between hidden and plain text
 */
function initPasswordToggles() {
    const toggleButtons = document.querySelectorAll('.password-toggle-btn');

    toggleButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const targetId = btn.getAttribute('data-target');
            const input = document.getElementById(targetId);

            if (!input) return;

            if (input.type === 'password') {
                input.type = 'text';
                btn.innerHTML = `
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                        <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>
                `;
                btn.setAttribute('aria-label', 'Hide password');
            } else {
                input.type = 'password';
                btn.innerHTML = `
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                `;
                btn.setAttribute('aria-label', 'Show password');
            }
        });
    });
}

/**
 * Role selector card interaction in registration
 * Allows fluid selection between Student and Admin
 */
function initRoleSelector() {
    const studentCard = document.getElementById('roleStudentCard');
    const adminCard = document.getElementById('roleAdminCard');
    const studentRadio = document.getElementById('roleRadioStudent');
    const adminRadio = document.getElementById('roleRadioAdmin');
    const studentBadge = document.getElementById('studentBadge');
    const adminBadge = document.getElementById('adminBadge');
    const adminCodeSection = document.getElementById('adminCodeSection');

    if (!studentCard || !adminCard) return;

    function selectStudent() {
        if (studentRadio) studentRadio.checked = true;
        if (adminRadio) adminRadio.checked = false;

        studentCard.classList.add('active');
        adminCard.classList.remove('active');

        if (studentBadge) studentBadge.style.display = 'inline-block';
        if (adminBadge) adminBadge.style.display = 'none';
        if (adminCodeSection) adminCodeSection.style.display = 'none';
    }

    function selectAdmin() {
        if (adminRadio) adminRadio.checked = true;
        if (studentRadio) studentRadio.checked = false;

        adminCard.classList.add('active');
        studentCard.classList.remove('active');

        if (adminBadge) adminBadge.style.display = 'inline-block';
        if (studentBadge) studentBadge.style.display = 'none';
        if (adminCodeSection) adminCodeSection.style.display = 'block';
    }

    studentCard.addEventListener('click', (e) => {
        selectStudent();
    });

    adminCard.addEventListener('click', (e) => {
        selectAdmin();
    });

    if (studentRadio) {
        studentRadio.addEventListener('change', () => {
            if (studentRadio.checked) selectStudent();
        });
    }

    if (adminRadio) {
        adminRadio.addEventListener('change', () => {
            if (adminRadio.checked) selectAdmin();
        });
    }
}

/**
 * Client-side validation for the Sign Up form
 */
function initSignupValidation() {
    const form = document.getElementById('signupForm');
    if (!form) return;

    const nameInput = document.getElementById('signupName');
    const emailInput = document.getElementById('signupEmail');
    const passwordInput = document.getElementById('signupPassword');
    const confirmInput = document.getElementById('signupConfirmPassword');
    const errorContainer = document.getElementById('clientErrorContainer');

    function showError(message, inputToFocus) {
        if (!errorContainer) {
            alert(message);
            return;
        }
        errorContainer.textContent = message;
        errorContainer.style.display = 'flex';
        errorContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });

        if (inputToFocus) {
            inputToFocus.style.borderColor = '#dc2626';
            inputToFocus.focus();
        }
    }

    function hideError() {
        if (errorContainer) {
            errorContainer.style.display = 'none';
            errorContainer.textContent = '';
        }
        [nameInput, emailInput, passwordInput, confirmInput].forEach(inp => {
            if (inp) inp.style.borderColor = '';
        });
    }

    // Reset red border on input
    [nameInput, emailInput, passwordInput, confirmInput].forEach(inp => {
        if (inp) {
            inp.addEventListener('input', () => {
                inp.style.borderColor = '';
                if (errorContainer && errorContainer.style.display !== 'none') {
                    // keep error visible until next check or hide if empty
                }
            });
        }
    });

    // Real-time password match check
    if (confirmInput && passwordInput) {
        confirmInput.addEventListener('input', () => {
            if (confirmInput.value && passwordInput.value !== confirmInput.value) {
                confirmInput.style.borderColor = '#dc2626';
            } else {
                confirmInput.style.borderColor = '';
            }
        });
    }

    form.addEventListener('submit', (e) => {
        hideError();

        const name = nameInput ? nameInput.value.trim() : '';
        const email = emailInput ? emailInput.value.trim() : '';
        const password = passwordInput ? passwordInput.value : '';
        const confirm = confirmInput ? confirmInput.value : '';

        if (!name || name.length < 2) {
            e.preventDefault();
            showError('Please enter your full name (at least 2 characters).', nameInput);
            return;
        }

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!email || !emailRegex.test(email)) {
            e.preventDefault();
            showError('Please enter a valid email address.', emailInput);
            return;
        }

        if (!password || password.length < 6) {
            e.preventDefault();
            showError('Password must be at least 6 characters long.', passwordInput);
            return;
        }

        if (!confirm) {
            e.preventDefault();
            showError('Please confirm your password.', confirmInput);
            return;
        }

        if (password !== confirm) {
            e.preventDefault();
            showError('Passwords do not match. Please re-enter.', confirmInput);
            return;
        }
    });
}

/**
 * Client-side validation for the Login form
 */
function initLoginValidation() {
    const form = document.getElementById('loginForm');
    if (!form) return;

    const emailInput = document.getElementById('loginEmail');
    const passwordInput = document.getElementById('loginPassword');
    const errorContainer = document.getElementById('clientErrorContainer');

    function showError(message) {
        if (!errorContainer) {
            alert(message);
            return;
        }
        errorContainer.textContent = message;
        errorContainer.style.display = 'flex';
    }

    form.addEventListener('submit', (e) => {
        const email = emailInput ? emailInput.value.trim() : '';
        const password = passwordInput ? passwordInput.value : '';

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!email || !emailRegex.test(email)) {
            e.preventDefault();
            showError('Please enter a valid email address.');
            if (emailInput) emailInput.focus();
            return;
        }

        if (!password) {
            e.preventDefault();
            showError('Please enter your password.');
            if (passwordInput) passwordInput.focus();
            return;
        }
    });
}

/**
 * Handle "Remember Me" locally
 */
function initRememberMe() {
    const rememberCheckbox = document.getElementById('rememberMeCheckbox');
    const emailInput = document.getElementById('loginEmail');

    if (!rememberCheckbox || !emailInput) return;

    const savedEmail = localStorage.getItem('committrack_remembered_email');
    if (savedEmail) {
        emailInput.value = savedEmail;
        rememberCheckbox.checked = true;
    }

    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', () => {
            if (rememberCheckbox.checked) {
                localStorage.setItem('committrack_remembered_email', emailInput.value.trim());
            } else {
                localStorage.removeItem('committrack_remembered_email');
            }
        });
    }
}

/**
 * Live search filter for tables and cards across all dashboard pages
 */
function initGlobalSearch() {
    const searchInput = document.getElementById('globalSearch');
    if (!searchInput) return;

    searchInput.addEventListener('input', () => {
        const query = searchInput.value.toLowerCase().trim();

        // 1. Filter table rows
        const rows = document.querySelectorAll('table tbody tr');
        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            row.style.display = text.includes(query) ? '' : 'none';
        });

        // 2. Filter task cards
        const taskCards = document.querySelectorAll('.task-card');
        taskCards.forEach(card => {
            const text = card.innerText.toLowerCase();
            card.style.display = text.includes(query) ? '' : 'none';
        });

        // 3. Filter classroom cards
        const clsCards = document.querySelectorAll('.cls-card');
        clsCards.forEach(card => {
            const text = card.innerText.toLowerCase();
            card.style.display = text.includes(query) ? '' : 'none';
        });
    });
}

