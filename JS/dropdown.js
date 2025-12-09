document.addEventListener('DOMContentLoaded', () => {
    const dropdowns = document.querySelectorAll('.mobile-actions .dropdown');
    dropdowns.forEach(dropdown => {
        dropdown.addEventListener('click', () => {
            const content = dropdown.querySelector('.dropdown-content');
            content.style.display = content.style.display === 'block' ? 'none' : 'block';
        });
    });
});