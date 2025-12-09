window.addEventListener('scroll', () => {
    const scrollBtn = document.querySelector('.scroll-to-top');
    scrollBtn.style.display = window.scrollY > 300 ? 'block' : 'none';
});

function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        document.querySelector(this.getAttribute('href')).scrollIntoView({
            behavior: 'smooth'
        });
        const mobileNav = document.querySelector('.mobile-nav');
        const burgerMenu = document.querySelector('.burger-menu');
        mobileNav.classList.remove('active');
        burgerMenu.classList.remove('active');
    });
});

window.addEventListener('scroll', () => {
    const header = document.querySelector('header');
    header.classList.toggle('scrolled', window.scrollY > 50);
});

document.querySelector('.burger-menu').addEventListener('click', () => {
    const mobileNav = document.querySelector('.mobile-nav');
    const burgerMenu = document.querySelector('.burger-menu');
    mobileNav.classList.toggle('active');
    burgerMenu.classList.toggle('active');
});

document.addEventListener('DOMContentLoaded', () => {
    const infoButtons = document.querySelectorAll('.info-btn');
    infoButtons.forEach(button => {
        button.addEventListener('click', () => {
            const target = button.getAttribute('data-target');
            const items = document.querySelectorAll('.info-item');
            const buttons = document.querySelectorAll('.info-btn');

            // Hide all items and remove active class from buttons
            items.forEach(item => {
                item.classList.remove('active');
                item.style.display = 'none';
            });
            buttons.forEach(btn => btn.classList.remove('active'));

            // Show the targeted item and mark button as active
            const targetItem = document.getElementById(target);
            targetItem.style.display = 'block';
            targetItem.classList.add('active');
            button.classList.add('active');
        });
    });

    // IntersectionObserver for section animations
    const sections = document.querySelectorAll('section, footer');
    const observerOptions = {
        root: null,
        rootMargin: '0px',
        threshold: 0.1 // Trigger when 10% of the section is visible
    };

    const observer = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target); // Stop observing once visible
            }
        });
    }, observerOptions);

    sections.forEach(section => {
        section.classList.add('section-hidden'); // Add initial hidden class
        observer.observe(section);
    });
});

document.addEventListener("DOMContentLoaded", () => {
    const langSelect = document.querySelectorAll(".lang-select");

    langSelect.forEach(select => {
        const selected = select.querySelector(".selected");
        const optionsList = select.querySelectorAll(".dropdown-list li");

        // Открытие/закрытие меню
        selected.addEventListener("click", () => {
            select.classList.toggle("active");
        });

        // Выбор языка
        optionsList.forEach(option => {
            option.addEventListener("click", () => {
                selected.textContent = option.textContent;
                select.classList.remove("active");
                // при желании можно добавить перевод
                console.log("Выбран язык:", option.dataset.value);
            });
        });

        // Закрытие при клике вне
        document.addEventListener("click", (e) => {
            if (!select.contains(e.target)) select.classList.remove("active");
        });
    });
});
