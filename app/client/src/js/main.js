import Swiper, {Autoplay, EffectCoverflow, EffectFade, Navigation, Pagination} from 'swiper';
import GLightbox from "glightbox";

document.addEventListener("DOMContentLoaded", function (event) {

    // INIT MENUBUTTON
    const menu_button = document.querySelector('[data-behaviour="toggle-menu"]');
    menu_button.addEventListener('click', () => {
        document.body.classList.toggle('body--show');
    })

    // INIT LIGHTBOX
    const lightbox = GLightbox({
        selector: '[data-gallery="gallery"]',
        touchNavigation: true,
        loop: true,
    });

    // INIT SWIPER
    const sliders = document.querySelectorAll('.swiper');
    sliders.forEach(function (slider) {
        const autoSwiper = slider.classList.contains('swiper--auto');
        const swiper = new Swiper(slider, {
            // configure Swiper to use modules
            modules: [Pagination, Navigation, Autoplay, EffectFade],
            effect: 'slide',
            fadeEffect: {
                crossFade: true
            },
            direction: 'vertical',
            loop: true,

            autoplay: autoSwiper ? {
                delay: 5000,
            } : false,

            // Navigation arrows
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },

            pagination: {
                el: '.swiper-pagination',
                type: 'bullets',
                clickable: true,
            },
        });
    });

    // FIXED HEADER
    window.addEventListener('scroll', () => {
        if (document.documentElement.scrollTop > 30 || document.body.scrollTop > 30){
            document.body.classList.add('menu--fixed');
        } else {
            document.body.classList.remove('menu--fixed');
        }
    });

    document.querySelectorAll('dialog[data-autoopen="true"]').forEach(dialog => {
        dialog.showModal();
        const statusMessage = document.createElement('div');
        statusMessage.className = `status-message status-message--success`;
        statusMessage.textContent = "Gespeichert";
        dialog.appendChild(statusMessage);
        setTimeout(() => {
            statusMessage.classList.add('status-message--hide');
            //Delete eventID from URL
            const url = new URL(window.location);
            url.searchParams.delete('eventID');
            window.history.replaceState({}, document.title, url.toString());
        }, 3000);
        setTimeout(() => {
            statusMessage.remove();
        }, 6000);
    });

    //Close dialog on outside click
    document.querySelectorAll('dialog').forEach(dialog => {
        dialog.addEventListener('click', (event) => {
            const rect = dialog.getBoundingClientRect();
            const isInDialog = (rect.top <= event.clientY && event.clientY <= rect.top + rect.height
                && rect.left <= event.clientX && event.clientX <= rect.left + rect.width);
            if (!isInDialog) {
                dialog.close();
            }
        });
    });
});
