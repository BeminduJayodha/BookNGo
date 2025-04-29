<?php


// Create table
function student_registration_install() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'class_students';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id INT NOT NULL AUTO_INCREMENT,
        student_name VARCHAR(255) NOT NULL,
        student_email VARCHAR(255) NOT NULL,
        class_selected VARCHAR(255) NOT NULL,
        date_registered TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'student_registration_install');

// Uninstall hook: delete students table
function student_registration_uninstall() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'class_students';

    // Drop the table if it exists
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}
register_uninstall_hook(__FILE__, 'student_registration_uninstall');


// Admin menu
function student_registration_menu() {
    add_menu_page(
    'Student Registration',    // Page title
    'SMS',    // Menu label
    'edit_pages',              // Capability
    'student-registration',    // **Slug** (this must match in allowed list!)
    'student_registration_page', // Callback function
    'dashicons-welcome-learn-more' // Icon
);

}
add_action('admin_menu', 'student_registration_menu');


// Form page
function student_registration_page() {
    global $wpdb;

    // Get unique class descriptions from booking_calendar
    $classes = $wpdb->get_results("SELECT DISTINCT description FROM {$wpdb->prefix}booking_calendar");
    ?>

<div class="wrap"> 
    <h1>Student Registration</h1>
    <form method="post" action="">

        <!-- Personal Information Section Comes First -->
        <h2>Personal Information</h2>
        <table class="form-table">
            <tr>
                <th><label for="student_name">Student Name</label></th>
                <td><input name="student_name" type="text" id="student_name" class="regular-text" required></td>
            </tr>
            <tr>
                <th><label for="student_dob">Date of Birth</label></th>
                <td><input name="student_dob" type="date" id="student_dob" class="regular-text" required></td>
            </tr>
            <tr>
                <th><label for="student_address">Address</label></th>
                <td><textarea name="student_address" id="student_address" class="regular-text" required></textarea></td>
            </tr>
            <tr>
                <th><label for="student_phone">Phone Number</label></th>
                <td><input name="student_phone" type="tel" id="student_phone" class="regular-text" pattern="[0-9]{3}-[0-9]{3}-[0-9]{4}" placeholder="XXX-XXX-XXXX" required></td>
            </tr>
        </table>

        <!-- Class Information Section Comes After Personal Information -->
        <h2>Class Information</h2>

        <div id="class-blocks">
            <!-- First Class Block -->
            <div class="class-block" style="position: relative;"> <!-- Add relative positioning to the block -->
                <input type="text" name="class_description[]" class="class-input" placeholder="Type class name" required />
                <ul class="class-suggestions"></ul>
                <div class="class-info"></div>
                <hr>
            </div>
        </div>

        <?php submit_button('Register Student'); ?>
    </form>
</div>




    <style>
        .class-block { margin-bottom: 20px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; }
        .class-info { margin-top: 10px; }
       .class-suggestions {
    max-height: 200px; /* Set the maximum height of the suggestion box */
    overflow-y: auto;  /* Enable vertical scrolling */
    background-color: #fff; /* Set background color */
    position: absolute; /* Position the suggestion box below the input field */
    width: 100%; /* Ensure the suggestion box spans the width of the input field */
    z-index: 9999; /* Make sure the suggestion box appears on top */
   
}

.class-suggestion {
    padding: 8px;
    cursor: pointer;
}

.class-suggestion:hover {
    background-color: #f1f1f1; /* Highlight suggestion on hover */
}
               
    </style>

    <script>
jQuery(document).ready(function($) {
    // Handle typing in class input and fetch suggestions
    $(document).on('input', '.class-input', function() {
        var inputValue = $(this).val();
        var suggestionBox = $(this).closest('.class-block').find('.class-suggestions');
        
        if (inputValue.length >= 2) {
            suggestionBox.html('<li>Loading...</li>');

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'fetch_class_suggestions',
                    term: inputValue
                },
                success: function(response) {
                    if (response) {
                        suggestionBox.html(response);
                    } else {
                        suggestionBox.html('<li>No results found</li>');
                    }
                }
            });
        } else {
            suggestionBox.html('');
        }
    });

    // Select class from suggestions
// Select class from suggestions and load instructor info
$(document).on('click', '.class-suggestion', function() {
    var className = $(this).text();
    var classBlock = $(this).closest('.class-block');
    var inputField = classBlock.find('.class-input');
    var suggestionBox = classBlock.find('.class-suggestions');
    var infoBox = classBlock.find('.class-info');

    // Set selected value
    inputField.val(className);
    suggestionBox.html('');

    // Load class instructor info
    infoBox.html('<p>Loading class info...</p>');
    $.ajax({
        url: ajaxurl,
        method: 'POST',
        data: {
            action: 'fetch_booked_slots_single',
            description: className
        },
        success: function(response) {
            infoBox.html(response);
        }
    });
});
$(document).on('click', '.add-instructor-btn', function(e) {
    e.preventDefault();

    const data = JSON.parse($(this).attr('data-instructor'));
    const $tableBody = $('#instructor-table tbody');
    const className = $(this).closest('.class-block').find('.class-input').val(); // Get selected class name

    // Clean up the amount (remove $, commas, and convert to float)
    const cleanedAmount = parseFloat(data.amount.replace(/[\$,]/g, ''));

    // Check for duplicates
    const exists = $tableBody.find('tr').filter(function () {
        return $(this).data('name') === data.customer_name &&
               $(this).data('start') === data.start_date &&
               $(this).data('end') === data.end_date &&
               $(this).data('time') === data.start_time;
    }).length > 0;

    if (!exists) {
        // Add new row with class name in a new column, cleaned amount, and remove button
        const $row = $(`
            <tr data-name="${data.customer_name}" data-start="${data.start_date}" data-end="${data.end_date}" data-time="${data.start_time}" data-amount="${cleanedAmount}">
                <td>${data.customer_name}</td>
                <td>${data.start_date} ${data.start_time}</td>
                <td>${data.end_date} ${data.end_time}</td>
                <td>${className}</td> <!-- New column for class name -->
                <td>$${cleanedAmount.toFixed(2)}</td>
                <td><button class="remove-instructor-btn">x</button></td>
            </tr>
        `);
        $tableBody.append($row);

        updateTotalAmount();
    }
});

