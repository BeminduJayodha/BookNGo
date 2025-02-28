<?php  
/**
 * Plugin Name: Booking Calendar Plugin
 * Description: A plugin for admin to book time slots and display them with colors.
 * Version: 1.0
 * Author: makerspace
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Activation hook to create database table
function booking_calendar_install() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'booking_calendar';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id INT NOT NULL AUTO_INCREMENT,
        customer_name VARCHAR(255) NOT NULL,
        booking_date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        color VARCHAR(7) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'booking_calendar_install');

// Uninstall hook to remove database table
function booking_calendar_uninstall() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'booking_calendar';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}
register_uninstall_hook(__FILE__, 'booking_calendar_uninstall');

// Enqueue scripts and styles
function booking_calendar_enqueue_scripts() {
    wp_enqueue_style('booking-calendar-style', plugins_url('style.css', __FILE__));
    wp_enqueue_script('booking-calendar-js', plugins_url('calendar.js', __FILE__), array('jquery'), null, true);
}
add_action('admin_enqueue_scripts', 'booking_calendar_enqueue_scripts');

// Admin menu for booking calendar
function booking_calendar_menu() {
    add_menu_page('Booking Calendar', 'Booking Calendar', 'manage_options', 'booking-calendar', 'booking_calendar_page');
}
add_action('admin_menu', 'booking_calendar_menu');

// Handle the booking form submission via AJAX
function save_booking() {
    global $wpdb;

    // Get data from the AJAX request
    $customer_name = sanitize_text_field($_POST['customer_name']);
    $start_time = sanitize_text_field($_POST['start_time']);
    $end_time = sanitize_text_field($_POST['end_time']);
    $booking_date = sanitize_text_field($_POST['booking_date']);

    // Generate a random color for the booking
    $color = '#' . strtoupper(dechex(rand(0, 0xFFFFFF)));

    // Save the booking in the database
    $wpdb->insert(
        $wpdb->prefix . 'booking_calendar',
        array(
            'customer_name' => $customer_name,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'booking_date' => $booking_date,
            'color' => $color
        )
    );

    wp_send_json_success();
}
add_action('wp_ajax_save_booking', 'save_booking');

// Booking calendar page with a structured table layout and navigation
function booking_calendar_page() {
    global $wpdb;

    // Fetch all existing bookings from the database
    $bookings = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}booking_calendar");

    // Get view from the query parameters
    $view = isset($_GET['view']) ? $_GET['view'] : 'month'; // Default to 'month' view
    $current_month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
    $current_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

    echo '<div class="wrap">
            <h1>Booking Calendar</h1>
            <div class="calendar-navigation-box" style="background-color: #f0f0f0; padding: 15px; border-radius: 8px; border: 1px solid #ccc; box-shadow: 2px 2px 10px rgba(0,0,0,0); margin-bottom: 20px;">
                <div class="calendar-navigation-section" style="display: flex; justify-content: space-between; align-items: center;">
                    <div class="calendar-navigation" style="display: inline-block; text-align: left;">
                        <a href="?page=booking-calendar&month=' . ($current_month - 1) . '&year=' . $current_year . '" class="button">&#8249; Previous</a>
                        <a href="?page=booking-calendar&month=' . ($current_month + 1) . '&year=' . $current_year . '" class="button">Next &#8250;</a>
                    </div>
                    <span style="font-size: 18px; font-weight: bold; margin: 0 15px; text-align: center; flex-grow: 1;">' . date('F Y', mktime(0, 0, 0, $current_month, 1, $current_year)) . '</span>
                    <a href="?page=booking-calendar&view=month&month=' . $current_month . '&year=' . $current_year . '" class="button" style="margin-left: 10px;">Month</a>
                    <a href="?page=booking-calendar&view=week&month=' . $current_month . '&year=' . $current_year . '" class="button" style="margin-left: 10px;">Week</a>
                    <a href="?page=booking-calendar&view=day&month=' . $current_month . '&year=' . $current_year . '" class="button" style="margin-left: 10px;">Day</a>
                    <a href="?page=booking-calendar&view=year&month=' . $current_month . '&year=' . $current_year . '" class="button" style="margin-left: 10px;">Year</a>
                    
                </div>
            </div>';

    // Render the calendar based on the selected view
    echo '<div id="booking-calendar-container">';
    if ($view == 'month') {
        display_month_view($bookings, $current_month, $current_year);
    } elseif ($view == 'week') {
        display_week_view($bookings, $current_month, $current_year);
    } elseif ($view == 'day') {
        display_day_view($bookings, $current_month, $current_year);
    } elseif ($view == 'year') {
        display_year_view($bookings, $current_year);
    }
    echo '</div>';

    echo '</div>';
}

function display_month_view($bookings, $current_month, $current_year) {
    // Calculate the first day of the month and number of days
    $first_day_of_month = strtotime("{$current_year}-{$current_month}-01");
    $start_day = date('N', $first_day_of_month);  // 1 = Monday, 7 = Sunday
    $num_days = date('t', $first_day_of_month);   // Number of days in the month

    // Calculate number of previous month's days to display
    $previous_month_days = date('t', strtotime("{$current_year}-" . ($current_month - 1) . "-01"));
    $next_month_days = 1; // Start from 1 for next month days

    echo '<table class="booking-table" border="1" cellspacing="0" cellpadding="5" style="width:100%; text-align:center; border-collapse: collapse; table-layout: fixed;"> 
            <thead>
                <tr>
                    <th style="border: 1px solid #000;">Monday</th>
                    <th style="border: 1px solid #000;">Tuesday</th>
                    <th style="border: 1px solid #000;">Wednesday</th>
                    <th style="border: 1px solid #000;">Thursday</th>
                    <th style="border: 1px solid #000;">Friday</th>
                    <th style="border: 1px solid #000;">Saturday</th>
                    <th style="border: 1px solid #000;">Sunday</th>
                </tr>
            </thead>
            <tbody>';

    $current_day = 1;
    $previous_month_day = $previous_month_days - $start_day + 2; // Start from the day before the first of the month
    for ($row = 1; $row <= 5; $row++) { // Assuming 5 weeks per month
        echo '<tr>';
        for ($day = 1; $day <= 7; $day++) {
            $current_cell_date = sprintf('%04d-%02d-%02d', $current_year, $current_month, $current_day);
            $bookings_on_date = array_filter($bookings, function ($booking) use ($current_cell_date) {
                return $booking->booking_date == $current_cell_date;
            });

            $booked_time_str = '';
            if (!empty($bookings_on_date)) {
                foreach ($bookings_on_date as $booking) {
                    $booked_time_str .= '<div style="background-color:' . esc_attr($booking->color) . '; color: white; padding: 5px; margin: 2px 0; border-radius: 4px;">';
                    $booked_time_str .= esc_html($booking->customer_name) . '<br>' . esc_html($booking->start_time . ' - ' . $booking->end_time);
                    $booked_time_str .= '</div>';
                }
            }

            // If the current day is within the number of days in the month, display it
            if (($row == 1 && $day >= $start_day) || ($row > 1 && $current_day <= $num_days)) {
                echo '<td class="booking-slot" data-day="' . $day . '" data-date="' . $current_cell_date . '" 
                      style="border: 1px solid #000; height: 100px; vertical-align: top; width: 14.28%; 
                      text-align: right; padding: 5px;" onclick="showBookingModal(\'' . $current_cell_date . '\')">' . $current_day . $booked_time_str . '</td>';
                $current_day++;
            } else {
                // Handle previous month's dates
                if ($current_day <= 1 && $day < $start_day) {
                    echo '<td class="booking-slot" data-day="' . $day . '" style="border: 1px solid #000; height: 100px; 
                          vertical-align: top; width: 14.28%; text-align: right; padding: 5px; background-color: #f0f0f0; color: #aaa;">
                          ' . $previous_month_day . '</td>';
                    $previous_month_day++;
                }
                // Handle next month's dates
                elseif ($current_day > $num_days) {
                    echo '<td class="booking-slot" data-day="' . $day . '" style="border: 1px solid #000; height: 100px; 
                          vertical-align: top; width: 14.28%; text-align: right; padding: 5px; background-color: #f0f0f0; color: #aaa;">
                          ' . $next_month_days . '</td>';
                    $next_month_days++;
                } else {
                    echo '<td class="booking-slot" data-day="' . $day . '" style="border: 1px solid #000; height: 100px; 
                          vertical-align: top; width: 14.28%;"></td>';
                }
            }
        }
        echo '</tr>';
        // Stop once we've reached the last day of the month
        if ($current_day > $num_days) {
            break;
        }
    }

    echo '    </tbody>
            </table>';

    // Add booking modal HTML
    echo '<div id="bookingModal" class="modal" style="display:none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); z-index: 9999;">
            <div class="modal-content" style="background: #fff; width: 400px; margin: 100px auto; padding: 20px; border-radius: 8px;">
                <h2>Book Time Slot</h2>
                <form id="bookingForm">
                    <input type="hidden" name="booking_date" id="bookingDate">
                    <label for="customer_name">Customer Name:</label>
                    <input type="text" name="customer_name" id="customer_name" required><br><br>
                    <label for="start_time">Start Time:</label>
                    <input type="time" name="start_time" id="start_time" required min="08:00" max="19:00"><br><br>
                    <label for="end_time">End Time:</label>
                    <input type="time" name="end_time" id="end_time" required min="08:00" max="19:00"><br><br>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <input type="submit" value="Save Booking" style="background-color: #21759b; color: white; padding: 10px 20px; border: none; cursor: pointer; border-radius: 5px;">
                        <button type="button" onclick="closeBookingModal()" style="background-color: #21759b; color: white; padding: 10px 20px; border: none; cursor: pointer; border-radius: 5px;">Close</button>
                    </div>
                </form>
            </div>
        </div>';
}
// JavaScript for booking modal handling
function booking_calendar_modal_js() {
    ?>
    <script type="text/javascript">
        function showBookingModal(date) {
            document.getElementById('bookingDate').value = date;
            document.getElementById('bookingModal').style.display = 'block';
        }

        function closeBookingModal() {
            document.getElementById('bookingModal').style.display = 'none';
        }

        jQuery(document).ready(function($) {
            $('#bookingForm').submit(function(e) {
                e.preventDefault();

                var formData = $(this).serialize();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData + '&action=save_booking',
                    success: function(response) {
                        if (response.success) {
                            alert('Booking Saved');
                            closeBookingModal();
                            location.reload();
                        } else {
                            alert('Error saving booking');
                        }
                    }
                });
            });
        });
    </script>
    <?php
}
add_action('admin_footer', 'booking_calendar_modal_js');




function display_week_view($bookings, $current_week_start_date, $current_year) {
    // Logic for week view
    // Calculate the week dates (Sunday to Saturday)
    $week_dates = [];
    for ($i = 0; $i < 7; $i++) {
        $week_dates[] = date('Y-m-d', strtotime("+$i day", strtotime($current_week_start_date)));
    }

    // Create a table for the weekly view with time slots (from 8 AM to 7 PM)
    echo '<table class="booking-table" border="1" cellspacing="0" cellpadding="5" style="width:100%; text-align:center; border-collapse: collapse; table-layout: fixed;">';
    echo '<thead>
            <tr>
                <th style="border: 1px solid #000;">Time</th>';

    // Add columns for each day of the week (Sunday to Saturday)
    foreach ($week_dates as $date) {
        echo '<th style="border: 1px solid #000;">' . date('D, d/m', strtotime($date)) . '</th>';
    }

    echo '</tr>
        </thead>
        <tbody>';

    // Loop through each time slot from 8 AM to 7 PM
    for ($hour = 8; $hour <= 19; $hour++) {
        $hour_str = str_pad($hour, 2, '0', STR_PAD_LEFT) . ":00";

        echo '<tr>';
        echo '<td style="border: 1px solid #000;">' . $hour_str . '</td>';

        // Loop through each day of the week and check if there are any bookings for the time slot
        foreach ($week_dates as $date) {
            // Filter bookings for the specific date and time
            $booked_time_str = '';
            $bookings_on_date_and_time = array_filter($bookings, function ($booking) use ($date, $hour) {
                // Check if the booking is within the time range
                return $booking->booking_date == $date && $hour >= date('H', strtotime($booking->start_time)) && $hour < date('H', strtotime($booking->end_time));
            });

            // Display booked slots
            if (!empty($bookings_on_date_and_time)) {
                foreach ($bookings_on_date_and_time as $booking) {
                    $booked_time_str .= '<div style="background-color:' . esc_attr($booking->color) . '; color: white; padding: 5px; margin: 2px 0; border-radius: 4px;">';
                    $booked_time_str .= esc_html($booking->customer_name) . '<br>' . esc_html($booking->start_time . ' - ' . $booking->end_time);
                    $booked_time_str .= '</div>';
                }
            }

            // Add the time slot column with the booking highlight
            echo '<td class="booking-slot" style="border: 1px solid #000; height: 100px; vertical-align: top; text-align: left; padding: 5px; background-color: ' . ($booked_time_str ? '#f8d7da' : 'transparent') . ';" onclick="showBookingModal(\'' . $date . ' ' . $hour_str . '\')">' . $booked_time_str . '</td>';
        }

        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';

    // Booking modal
    echo '<div id="bookingModal" class="modal" style="display:none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); z-index: 9999;">
            <div class="modal-content" style="background: #fff; width: 400px; margin: 100px auto; padding: 20px; border-radius: 8px;">
                <h2>Book Time Slot</h2>
                <form id="bookingForm">
                    <input type="hidden" name="booking_date" id="bookingDate">
                    <label for="customer_name">Customer Name:</label>
                    <input type="text" name="customer_name" id="customer_name" required><br><br>
                    <label for="start_time">Start Time:</label>
                    <input type="time" name="start_time" id="start_time" required min="08:00" max="19:00"><br><br>
                    <label for="end_time">End Time:</label>
                    <input type="time" name="end_time" id="end_time" required min="08:00" max="19:00"><br><br>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <input type="submit" value="Save Booking" style="background-color: #21759b; color: white; padding: 10px 20px; border: none; cursor: pointer; border-radius: 5px;">
                        <button type="button" onclick="closeBookingModal()" style="background-color: #21759b; color: white; padding: 10px 20px; border: none; cursor: pointer; border-radius: 5px;">Close</button>
                    </div>
                </form>
            </div>
        </div>';
}

function display_day_view($bookings, $current_month, $current_year) {
    // Logic for day view
    echo "<h3>Day View</h3>";
    // Display the calendar for the day
}

function display_year_view($bookings, $current_year) {
    // Logic for year view
    echo "<h3>Year View</h3>";
    // Display the calendar for the year
}







