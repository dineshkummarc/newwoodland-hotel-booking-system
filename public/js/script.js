document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    /* =========================================================
       SCROLL TO TOP
    ========================================================= */
    var scrollTopBtn = document.getElementById('scrollTopBtn');
    if (scrollTopBtn) {
        window.addEventListener('scroll', function () {
            if (window.scrollY > 400) {
                scrollTopBtn.classList.add('show');
            } else {
                scrollTopBtn.classList.remove('show');
            }
        });
        scrollTopBtn.addEventListener('click', function (e) {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    /* =========================================================
       NAVBAR — SHRINK ON SCROLL
    ========================================================= */
    var header = document.querySelector('.header');
    if (header) {
        var onScroll = function () {
            header.classList.toggle('scrolled', window.scrollY > 40);
        };
        window.addEventListener('scroll', onScroll);
        onScroll();
    }

    /* =========================================================
       FAQ ACCORDION
    ========================================================= */
    document.querySelectorAll('.faq-question').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var item = btn.closest('.faq-item');
            var wasActive = item.classList.contains('active');

            item.parentElement.querySelectorAll('.faq-item').forEach(function (i) {
                i.classList.remove('active');
            });

            if (!wasActive) {
                item.classList.add('active');
            }
        });
    });

    /* =========================================================
       GALLERY FILTER
    ========================================================= */
    var filterButtons = document.querySelectorAll('.gallery-filters [data-filter]');
    var galleryItems = document.querySelectorAll('.gallery-item');

    if (filterButtons.length && galleryItems.length) {
        filterButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                filterButtons.forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');

                var filter = btn.getAttribute('data-filter');

                galleryItems.forEach(function (item) {
                    var show = filter === 'all' || item.getAttribute('data-category') === filter;
                    item.style.display = show ? '' : 'none';
                });
            });
        });
    }

    /* =========================================================
       GALLERY LIGHTBOX
    ========================================================= */
    var lightbox = document.getElementById('lightbox');
    if (lightbox) {
        var lightboxImg = lightbox.querySelector('img');
        var lightboxImages = Array.prototype.slice.call(document.querySelectorAll('.gallery-card img'));
        var currentIndex = 0;

        function openLightbox(index) {
            currentIndex = index;
            lightboxImg.src = lightboxImages[currentIndex].src;
            lightboxImg.alt = lightboxImages[currentIndex].alt;
            lightbox.classList.add('active');
        }

        function closeLightbox() {
            lightbox.classList.remove('active');
        }

        function showRelative(offset) {
            currentIndex = (currentIndex + offset + lightboxImages.length) % lightboxImages.length;
            lightboxImg.src = lightboxImages[currentIndex].src;
            lightboxImg.alt = lightboxImages[currentIndex].alt;
        }

        lightboxImages.forEach(function (img, index) {
            img.addEventListener('click', function () { openLightbox(index); });
        });

        var closeBtn = lightbox.querySelector('.lightbox-close');
        var prevBtn = lightbox.querySelector('.lightbox-prev');
        var nextBtn = lightbox.querySelector('.lightbox-next');

        if (closeBtn) closeBtn.addEventListener('click', closeLightbox);
        if (prevBtn) prevBtn.addEventListener('click', function () { showRelative(-1); });
        if (nextBtn) nextBtn.addEventListener('click', function () { showRelative(1); });

        lightbox.addEventListener('click', function (e) {
            if (e.target === lightbox) closeLightbox();
        });

        document.addEventListener('keydown', function (e) {
            if (!lightbox.classList.contains('active')) return;
            if (e.key === 'Escape') closeLightbox();
            if (e.key === 'ArrowLeft') showRelative(-1);
            if (e.key === 'ArrowRight') showRelative(1);
        });
    }

    /* =========================================================
       BOOKING DATE LOGIC + LIVE PRICE ESTIMATE (UX only —
       the server always recalculates the authoritative total)
    ========================================================= */
    var checkinInput = document.querySelector('[name="check_in"]');
    var checkoutInput = document.querySelector('[name="check_out"]');
    var roomSelect = document.querySelector('[name="room_id"]');
    var estimateBox = document.getElementById('bookingEstimate');

    function todayIso() {
        var d = new Date();
        d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
        return d.toISOString().split('T')[0];
    }

    function updateEstimate() {
        if (!checkinInput || !checkoutInput) return;

        if (checkinInput.value) {
            checkoutInput.min = checkinInput.value;
            if (checkoutInput.value && checkoutInput.value <= checkinInput.value) {
                var next = new Date(checkinInput.value);
                next.setDate(next.getDate() + 1);
                checkoutInput.value = next.toISOString().split('T')[0];
            }
        }

        if (!estimateBox || !roomSelect) return;

        var checkin = checkinInput.value;
        var checkout = checkoutInput.value;
        var option = roomSelect.options[roomSelect.selectedIndex];
        var price = option ? parseFloat(option.getAttribute('data-price')) : NaN;

        if (checkin && checkout && !isNaN(price)) {
            var nights = Math.round((new Date(checkout) - new Date(checkin)) / 86400000);
            if (nights > 0) {
                var total = nights * price;
                estimateBox.innerHTML =
                    '<strong>' + nights + '</strong> night' + (nights > 1 ? 's' : '') +
                    ' &times; NPR ' + price.toLocaleString() +
                    ' = <strong>NPR ' + total.toLocaleString() + '</strong> (estimated)';
                estimateBox.style.display = 'block';
                return;
            }
        }

        estimateBox.style.display = 'none';
        estimateBox.innerHTML = '';
    }

    if (checkinInput) {
        checkinInput.min = todayIso();
        checkinInput.addEventListener('change', updateEstimate);
    }
    if (checkoutInput) checkoutInput.addEventListener('change', updateEstimate);
    if (roomSelect) roomSelect.addEventListener('change', updateEstimate);
    updateEstimate();

    /* =========================================================
       LIVE AVAILABILITY CHECK
    ========================================================= */
    var availabilityBox = document.getElementById('availabilityResult');
    var submitBtn = document.getElementById('bookingSubmitBtn');

    function checkAvailability() {
        if (!roomSelect || !checkinInput || !checkoutInput || !availabilityBox) return;

        var roomId = roomSelect.value;
        var checkin = checkinInput.value;
        var checkout = checkoutInput.value;

        if (!roomId || !checkin || !checkout || checkout <= checkin) {
            availabilityBox.innerHTML = '';
            return;
        }

        availabilityBox.innerHTML = '<span class="text-muted">Checking availability&hellip;</span>';

        fetch('/rooms/' + roomId + '/availability?check_in=' + checkin + '&check_out=' + checkout, {
            headers: { 'Accept': 'application/json' },
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.available) {
                    availabilityBox.innerHTML = '<span class="text-success"><i class="bi bi-check-circle-fill"></i> Room is available for these dates.</span>';
                    if (submitBtn) submitBtn.disabled = false;
                } else {
                    availabilityBox.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle-fill"></i> Room is not available for these dates.</span>';
                    if (submitBtn) submitBtn.disabled = true;
                }
            })
            .catch(function () {
                availabilityBox.innerHTML = '';
            });
    }

    [checkinInput, checkoutInput, roomSelect].forEach(function (el) {
        if (el) el.addEventListener('change', checkAvailability);
    });
});
