/**
 * GNYC Pickup Manager — Checkout Calendar & Slot UI
 * Enqueued via GNYC_Pickup_Fields::enqueue_scripts()
 * Depends on: jQuery, pickupData (localized)
 */
jQuery(function($) {

    var availableDates  = [];
    var currentYear     = 0;
    var currentMonth    = 0;
    var selectedDate    = null;
    var selectedTime    = null;
    var currentLocation = null;

    var dayNamesDesktop = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    var dayNamesMobile  = ['S','M','T','W','T','F','S'];
    var monthNames      = ['January','February','March','April','May','June',
                           'July','August','September','October','November','December'];

    function renderDayHeaders() {
        var $header  = $('#pickup-cal-days-header');
        var isMobile = window.innerWidth < 480;
        var names    = isMobile ? dayNamesMobile : dayNamesDesktop;
        $header.empty().css('display','grid');
        names.forEach(function(name) {
            $header.append(
                $('<div>').text(name).css({
                    'text-align'  : 'center',
                    'padding'     : '8px 4px',
                    'font-size'   : isMobile ? '11px' : '12px',
                    'font-weight' : 'bold',
                    'color'       : '#555',
                })
            );
        });
    }

    function toYmd(date) {
        var y = date.getFullYear();
        var m = String(date.getMonth() + 1).padStart(2, '0');
        var d = String(date.getDate()).padStart(2, '0');
        return y + m + d;
    }

    function renderCalendar() {
        var $grid  = $('#pickup-cal-grid');
        var $label = $('#pickup-cal-month-label');
        $grid.empty().css('display','grid');

        $label.text(monthNames[currentMonth] + ' ' + currentYear);

        var today    = new Date();
        var minYear  = today.getFullYear();
        var minMonth = today.getMonth();

        $('#pickup-cal-prev').css('opacity',
            ( currentYear === minYear && currentMonth === minMonth ) ? '0.3' : '1'
        );

        var firstDay    = new Date(currentYear, currentMonth, 1).getDay();
        var daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();

        for (var i = 0; i < firstDay; i++) {
            $grid.append($('<div>'));
        }

        for (var d = 1; d <= daysInMonth; d++) {
            var date    = new Date(currentYear, currentMonth, d);
            var ymd     = toYmd(date);
            var isAvail = availableDates.indexOf(ymd) !== -1;
            var isSel   = ymd === selectedDate;

            var $cell = $('<div>').text(d).css({
                'text-align'    : 'center',
                'padding'       : '10px 4px',
                'border-radius' : '6px',
                'font-size'     : '14px',
                'cursor'        : isAvail ? 'pointer' : 'default',
                'font-weight'   : isAvail ? 'bold' : 'normal',
                'background'    : isSel ? '#1a1a2e' : ( isAvail ? '#fff' : '#f0f0f0' ),
                'color'         : isSel ? '#fff' : ( isAvail ? '#1a1a2e' : '#bbb' ),
                'border'        : isAvail && ! isSel ? '1px solid #d0d0d0' : '1px solid transparent',
                'transition'    : 'background 0.15s, color 0.15s',
            });

            if (isAvail) {
                $cell.attr('data-date', ymd);
                $cell.on('mouseenter', function() {
                    if ( $(this).attr('data-date') !== selectedDate ) {
                        $(this).css({ 'background': '#e8eaf0', 'color': '#1a1a2e' });
                    }
                }).on('mouseleave', function() {
                    if ( $(this).attr('data-date') !== selectedDate ) {
                        $(this).css({ 'background': '#fff', 'color': '#1a1a2e' });
                    }
                }).on('click', function() {
                    selectedDate = $(this).attr('data-date');
                    $('#pickup_date_order').val(selectedDate);
                    renderCalendar();
                    loadTimeSlots(selectedDate);
                });
            }

            $grid.append($cell);
        }
    }

    function loadDates(locationId, dateStart, dateEnd) {
        currentLocation = locationId;
        availableDates  = [];
        selectedDate    = null;
        selectedTime    = null;

        $('#pickup_date_order').val('');
        $('#pickup_time_order').val('');
        $('#pickup-cal-loading').show();
        $('#pickup-cal-grid').hide();
        $('#pickup-cal-days-header').hide();
        $('#pickup-timeslots-wrapper').hide().find('#pickup-timeslots-grid').empty();

        $.post(pickupData.ajaxUrl, {
            action     : 'get_pickup_dates',
            nonce      : pickupData.nonce,
            location_id: locationId,
            date_start : dateStart || '',
            date_end   : dateEnd   || '',
        }, function(response) {
            $('#pickup-cal-loading').hide();
            $('#pickup-cal-grid').show();
            $('#pickup-cal-days-header').show();

            if ( response.success && response.data.dates.length ) {
                availableDates = response.data.dates.map(function(d) { return d.value; });

                var firstDate = availableDates[0];
                currentYear   = parseInt(firstDate.substring(0, 4));
                currentMonth  = parseInt(firstDate.substring(4, 6)) - 1;

                selectedDate = availableDates[0];
                $('#pickup_date_order').val(selectedDate);

                renderDayHeaders();
                renderCalendar();
                loadTimeSlots(selectedDate);
            } else {
                $('#pickup-cal-grid').html(
                    '<p style="padding:15px;color:#999;text-align:center;grid-column:span 7;">No available dates for this location.</p>'
                );
            }
        });
    }

    function loadTimeSlots(date) {
        selectedTime = null;
        $('#pickup_time_order').val('');

        var $wrapper = $('#pickup-timeslots-wrapper');
        var $grid    = $('#pickup-timeslots-grid');
        var $loading = $('#pickup-timeslots-loading');

        $grid.empty();
        $loading.show();
        $wrapper.slideDown(200);

        $.post(pickupData.ajaxUrl, {
            action     : 'get_pickup_slots',
            nonce      : pickupData.nonce,
            location_id: currentLocation,
            date       : date,
        }, function(response) {
            $loading.hide();

            if ( response.success && response.data.slots.length ) {
                var $select = $('<select>')
                    .attr('id', 'pickup-timeslot-select')
                    .css({
                        'width'         : '100%',
                        'padding'       : '10px',
                        'font-size'     : '15px',
                        'border'        : '1px solid #d0d0d0',
                        'border-radius' : '6px',
                        'box-sizing'    : 'border-box',
                        'background'    : '#fff',
                        'color'         : '#1a1a2e',
                    });

                $select.append($('<option>').val('').text('— Select a time —'));

                response.data.slots.forEach(function(slot) {
                    var spotsMatch = slot.label.match(/\((\d+) spots? left\)/);
                    var spots      = spotsMatch ? parseInt(spotsMatch[1]) : 5;
                    var isLow      = spots <= 2;
                    var label      = isLow ? '⚠ ' + slot.label : slot.label;

                    $select.append(
                        $('<option>').val(slot.value).text(label)
                    );
                });

                $select.on('change', function() {
                    selectedTime = $(this).val();
                    $('#pickup_time_order').val(selectedTime);
                });

                $grid.append($select);

            } else {
                $grid.html('<p style="color:#999;font-size:13px;">No time slots available for this date.</p>');
            }
        });
    }

    // Month navigation
    $('#pickup-cal-prev').on('click', function() {
        var today = new Date();
        if ( currentYear === today.getFullYear() && currentMonth === today.getMonth() ) {
            return;
        }
        currentMonth--;
        if (currentMonth < 0) { currentMonth = 11; currentYear--; }
        renderCalendar();
    });

    $('#pickup-cal-next').on('click', function() {
        currentMonth++;
        if (currentMonth > 11) { currentMonth = 0; currentYear++; }
        renderCalendar();
    });

    // Location change
    $(document).on('change', '.pickup-location-select', function() {
        var $item      = $(this).closest('.pickup-item');
        var locationId = $(this).val();
        var dateStart  = $item.data('date-range-start') || '';
        var dateEnd    = $item.data('date-range-end') || '';

        if ( ! locationId ) {
            $('#pickup-calendar-wrapper').slideUp(200);
            $('#pickup-timeslots-wrapper').slideUp(200);
            return;
        }

        $('#pickup-calendar-wrapper').slideDown(200);
        loadDates(locationId, dateStart, dateEnd);
    });

    // Re-render day headers on resize
    $(window).on('resize', function() {
        if (availableDates.length) {
            renderDayHeaders();
        }
    });

    // Show/hide entire pickup section based on shipping method
    function isLocalPickupSelected() {
        var $radio = $('input[name="shipping_method[0]"]:checked');
        if ( $radio.length ) return $radio.val().indexOf('local_pickup') !== -1;
        var $hidden = $('input[name="shipping_method[0]"][type="hidden"]');
        if ( $hidden.length ) return $hidden.val().indexOf('local_pickup') !== -1;
        return false;
    }

    function injectAndShow() {
        var $inner = $('#pickup-selection-inner');

        if ( ! $inner.parent().is('#payment') && $('#payment').length ) {
            $inner.insertBefore('#payment');
        }

        if ( isLocalPickupSelected() ) {
            $inner.show();

            // Auto-select if only one location
            $('.pickup-location-select').each(function() {
                var $select  = $(this);
                var $options = $select.find('option[value!=""]');
                if ( $options.length === 1 && $select.val() === '' ) {
                    $select.val( $options.first().val() ).trigger('change');
                }
            });
        } else {
            $inner.hide();
        }
    }

    setTimeout( injectAndShow, 500 );
    $( document.body ).on( 'updated_checkout', function() { setTimeout( injectAndShow, 300 ); });
    $( document.body ).on( 'change', 'input[name="shipping_method[0]"]', injectAndShow );

});