$(document).on('click', '.remove-instructor-btn', function() {
    // Find the row to remove
    const $row = $(this).closest('tr');

    // Get the amount from the data-amount attribute
    const amount = parseFloat($row.data('amount'));

    // Remove the row
    $row.remove();

    // Update the total amount
    updateTotalAmount();
});



// Function to update total amount
function updateTotalAmount() {
    let total = 0;
    $('#instructor-table tbody tr').each(function () {
        const amount = parseFloat($(this).data('amount'));
        if (!isNaN(amount)) {
            total += amount;
        }
    });

    // Update the total amount in the footer
    $('#total-row .total-amount').text('$' + total.toFixed(2));
}





});


    </script>

    <?php

    // Handle form submission
    if (!empty($_POST['student_name']) && !empty($_POST['class_description'])) {
        $student_name = sanitize_text_field($_POST['student_name']);
        $student_email = sanitize_email($_POST['student_email']);
        $descriptions = array_map('sanitize_text_field', $_POST['class_description']);

        foreach ($descriptions as $class) {
            $wpdb->insert(
                $wpdb->prefix . 'class_students',
                [
                    'student_name' => $student_name,
                    'student_email' => $student_email,
                    'class_selected' => $class,
                    'date_registered' => current_time('mysql')
                ]
            );
        }

        echo '<div class="notice notice-success is-dismissible"><p>Student Registered for Selected Classes Successfully!</p></div>';
    }
}
add_action('wp_ajax_fetch_booked_slots_single', function() {
    global $wpdb;
    $description = sanitize_text_field($_POST['description']);

    $slots = $wpdb->get_results($wpdb->prepare(
        "SELECT bc.id AS booking_id, bc.customer_name, bc.group_id, bc.start_date, bc.end_date, bc.start_time, bc.end_time, bi.amount
         FROM {$wpdb->prefix}booking_calendar bc
         LEFT JOIN {$wpdb->prefix}booking_invoices bi ON bc.id = bi.booking_id
         WHERE bc.description = %s",
        $description
    ));

    if ($slots) {
        echo '<ul>';

        $grouped = [];

        foreach ($slots as $slot) {
            $key = $slot->customer_name . '|' . $slot->group_id;

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'customer_name' => $slot->customer_name,
                    'group_id' => $slot->group_id,
                    'start_date' => $slot->start_date,
                    'end_date' => $slot->end_date,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'booking_ids' => [],
                    'amounts' => []
                ];
            }

            $grouped[$key]['booking_ids'][] = $slot->booking_id;
            $grouped[$key]['amounts'][] = floatval($slot->amount);
        }

        foreach ($grouped as $group) {
            $unique_amounts = array_unique($group['amounts']);
            $total_amount = array_sum($unique_amounts);

            $label = esc_html("Instructor: {$group['customer_name']}, From {$group['start_date']} {$group['start_time']} to {$group['end_date']} {$group['end_time']} - Amount: " . number_format($total_amount, 2));

            // Encode instructor data for JavaScript
            $data = esc_attr(json_encode([
                'customer_name' => $group['customer_name'],
                'start_date' => $group['start_date'],
                'end_date' => $group['end_date'],
                'start_time' => $group['start_time'],
                'end_time' => $group['end_time'],
                'amount' => number_format($total_amount, 2)
            ]));

            echo '<li>';
            echo "<button class='add-instructor-btn' data-instructor='$data'>+</button> $label";
            echo '</li>';
        }

        echo '</ul>';
        echo '<div><h3>Selected Instructors</h3>
    <table id="instructor-table" border="1" style="width:100%; margin-top:10px;">
    <thead>
        <tr>
            <th>Name</th>
            <th>From</th>
            <th>To</th>
            <th>Class</th> <!-- New column for class name -->
            <th>Amount</th>
        </tr>
    </thead>
    <tbody></tbody>
    <tfoot>
        <tr id="total-row">
            <td colspan="4" style="text-align: right;">Total Amount</td>
            <td class="total-amount">$0.00</td>
        </tr>
    </tfoot>
</table>

</div>
';
    } else {
        echo '<p>No bookings found for this class.</p>';
    }

    wp_die();
});



add_action('wp_ajax_fetch_class_suggestions', 'fetch_class_suggestions');
add_action('wp_ajax_nopriv_fetch_class_suggestions', 'fetch_class_suggestions');

function fetch_class_suggestions() {
    global $wpdb;
    $term = sanitize_text_field($_POST['term']);

    $classes = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT description FROM {$wpdb->prefix}booking_calendar WHERE description LIKE %s",
        '%' . $wpdb->esc_like($term) . '%'
    ));

    if ($classes) {
        foreach ($classes as $class) {
            echo '<li class="class-suggestion">' . esc_html($class->description) . '</li>';
        }
    }

    wp_die();
}



?>
