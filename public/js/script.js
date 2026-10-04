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
    var guestsSelect = document.querySelector('[name="guests_label"]');
    var estimateBox = document.getElementById('bookingEstimate');
    var roomsSelect = document.querySelector('[name="rooms_count"]');

    function roomsCount() {
        var n = roomsSelect ? parseInt(roomsSelect.value, 10) : 1;
        return isNaN(n) || n < 1 ? 1 : n;
    }

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
        var perPerson = option && option.getAttribute('data-per-person') === '1';
        var guests = guestsSelect ? parseInt(guestsSelect.value, 10) : NaN;

        // A room priced per person needs an exact guest count to estimate.
        if (perPerson && isNaN(guests)) { price = NaN; }

        if (checkin && checkout && !isNaN(price)) {
            var nights = Math.round((new Date(checkout) - new Date(checkin)) / 86400000);
            if (nights > 0) {
                var rooms = roomsCount();
                var total = nights * price * (perPerson ? guests : 1) * rooms;
                estimateBox.innerHTML =
                    '<strong>' + nights + '</strong> night' + (nights > 1 ? 's' : '') +
                    ' &times; NPR ' + price.toLocaleString() +
                    (perPerson ? ' &times; <strong>' + guests + '</strong> guests' : '') +
                    (rooms > 1 ? ' &times; <strong>' + rooms + '</strong> rooms' : '') +
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
    if (guestsSelect) guestsSelect.addEventListener('change', updateEstimate);
    if (roomsSelect) roomsSelect.addEventListener('change', updateEstimate);
    updateEstimate();

    /* =========================================================
       LIVE AVAILABILITY CHECK
    ========================================================= */
    var availabilityBox = document.getElementById('availabilityResult');
    var submitBtn = document.getElementById('bookingSubmitBtn');

    /* Prevent accidental double-submission on double-click/slow connections.
       The server remains authoritative regardless — this is UX only. */
    if (submitBtn) {
        var bookingForm = submitBtn.closest('form');
        if (bookingForm) {
            bookingForm.addEventListener('submit', function () {
                submitBtn.disabled = true;
                submitBtn.innerHTML = 'Submitting&hellip;';
            });
        }
    }

    function checkAvailability() {
        if (!roomSelect || !checkinInput || !checkoutInput || !availabilityBox) return;

        var roomId = roomSelect.value;
        var checkin = checkinInput.value;
        var checkout = checkoutInput.value;

        if (!roomId || !checkin || !checkout || checkout <= checkin) {
            availabilityBox.innerHTML = '';
            if (submitBtn) submitBtn.disabled = false;
            return;
        }

        availabilityBox.innerHTML = '<span class="text-muted">Checking availability&hellip;</span>';

        var guestsParam = guestsSelect && guestsSelect.value ? '&guests_label=' + encodeURIComponent(guestsSelect.value) : '';

        fetch('/rooms/' + roomId + '/availability?check_in=' + checkin + '&check_out=' + checkout + guestsParam, {
            headers: { 'Accept': 'application/json' },
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                var rooms = roomsCount();
                // The server's estimate includes weekend/season/holiday rates.
                if (data.estimate && estimateBox) {
                    estimateBox.innerHTML =
                        '<strong>' + data.estimate.nights + '</strong> night' + (data.estimate.nights > 1 ? 's' : '') +
                        (rooms > 1 ? ' &times; <strong>' + rooms + '</strong> rooms' : '') +
                        ' = <strong>NPR ' + Math.round(data.estimate.subtotal * rooms).toLocaleString() + '</strong> (estimated' +
                        (data.estimate.varies ? ', includes weekend/seasonal rates' : '') + ')';
                    estimateBox.style.display = 'block';
                }
                if (rooms > 1 && typeof data.free_rooms === 'number' && data.free_rooms < rooms) {
                    availabilityBox.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle-fill"></i> Only ' + data.free_rooms + ' room(s) of this type are available for these dates.</span>';
                    if (submitBtn) submitBtn.disabled = true;
                } else if (data.available) {
                    availabilityBox.innerHTML = '<span class="text-success"><i class="bi bi-check-circle-fill"></i> Room is available for these dates.</span>';
                    if (submitBtn) submitBtn.disabled = false;
                } else {
                    // The server says why (same rules as the booking itself): dates
                    // taken, past/too-far dates, stay too long, party too large…
                    availabilityBox.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle-fill"></i> <span class="availability-reason"></span></span>';
                    availabilityBox.querySelector('.availability-reason').textContent = data.reason || 'Room is not available for these dates.';
                    if (submitBtn) submitBtn.disabled = true;
                }
            })
            .catch(function () {
                // Network hiccups must never permanently block submission —
                // the server re-validates availability authoritatively anyway.
                availabilityBox.innerHTML = '';
                if (submitBtn) submitBtn.disabled = false;
            });
    }

    [checkinInput, checkoutInput, roomSelect, guestsSelect, roomsSelect].forEach(function (el) {
        if (el) el.addEventListener('change', checkAvailability);
    });

    /* =========================================================
       FORM LOADING STATE — <form data-loading-text="Sending…">
       Disables the submit button (prevents double submits, e.g.
       two SMS codes) and shows a spinner while the request runs.
    ========================================================= */
    document.querySelectorAll('form[data-loading-text]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (form.dataset.submitting) {
                e.preventDefault();
                return;
            }
            var btn = form.querySelector('[type="submit"]');
            if (!btn) return;
            form.dataset.submitting = '1';
            btn.dataset.originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.setAttribute('aria-busy', 'true');
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>';
            btn.appendChild(document.createTextNode(form.dataset.loadingText));
        });
    });

    // Restore those buttons if the page comes back from the back/forward cache.
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) return;
        document.querySelectorAll('form[data-submitting]').forEach(function (form) {
            var btn = form.querySelector('[type="submit"]');
            delete form.dataset.submitting;
            if (btn && btn.dataset.originalHtml !== undefined) {
                btn.innerHTML = btn.dataset.originalHtml;
                btn.disabled = false;
                btn.removeAttribute('aria-busy');
            }
        });
    });

    /* =========================================================
       COUNTDOWNS — <el data-countdown="SECONDS"
         data-countdown-template="Expires in {time}."
         data-countdown-done="Expired."
         data-countdown-enables="button-id">
       The optional button stays disabled until the countdown ends.
    ========================================================= */
    document.querySelectorAll('[data-countdown]').forEach(function (el) {
        var remaining = parseInt(el.dataset.countdown, 10) || 0;
        var button = el.dataset.countdownEnables ? document.getElementById(el.dataset.countdownEnables) : null;
        var endsAt = Date.now() + remaining * 1000;

        function format(seconds) {
            var m = Math.floor(seconds / 60);
            var s = seconds % 60;
            return m + ':' + (s < 10 ? '0' : '') + s;
        }

        function tick() {
            var left = Math.max(0, Math.round((endsAt - Date.now()) / 1000));
            if (left <= 0) {
                el.textContent = el.dataset.countdownDone || '';
                if (button) button.disabled = false;
                return;
            }
            el.textContent = (el.dataset.countdownTemplate || '{time}').replace('{time}', format(left));
            if (button) button.disabled = true;
            setTimeout(tick, 1000);
        }

        tick();
    });

    /* =========================================================
       OTP INPUT — digits only
    ========================================================= */
    document.querySelectorAll('.otp-input').forEach(function (input) {
        input.addEventListener('input', function () {
            var digits = input.value.replace(/\D/g, '').slice(0, 6);
            if (digits !== input.value) input.value = digits;
        });
    });
});
