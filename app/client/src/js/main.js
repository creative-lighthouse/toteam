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
});
