// Toggle mobile menu
document.querySelector('.burger-menu').addEventListener('click', () => {
    const mobileNav = document.querySelector('.mobile-nav');
    const burgerMenu = document.querySelector('.burger-menu');
    mobileNav.classList.toggle('active');
    burgerMenu.classList.toggle('active');
});

// Form validation for register
const registerForm = document.getElementById('registerForm');
if (registerForm) {
    registerForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        if (password !== confirmPassword) {
            alert('Passwords do not match!');
            return;
        }
        // Here you can add logic to submit the form (e.g., to a server)
        alert('Registration successful!');
    });
}

// Form submission for login
const loginForm = document.getElementById('loginForm');
if (loginForm) {
    loginForm.addEventListener('submit', (e) => {
        e.preventDefault();
        // Here you can add logic to submit the form (e.g., to a server)
        alert('Login successful!');
    });
}