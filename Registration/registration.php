<?php


// Create table
function student_registration_install() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $class_table = $wpdb->prefix . 'class_students';
    $students_table = $wpdb->prefix . 'students';
    $payment_table= $wpdb->prefix . 'student_payments';

    $sql = "
    CREATE TABLE $students_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        student_id VARCHAR(20) NOT NULLL,
        name VARCHAR(255) NOT NULL,
        dob DATE,
        address TEXT,
        phone VARCHAR(20),
        email VARCHAR(255),
        date_registered DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;


    CREATE TABLE $$payment_table (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(50),
        class_name VARCHAR(255),
        paid_months INT,
        remaining_months INT,
        due_amount DECIMAL(10,2),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        payment_method VARCHAR(50)
    ) $charset_collate;
    
    CREATE TABLE $class_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        student_id VARCHAR(20) NOT NULL,
        class_selected VARCHAR(255) NOT NULL,
        instructor_name VARCHAR(255),
        start_datetime DATETIME,
        end_datetime DATETIME,
        amount DECIMAL(10,2),
        full_amount DECIMAL(10,2),
        date_registered TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY student_id (student_id)
    ) $charset_collate;
    ";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'student_registration_install');


function student_registration_uninstall() {
    global $wpdb;
    $class_table = $wpdb->prefix . 'class_students';
    $students_table = $wpdb->prefix . 'students';

    $wpdb->query("DROP TABLE IF EXISTS $class_table");
    $wpdb->query("DROP TABLE IF EXISTS $students_table");
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
<td><input name="student_phone" type="tel" id="student_phone" class="regular-text" placeholder="Enter phone number" required></td>

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
                <input type="hidden" name="instructor_data" id="instructor_data" value="" />
        <input type="hidden" name="selected_payment_option" id="selected_payment_option" value="full">
                
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
$('input[name="payment_option"][value="monthly"]').closest('label').hide();

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
// Add Instructor Button
$(document).on('click', '.add-instructor-btn', function(e) {  
    e.preventDefault();

    const data = JSON.parse($(this).attr('data-instructor'));
    const $tableBody = $('#instructor-table tbody');
    const className = $(this).closest('.class-block').find('.class-input').val();

    const cleanedAmount = parseFloat(data.amount.replace(/[\$,]/g, ''));

    // Check for duplicates
    const exists = $tableBody.find('tr').filter(function () {
        return $(this).data('name') === data.customer_name &&
               $(this).data('start') === data.start_date &&
               $(this).data('end') === data.end_date &&
               $(this).data('time') === data.start_time;
    }).length > 0;

    if (!exists) {
        const $row = $(`<tr data-name="${data.customer_name}" data-start="${data.start_date}" data-end="${data.end_date}" data-time="${data.start_time}" data-amount="${cleanedAmount}">
            <td>${data.customer_name}</td>
            <td>${data.start_date} ${data.start_time}</td>
            <td>${data.end_date} ${data.end_time}</td>
            <td>${className}</td>
            <td>$${cleanedAmount.toFixed(2)}</td>
            <td><button class="remove-instructor-btn">x</button></td>
        </tr>`);

        $tableBody.append($row);
        updateTotalAmount();
        checkPaymentOptionsVisibility(); // ✅ now this works correctly
    }
});

// Remove Instructor Button
$(document).on('click', '.remove-instructor-btn', function() {
    const $row = $(this).closest('tr');
    const amount = parseFloat($row.data('amount'));
    $row.remove();

    updateTotalAmount();
    checkPaymentOptionsVisibility(); // ✅ now this works correctly
});

function checkPaymentOptionsVisibility() {
    let allowMonthly = true;
    const $rows = $('#instructor-table tbody tr');

    $rows.each(function () {
        const startDateStr = $(this).data('start');
        const endDateStr = $(this).data('end');

        const startDate = new Date(startDateStr);
        const endDate = new Date(endDateStr);

        const startDay = startDate.getDate();
        const endDay = endDate.getDate();

        // Get last day of end month
        const endMonthLastDay = new Date(endDate.getFullYear(), endDate.getMonth() + 1, 0).getDate();

        const sameDayMatch = startDay === endDay;
        const endsAtMonthEnd = endDay === endMonthLastDay;
        const startsInFirstWeek = startDay >= 1 && startDay <= 7;

        if (!(startsInFirstWeek && (sameDayMatch || endsAtMonthEnd))) {
            allowMonthly = false;
            return false; // Exit loop early
        }
    });

    const $monthly = $('input[name="payment_option"][value="monthly"]').closest('label');
    const $full = $('input[name="payment_option"][value="full"]').closest('label');

    if (allowMonthly && $rows.length > 0) {
        $monthly.show();
    } else {
        $monthly.hide();
        $('input[name="payment_option"][value="full"]').prop('checked', true);
    }
}






function updateTotalAmount() { 
    let total = 0;
    let monthSet = new Set(); // Store unique months like "2025-05", "2025-06"

    $('#instructor-table tbody tr').each(function () {
        const amount = parseFloat($(this).data('amount'));
        const startDate = new Date($(this).data('start'));
        const endDate = new Date($(this).data('end'));

        if (!isNaN(amount)) {
            total += amount;

            let current = new Date(startDate.getFullYear(), startDate.getMonth(), 1);
            const end = new Date(endDate.getFullYear(), endDate.getMonth(), 1);

            const currentMonthKey = new Date().toISOString().slice(0, 7); // e.g., "2025-05"

while (current <= end) {
    const ym = `${current.getFullYear()}-${String(current.getMonth() + 1).padStart(2, '0')}`;

    // Only add months from current month onward
    if (ym >= currentMonthKey) {
        monthSet.add(ym);
    }

    current.setMonth(current.getMonth() + 1);
}

        }
    });

    const paymentOption = $('input[name="payment_option"]:checked').val();
    let payable = total;
    let monthNote = '';

    if (paymentOption === 'monthly' && monthSet.size > 0) {
        const monthCount = monthSet.size;
        const monthlyAmount = total / monthCount;

        // ✅ Set payable amount to one month's amount
        payable = monthlyAmount;

        // ✅ Get the first month in sorted order
        const months = Array.from(monthSet).sort();
        const firstMonth = months[0];
        const [year, month] = firstMonth.split('-');

        // ✅ Convert to readable format
        const readableMonth = new Date(year, month - 1).toLocaleString('default', { month: 'long', year: 'numeric' });

        monthNote = `<div style="font-size: 13px; color: #666;">First Month: ${readableMonth}</div>`;

        let breakdown = '<h3>Monthly Breakdown:</h3>';
        months.forEach(m => {
            const [y, mo] = m.split('-');
            const label = new Date(y, mo - 1).toLocaleString('default', { month: 'long', year: 'numeric' });
            breakdown += `<div>${label}: Rs.${monthlyAmount.toFixed(2)}</div>`;
        });

        $('#monthly-breakdown').html(breakdown);
    } else {
        $('#monthly-breakdown').html('');
    }

    $('#payable-amount').html(`Rs.${payable.toFixed(2)} ${monthNote}`);
    $('.total-amount').text(`Rs.${total.toFixed(2)}`);
}





// Recalculate payable amount when payment option changes
$(document).on('change', 'input[name="payment_option"]', function() {
    $('#selected_payment_option').val($(this).val()); // Update hidden input value
    updateTotalAmount();
});

$('form').on('submit', function(e) {

    $('#selected_payment_option').val($('input[name="payment_option"]:checked').val());
    let instructorData = [];

    $('#instructor-table tbody tr').each(function () {
        const row = $(this);
        instructorData.push({
            name: row.data('name'),
            from: row.data('start') + ' ' + row.data('time'),
            to: row.data('end') + ' ' + row.data('time'), // or different end time if available
            class_name: row.find('td:nth-child(4)').text(), // assuming 4th column is class
            amount: row.data('amount')
        });
    });

    $('#instructor_data').val(JSON.stringify(instructorData));
});
jQuery(document).ready(function($) {
    $('#student-success-modal').fadeIn();

    $('#close-success-modal').on('click', function() {
        $('#student-success-modal').fadeOut();
    });
});




});


    </script>

    <?php

if (!empty($_POST['student_name']) && !empty($_POST['class_description']) && isset($_POST['instructor_data'])) {
    global $wpdb;

    // Sanitize and fetch student data
    $student_name    = sanitize_text_field($_POST['student_name']);
    $student_dob     = sanitize_text_field($_POST['student_dob']);
    $student_address = sanitize_textarea_field($_POST['student_address']);
    $student_phone   = sanitize_text_field($_POST['student_phone']);

    // Insert student into wp_students
    $wpdb->insert(
        $wpdb->prefix . 'students',
        [
            'name'            => $student_name,
            'dob'             => $student_dob,
            'address'         => $student_address,
            'phone'           => $student_phone,
            'date_registered' => current_time('mysql'),
        ]
    );

    if ($wpdb->insert_id === 0) {
        echo '<div class="notice notice-error"><p>Failed to register student.</p></div>';
        return;
    }

    $student_auto_id = $wpdb->insert_id;

    // Decode instructor data
    $instructors = json_decode(stripslashes($_POST['instructor_data']), true);

    if (empty($instructors) || !is_array($instructors)) {
        echo '<div class="notice notice-error"><p>Invalid class selection data.</p></div>';
        return;
    }

    // Get the description_code_id from booking_calendar matching the first class_name
    $first_class_name = sanitize_text_field($instructors[0]['class_name']);
    $description_code_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT description_code_id FROM {$wpdb->prefix}booking_calendar WHERE LOWER(description) = LOWER(%s) LIMIT 1",
            $first_class_name
        )
    );

    $description_code_id = $description_code_id ?: '000';
    $custom_student_id = 'STU-' . strtoupper($description_code_id) . '-' . str_pad($student_auto_id, 3, '0', STR_PAD_LEFT);

    // Update the student record with the formatted student_id
    $wpdb->update(
        $wpdb->prefix . 'students',
        ['student_id' => $custom_student_id],
        ['id' => $student_auto_id]
    );

    // Prepare payment summary
    $payment_option = sanitize_text_field($_POST['selected_payment_option']);
    $js_payment_option = esc_js($payment_option);
    $payment_option_label = ($payment_option === 'monthly') ? 'Monthly Payment' : 'Full Payment';
    $payment_summary = [];

    foreach ($instructors as $entry) {
        $start = new DateTime($entry['from']);
        $end = new DateTime($entry['to']);
    
        

        $month_iterator = clone $start;
        $month_iterator->modify('first day of this month');
        $end_month = clone $end;
        $end_month->modify('first day of this month');

        $months = [];
        while ($month_iterator <= $end_month) {
            $current_month = (new DateTime())->format('Y-m');
$month_value = $month_iterator->format('Y-m');

if ($month_value >= $current_month) {
    $months[] = $month_value;
}

            $month_iterator->modify('+1 month');
        }

        $month_count = count($months);
        $original_amount = floatval($entry['amount']);
        $amount = $original_amount;

        if ($payment_option === 'monthly' && $month_count > 0) {
            $amount = round($original_amount / $month_count, 2);
        }

        // Calculate paid and due
        $paid_months = 0; // No months paid
$remaining_months = $month_count;
$due_amount = $original_amount; // Full amount remains due


        // Format months to readable names
        $month_names = array_map(function($m) {
            return date('F Y', strtotime($m . '-01'));
        }, $months);
        
        // Separate paid and remaining month names
        // Format months to readable names
        $month_names = array_map(function($m) {
            return date('F Y', strtotime($m . '-01'));
        }, $months);
        
        // Separate paid and remaining month names
        $paid_month_names = array_slice($month_names, 0, $paid_months);
        $remaining_month_names = array_slice($month_names, $paid_months);
        
        $payment_summary[] = [
            'class' => $entry['class_name'],
            'paid_months' => $paid_months,
            'paid_month_names' => $paid_month_names,
            'remaining_months' => $remaining_months,
            'remaining_month_names' => $remaining_month_names,
            'due_amount' => number_format($due_amount, 2)
        ];



        // Insert class-student record
        $wpdb->insert(
            $wpdb->prefix . 'class_students',
            [
                'student_id'      => $custom_student_id,
                'class_selected'  => sanitize_text_field($entry['class_name']),
                'instructor_name' => sanitize_text_field($entry['name']),
                'start_datetime'  => sanitize_text_field($entry['from']),
                'end_datetime'    => sanitize_text_field($entry['to']),
                'amount'          => $amount,
                'full_amount'     => $original_amount,
                'date_registered' => current_time('mysql'),
            ]
        );
$wpdb->insert(
    $wpdb->prefix . 'student_payments',
    [
        'student_id'       => $custom_student_id,
        'class_name'       => sanitize_text_field($entry['class_name']),
        'paid_months'      => implode(', ', $paid_month_names),
        'remaining_months' => implode(', ', $remaining_month_names),
        'due_amount'       => $due_amount
    ]
);


    }

    // Prepare modal payment summary HTML
    $payment_html = '<ul style="text-align:left;">';
    foreach ($payment_summary as $summary) {
        $payment_html .= '<li><strong>' . esc_html($summary['class']) . '</strong>:<br>';
        $payment_html .= 'Paid Months (' . $summary['paid_months'] . '): ' . implode(', ', $summary['paid_month_names']) . '<br>';
        $payment_html .= 'Remaining Months (' . $summary['remaining_months'] . '): ' . implode(', ', $summary['remaining_month_names']) . '<br>';
        $payment_html .= 'Due Amount: Rs. ' . $summary['due_amount'] . '</li>';

    }
    $payment_html .= '</ul>';

    // Show success modal
// Show success modal
echo '<div id="student-success-modal" style="display: none;">
    <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999;">
        <div style="background: #fff; width: 400px; margin: 100px auto; padding: 30px; text-align: center; border-radius: 8px; position: relative;">
            <h2 style="margin-top: 0;">Student registered successfully!</h2>
            <p><strong>Student ID:</strong> ' . esc_html($custom_student_id) . '</p>
            <p><strong>Payment Option:</strong> ' . esc_html($payment_option_label) . '</p>
            <p><strong>Payment Summary:</strong></p>
            <div id="payment-summary">' . $payment_html . '</div> <!-- This will be updated dynamically -->
            <div style="margin-top: 20px;">
                <a href="/wp-admin/admin.php?page=student_payment_page" class="button button-primary">Go to Payment</a>
                <button id="update-payment-button" class="button button-secondary">Update Payment</button>
                <button id="close-success-modal" class="button">Close</button>
            </div>
        </div>
    </div>
</div>';


// Fetch remaining_months from database
global $wpdb;
$table_name = $wpdb->prefix . 'student_payments';

$payment_data = $wpdb->get_row(
    $wpdb->prepare("SELECT remaining_months FROM $table_name WHERE student_id = %s", $custom_student_id)
);

$checkbox_html = '';

if ($payment_data && !empty($payment_data->remaining_months)) {
    $months = array_map('trim', explode(',', $payment_data->remaining_months));
    foreach ($months as $month) {
        $checkbox_html .= '<label style="display: block; margin-bottom: 8px;">
            <input type="checkbox" name="payment_months[]" value="' . esc_attr($month) . '"> ' . esc_html($month) . '
        </label>';
    }
} else {
    $checkbox_html = '<p>No remaining months found.</p>';
}





global $wpdb;

$student_data = $wpdb->get_row( $wpdb->prepare(
    "SELECT class_selected, instructor_name FROM wp_class_students WHERE student_id = %d LIMIT 1",
    $custom_student_id
) );

$class_name = $student_data ? $student_data->class_selected : 'No class assigned';
$instructor_name = $student_data ? $student_data->instructor_name : 'No instructor assigned';

// Modal for updating payment with checkboxes
echo '<div id="update-payment-modal" style="display: none;">
    <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999;">
        <div style="background: #fff; width: 400px; margin: 100px auto; padding: 30px; text-align: center; border-radius: 8px; position: relative;">
            <h2>Update Payment</h2>
            <form id="update-payment-form">
<div style="margin-bottom: 15px; text-align: center;">
    <div style="display: inline-block; text-align: left;">
        <h4 style="margin-bottom: 10px;">Pay For:</h4>
        ' . $checkbox_html . '
    </div>
</div>
<div style="margin-bottom: 15px; text-align: center;">
    <div style="display: inline-block; text-align: left;">
        <h4 style="margin-bottom: 10px;">Select Payment Method</h4>
        <label style="display: block; margin: 5px 0;">
            <input type="radio" name="payment_method" value="cash" checked required> Cash
        </label>
        <label style="display: block; margin: 5px 0;">
            <input type="radio" name="payment_method" value="bank transfer"> Bank Transfer
        </label>
        <label style="display: block; margin: 5px 0;">
            <input type="radio" name="payment_method" value="card"> Card
        </label>
    </div>
</div>
<div>
 <p><strong>Student ID:</strong> ' . esc_html($custom_student_id) . '</p>
                     <p><strong>Class Name:</strong> ' . esc_html($class_name) . '</p>
                    <p><strong>Instructor Name:</strong> ' . esc_html($instructor_name) . '</p>
<p id="pay-amount"><strong>Pay Amount:</strong> Rs. ' . esc_html($due_amount) . '</p>

</div>

                <div style="margin-top: 15px;">
                    <button type="submit" class="button button-primary">Submit Payment</button>
                    <button type="button" id="close-update-payment-modal" class="button">Close</button>
                </div>
            </form>
        </div>
    </div>
</div>';

// JavaScript for handling the modal and AJAX
echo "<script>
    jQuery(document).ready(function($) {
        var paymentOption = '" . $js_payment_option . "';
        var totalDueAmount = parseFloat('" . $due_amount . "');

        function calculateAndDisplayMonthlyAmount() {
            var totalCheckboxes = $('input[name=\"payment_months[]\"]').length;

            if (paymentOption === 'monthly' && totalCheckboxes > 0) {
                var monthlyAmount = (totalDueAmount / totalCheckboxes).toFixed(2);
                $('#pay-amount').text('Pay Amount: Rs. ' + monthlyAmount);
            } else {
                $('#pay-amount').text('Pay Amount: Rs. ' + totalDueAmount.toFixed(2));
            }
        }

        // Show the success modal
        $('#student-success-modal').fadeIn();

        // Close success modal
        $('#close-success-modal').on('click', function() {
            $('#student-success-modal').fadeOut();
        });

        // Open update payment modal
        $('#update-payment-button').on('click', function() {
            $('#update-payment-modal').fadeIn();

            if (paymentOption === 'full') {
                $('input[name=\"payment_months[]\"]').each(function() {
                    $(this).prop('checked', true).prop('disabled', true);
                });
            } else if (paymentOption === 'monthly') {
                var checkboxes = $('input[name=\"payment_months[]\"]');
                checkboxes.prop('checked', false).prop('disabled', true);
                checkboxes.first().prop('checked', true).prop('disabled', false);
            } else {
                $('input[name=\"payment_months[]\"]').prop('disabled', false);
            }

            calculateAndDisplayMonthlyAmount(); // Update pay amount
        });

        // Close update payment modal
        $('#close-update-payment-modal').on('click', function() {
            $('#update-payment-modal').fadeOut();
        });

        // Handle the form submission via AJAX
        $('#update-payment-form').on('submit', function(e) {
            e.preventDefault();

            var selectedMonths = [];
            $('input[name=\"payment_months[]\"]:checked').each(function() {
                selectedMonths.push($(this).val());
            });

            $.ajax({
                url: '" . admin_url('admin-ajax.php') . "',
                type: 'POST',
                data: {
                    action: 'update_student_payment_months',
                    student_id: '" . esc_js($custom_student_id) . "',
                    payment_months: selectedMonths,
                    payment_method: $('input[name=\"payment_method\"]:checked').val()
                },
                success: function(response) {
                    if (response.success) {
                        alert('Payment updated successfully!');

                        $.ajax({
                            url: '" . admin_url('admin-ajax.php') . "',
                            type: 'POST',
                            data: {
                                action: 'fetch_updated_payment_summary',
                                student_id: '" . esc_js($custom_student_id) . "'
                            },
                            success: function(response) {
                                if (response.success) {
                                    $('#payment-summary').html(response.data.payment_summary);
                                    $('#update-payment-button').hide();
                                } else {
                                    alert('Failed to fetch updated payment details.');
                                }
                            }
                        });

                        $('#update-payment-modal').fadeOut();
                    } else {
                        alert('There was an error updating the payment.');
                    }
                }
            });
        });
    });
</script>";




}

}
// Handle AJAX request to update student payment months
function update_student_payment_months() {
    if (isset($_POST['student_id']) && isset($_POST['payment_months'])) {
        global $wpdb;
        $student_id = sanitize_text_field($_POST['student_id']);
        $new_paid_months = array_map('sanitize_text_field', $_POST['payment_months']);
        $payment_method = isset($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : '';

        $payment_table = $wpdb->prefix . 'student_payments';
        $student_table = $wpdb->prefix . 'class_students';

        // Step 1: Get full_amount from class_students table
        $student_data = $wpdb->get_row(
            $wpdb->prepare("SELECT full_amount FROM $student_table WHERE student_id = %s", $student_id)
        );

        if (!$student_data) {
            wp_send_json_error(['message' => 'Student not found']);
        }

        $full_amount = floatval($student_data->full_amount);

        // Step 2: Get existing paid and remaining months
        $payment_data = $wpdb->get_row(
            $wpdb->prepare("SELECT paid_months, remaining_months FROM $payment_table WHERE student_id = %s", $student_id)
        );

        if ($payment_data) {
            $existing_paid_months = !empty($payment_data->paid_months) ? array_map('trim', explode(',', $payment_data->paid_months)) : [];
            $existing_remaining_months = !empty($payment_data->remaining_months) ? array_map('trim', explode(',', $payment_data->remaining_months)) : [];

            // Step 3: Remove selected months from remaining
            $updated_remaining_months = array_diff($existing_remaining_months, $new_paid_months);

            // Step 4: Merge all months to get total_months
            $all_months = array_unique(array_merge($existing_paid_months, $existing_remaining_months));
            $total_months = count($all_months); // e.g., 3 months total

            // Step 5: Calculate paid_month_count
            $paid_month_count = count($new_paid_months); // e.g., 2 just paid now

            // Step 6: Calculate due amount
            $per_month_amount = $total_months > 0 ? ($full_amount / $total_months) : 0;
            $due_amount = $full_amount - ($per_month_amount * ($paid_month_count + count($existing_paid_months)));
            $last_paid_amount = $per_month_amount * count($new_paid_months);

            // Step 7: Merge new paid months with existing ones
            $updated_paid_months = array_unique(array_merge($existing_paid_months, $new_paid_months));

            // Step 8: Update DB
            $updated = $wpdb->update(
                $payment_table,
                array(
                    'paid_months' => implode(',', $updated_paid_months),
                    'remaining_months' => implode(',', $updated_remaining_months),
                    'due_amount' => round($due_amount, 2),
                    'payment_method' => $payment_method,
                    'last_paid_amount' => round($last_paid_amount, 2),
                    'last_paid_date' => current_time('mysql')
                ),
                array('student_id' => $student_id)
            );

            if ($updated !== false) {
                wp_send_json_success(); // Return success
            } else {
                wp_send_json_error(['message' => 'Failed to update DB']);
            }
        } else {
            wp_send_json_error(['message' => 'Payment data not found']);
        }
    }

    wp_die();
}


// Register the AJAX action for logged-in users
add_action('wp_ajax_update_student_payment_months', 'update_student_payment_months');
// Handle AJAX request to fetch updated payment summary
function fetch_updated_payment_summary() {
    if (isset($_POST['student_id'])) {
        global $wpdb;
        $student_id = sanitize_text_field($_POST['student_id']);

        // Fetch the updated payment details from the database
        $table_name = $wpdb->prefix . 'student_payments';
        $payment_data = $wpdb->get_row(
            $wpdb->prepare("SELECT paid_months, remaining_months, due_amount  FROM $table_name WHERE student_id = %s", $student_id)
        );

        if ($payment_data) {
            // Prepare the updated payment summary
            $paid_months = !empty($payment_data->paid_months) ? implode(', ', explode(',', $payment_data->paid_months)) : 'No months paid yet';
            $remaining_months = !empty($payment_data->remaining_months) ? implode(', ', explode(',', $payment_data->remaining_months)) : 'All months paid';
            $due_amount = isset($payment_data->due_amount) ? number_format((float)$payment_data->due_amount, 2) : '0.00';

            // Create the new payment summary HTML
            $payment_summary = '<strong>Paid Months:</strong> ' . esc_html($paid_months) . '<br>';
            $payment_summary .= '<strong>Remaining Months:</strong> ' . esc_html($remaining_months) . '<br>';
            $payment_summary .= '<strong>Due Amount:</strong> Rs. ' . esc_html($due_amount);

            wp_send_json_success(array('payment_summary' => $payment_summary));
        } else {
            wp_send_json_error();
        }
    }

    wp_die();
}

// Register the AJAX action for logged-in users
add_action('wp_ajax_fetch_updated_payment_summary', 'fetch_updated_payment_summary');




add_action('wp_ajax_fetch_booked_slots_single', function() { 
    global $wpdb;
    $description = sanitize_text_field($_POST['description']);

    // Get the current month start and end dates
    $current_month_start = date('Y-m-01'); // First day of the current month
    $current_month_end = date('Y-m-t'); // Last day of the current month

    $slots = $wpdb->get_results($wpdb->prepare(
        "SELECT bc.id AS booking_id, bc.customer_name, bc.group_id, bc.start_date, bc.end_date, bc.start_time, bc.end_time, bc.course_fee
         FROM {$wpdb->prefix}booking_calendar bc
         WHERE bc.description = %s",
        $description
    ));

    if ($slots) {
        echo '<ul>';

        $grouped = [];

        foreach ($slots as $slot) {
            // Check if the class falls within the current month
            $start_date = $slot->start_date;
            $end_date = $slot->end_date;

            // Skip classes that end in the previous month
            if (strtotime($end_date) < strtotime($current_month_start)) {
                continue;
            }

            // Group classes by customer and group_id
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
                    'course_fee' => floatval($slot->course_fee)
                ];
            }

            $grouped[$key]['booking_ids'][] = $slot->booking_id;
        }

        foreach ($grouped as $group) {
            $total_amount = $group['course_fee'];

            // Only display classes within the current month
            $label = esc_html("Instructor: {$group['customer_name']}, From {$group['start_date']} {$group['start_time']} to {$group['end_date']} {$group['end_time']} - Course Fee: " . number_format($total_amount, 2));

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
                <th>Class</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody></tbody>
        <tfoot>
            <tr id="total-row">
                <td colspan="4" style="text-align: right;">Total Amount</td>
                <td class="total-amount">Rs.0.00</td>
            </tr>
        </tfoot>
        </table>
        <h3>Payment Option</h3>
        <label>
            <input type="radio" name="payment_option" value="full" checked> Full Payment
        </label>
        <label style="margin-left: 20px;">
            <input type="radio" name="payment_option" value="monthly"> Monthly Payment
        </label>

        <p style="margin-top: 10px;">
            <strong>Payable Amount: </strong><span id="payable-amount">Rs.0.00</span>
        </p>
        </div>';
    } else {
        echo 'No booked slots found.';
    }

    wp_die();
});




add_action('wp_ajax_fetch_class_suggestions', 'fetch_class_suggestions');
add_action('wp_ajax_nopriv_fetch_class_suggestions', 'fetch_class_suggestions');



function register_student_payment_submenu() {
    add_submenu_page(
        'student-registration',         // Parent slug
        'Student Payment',              // Page title
        'Student Payment',              // Menu label
        'edit_pages',                   // Capability
        'student_payment_page',         // Slug
        'student_payment_page'          // Callback function
    );
}
add_action('admin_menu', 'register_student_payment_submenu');


function student_payment_page() {
    global $wpdb;

    $students = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}students ORDER BY id DESC");

    if (isset($_POST['mark_months_paid']) && isset($_POST['paid_months'], $_POST['student_id'], $_POST['class_name'])) {
        $student_id = sanitize_text_field($_POST['student_id']);
        $class_name = sanitize_text_field($_POST['class_name']);
        $marked_paid = array_map('sanitize_text_field', $_POST['paid_months']);

        $payment = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}student_payments WHERE student_id = %s AND class_name = %s", $student_id, $class_name)
        );

        if ($payment) {
            $old_paid = array_map('trim', explode(',', $payment->paid_months));
            $old_remaining = array_map('trim', explode(',', $payment->remaining_months));

            $new_paid = array_merge($old_paid, $marked_paid);
            $new_paid = array_unique(array_filter($new_paid));

            $new_remaining = array_diff($old_remaining, $marked_paid);

            $monthly_amount = ($payment->due_amount > 0 && count($old_remaining) > 0) ? ($payment->due_amount / count($old_remaining)) : 0;
            $new_due_amount = round($monthly_amount * count($new_remaining), 2);

            $wpdb->update(
                $wpdb->prefix . 'student_payments',
                [
                    'paid_months'      => implode(', ', $new_paid),
                    'remaining_months' => implode(', ', $new_remaining),
                    'due_amount'       => $new_due_amount
                ],
                [
                    'student_id' => $student_id,
                    'class_name' => $class_name
                ]
            );

            echo '<div class="updated notice"><p>Payment status updated successfully.</p></div>';
        }
    }

    echo '<style>
    table.student-payment-table {
        width: 100%;
        border-collapse: collapse;
    }
    table.student-payment-table th {
        background-color: #ddd;
        font-weight: bold;
        text-align: center;
        padding: 10px;
        border: 1px solid #ccc;
    }
    table.student-payment-table td {
        padding: 10px;
        border: 1px solid #eee;
        text-align: center;
    }
    table.student-payment-table tbody tr:nth-child(odd) {
        background-color: #ffffff;
    }
    table.student-payment-table tbody tr:nth-child(even) {
        background-color: #f7f7f7;
    }

    .payment-button {
        padding: 5px 10px;
        border-radius: 5px;
        font-size: 14px;
        cursor: pointer;
        display: inline-block;
        width: 100px;
        text-align: center;
        margin: 2px;
    }

    .paid-btn {
        background-color: green;
        color: white;
    }

    .unpaid-btn {
        background-color: orange;
        color: white;
    }
</style>';

    echo '<div class="wrap">';
    echo '<h1>Student Payment Status</h1>';
    echo '<table class="student-payment-table">';
    echo '<thead><tr>
            <th>Student ID</th>
            <th>Name</th>
            <th>Phone</th>
            <th>Class</th>
            <th>Paid | Unpaid</th>
            <th>Due Amount (Rs)</th>
        </tr></thead><tbody>';

    foreach ($students as $student) {
        $student_id = $student->student_id;
        $name = $student->name;
        $phone = $student->phone;

        $payments = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}student_payments WHERE student_id = %s", $student_id)
        );

        if ($payments) {
            foreach ($payments as $payment) {
                $paid_months = !empty($payment->paid_months) ? $payment->paid_months : 'None';
                $remaining_months = !empty($payment->remaining_months) ? $payment->remaining_months : 'None';

                echo '<tr>
                    <td>' . esc_html($student_id) . '</td>
                    <td>' . esc_html($name) . '</td>
                    <td>' . esc_html($phone) . '</td>
                    <td>' . esc_html($payment->class_name) . '</td>
                    <td>';
$current_month = date('F Y'); // e.g., "May 2025"

                // Paid months
if ($paid_months !== 'None') {
    $paid_months_array = array_map('trim', explode(',', $paid_months));
    foreach ($paid_months_array as $month) {
        $is_current = ($month === $current_month);
        $button_class = 'paid-btn';
        $button_style = $is_current ? '' : 'background-color: #ccc; color: #666; opacity: 0.5; pointer-events: none;';
        echo '<div class="payment-month" data-student="' . esc_attr($student_id) . '" data-class="' . esc_attr($payment->class_name) . '" data-month="' . esc_attr($month) . '" data-status="paid">
                ' . esc_html($month) . ' 
                <span class="payment-button ' . $button_class . '" style="' . $button_style . '">Paid</span>
            </div>';
    }
}

// Unpaid months
if ($remaining_months !== 'None') {
    $remaining_months_array = array_map('trim', explode(',', $remaining_months));
    $per_month_due = (count($remaining_months_array) > 0) ? ($payment->due_amount / count($remaining_months_array)) : 0;

    foreach ($remaining_months_array as $month) {
        $is_current = ($month === $current_month);
        $button_class = 'unpaid-btn';
        $button_style = $is_current ? '' : 'background-color: #ccc; color: #666; opacity: 0.5; pointer-events: none;';
        echo '<div class="payment-month" 
                    data-student="' . esc_attr($student_id) . '" 
                    data-class="' . esc_attr($payment->class_name) . '" 
                    data-month="' . esc_attr($month) . '" 
                    data-status="unpaid"
                    data-due-amount="' . esc_attr(round($per_month_due, 2)) . '">
                ' . esc_html($month) . ' 
                <span class="payment-button ' . $button_class . '" style="' . $button_style . '">Unpaid</span>
            </div>';
    }
}




                echo '</td>
                    <td>' . number_format($payment->due_amount, 2) . '</td>
                </tr>';

                if (!empty($payment->remaining_months)) {
                    $remaining_months_array = array_map('trim', explode(',', $payment->remaining_months));
                    echo '<tr class="payment-form-row" style="display:none;"><td colspan="6">
                        <form method="post">
                            <input type="hidden" name="student_id" value="' . esc_attr($student_id) . '">
                            <input type="hidden" name="class_name" value="' . esc_attr($payment->class_name) . '">
                            <label>Select months to mark as paid:</label><br><br>';

                    foreach ($remaining_months_array as $month) {
                        echo '<label><input type="checkbox" name="paid_months[]" value="' . esc_attr($month) . '"> ' . esc_html($month) . '</label><br><br>';
                    }

                    echo '<button type="submit" name="mark_months_paid" class="button button-primary">Update Payment</button>
                        </form>
                    </td></tr>';
                }
            }
        } else {
            echo '<tr>
                <td>' . esc_html($student_id) . '</td>
                <td>' . esc_html($name) . '</td>
                <td>' . esc_html($phone) . '</td>
                <td colspan="3">No payment data available</td>
            </tr>';
        }
    }

    echo '</tbody></table></div>';

echo '
<div id="unpaidModal" style="display:none; position:fixed; top:20%; left:50%; transform:translateX(-50%); background:white; padding:20px; border:1px solid #ccc; z-index:1000;">  
    <h2>Mark This Month as Paid</h2>
    <form id="ajax-payment-form">
        <input type="hidden" name="student_id" id="modal_student_id">
        <input type="hidden" name="class_name" id="modal_class_name">
        <input type="hidden" name="month" id="modal_month">

        <p id="modal_student_display"></p>
        <p id="modal_month_display"></p>
        <p id="modal_amount_display"></p>

<label for="modal_payment_method">Select Payment Method:</label><br>
<input type="radio" id="cash" name="payment_method" value="cash" checked required>
<label for="cash">Cash</label><br>

<input type="radio" id="banktransfer" name="payment_method" value="banktransfer">
<label for="banktransfer">Bank Transfer</label><br>

<input type="radio" id="card" name="payment_method" value="card">
<label for="card">Card</label><br>
<input type="hidden" name="pay_amount" id="modal_pay_amount">



        <button type="submit" class="button button-primary">Confirm Payment</button>
        <button type="button" id="closeModal" class="button">Cancel</button>
        <div id="payment-status-msg" style="margin-top:10px;"></div>
    </form>
</div>
<div id="modalOverlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.3); z-index:999;"></div>

<script>
jQuery(document).ready(function($) {
    $(".payment-month").on("click", ".unpaid-btn", function () {
        if ($(this).css("pointer-events") === "none") return;

        const parent = $(this).closest(".payment-month");
        const student_id = parent.data("student");
        const class_name = parent.data("class");
        const month = parent.data("month");
        const dueAmount = parseFloat(parent.data("due-amount")).toFixed(2);
        

        // Set modal values that are immediately available
        $("#modal_amount_display").text("Pay Amount: Rs " + dueAmount);
        $("#modal_pay_amount").val(dueAmount); // Set hidden input
        $("#modal_student_id").val(student_id);
        $("#modal_class_name").val(class_name);
        $("#modal_month").val(month);

        // Temporary display while fetching instructor
        $("#modal_student_display").html(
            "<strong>Student ID:</strong> " + student_id + "<br>" +
            "<strong>Class:</strong> " + class_name + "<br>" +
            "<strong>Instructor:</strong> Loading..."
        );

        // Month display
        $("#modal_month_display").text("You are marking \"" + month + "\" as paid.");
        $("#unpaidModal, #modalOverlay").fadeIn();

        // AJAX call to fetch instructor name
        $.ajax({
            url: ajaxurl,
            method: "POST",
            data: {
                action: "get_instructor_by_student",
                student_id: student_id
            },
            success: function(response) {
                if (response.success) {
                    $("#modal_student_display").html(
                        "<strong>Student ID:</strong> " + student_id + "<br>" +
                        "<strong>Class:</strong> " + class_name + "<br>" +
                        "<strong>Instructor:</strong> " + response.data.instructor_name
                    );
                } else {
                    $("#modal_student_display").append("<br><strong>Instructor:</strong> Not found");
                }
            },
            error: function() {
                $("#modal_student_display").append("<br><strong>Instructor:</strong> Error fetching");
            }
        });
    });

    $("#closeModal, #modalOverlay").on("click", function () {
        $("#unpaidModal, #modalOverlay").fadeOut();
    });

$("#ajax-payment-form").on("submit", function (e) {
    e.preventDefault();

    const student_id = $("#modal_student_id").val();
    const class_name = $("#modal_class_name").val();
    const month = $("#modal_month").val();
    const pay_amount = $("#modal_pay_amount").val();

    // Fetch the selected radio button value for payment method
    const payment_method = $("input[name=\"payment_method\"]:checked").val();  // Use double quotes inside the selector
  // Get selected radio button value

    if (!payment_method) {
        $("#payment-status-msg").text("Please select a payment method.").css("color", "red");
        return;
    }

    $.ajax({
        url: ajaxurl,
        method: "POST",
        data: {
            action: "update_month_payment_status",
            student_id,
            class_name,
            month,
            new_status: "paid",
            payment_method, // Send the selected payment method to the server
            pay_amount 
        },
        success: function(response) {
            if (response.success) {
                $("#payment-status-msg").text("Payment updated successfully!").css("color", "green");
                setTimeout(() => {
                    $("#unpaidModal, #modalOverlay").fadeOut();
                    location.reload(); // refresh to reflect UI change
                }, 800);
            } else {
                $("#payment-status-msg").text("Error: " + response.data).css("color", "red");
            }
        }
    });
});

});
</script>

';



}
add_action('wp_ajax_get_instructor_by_student', 'get_instructor_by_student');
function get_instructor_by_student() {
    global $wpdb;

    $student_id = intval($_POST['student_id']);
    $table = $wpdb->prefix . 'class_students';

    // Assumes instructor name is stored in wp_class_students table
    $result = $wpdb->get_var($wpdb->prepare(
        "SELECT instructor_name FROM $table WHERE student_id = %d LIMIT 1",
        $student_id
    ));

    if ($result) {
        wp_send_json_success(['instructor_name' => $result]);
    } else {
        wp_send_json_error('Instructor not found.');
    }
}



add_action('wp_ajax_update_month_payment_status', 'update_month_payment_status');
function update_month_payment_status() {
    global $wpdb;

    $student_id = sanitize_text_field($_POST['student_id']);
    $class_name = sanitize_text_field($_POST['class_name']);
    $month = sanitize_text_field($_POST['month']);
    $new_status = sanitize_text_field($_POST['new_status']);
    $payment_method = sanitize_text_field($_POST['payment_method']); // NEW
    $pay_amount = floatval($_POST['pay_amount']);
    $last_paid_date = current_time('mysql');

    $table = $wpdb->prefix . 'student_payments';
    $payment = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE student_id = %s AND class_name = %s",
        $student_id, $class_name
    ));

    if (!$payment) {
        wp_send_json_error('Payment record not found.');
    }

    $paid_months = array_map('trim', explode(',', $payment->paid_months));
    $remaining_months = array_map('trim', explode(',', $payment->remaining_months));

    if ($new_status === 'paid') {
        if (!in_array($month, $paid_months)) {
            $paid_months[] = $month;
        }
        $remaining_months = array_diff($remaining_months, [$month]);
    } elseif ($new_status === 'unpaid') {
        if (!in_array($month, $remaining_months)) {
            $remaining_months[] = $month;
        }
        $paid_months = array_diff($paid_months, [$month]);
    } else {
        wp_send_json_error('Invalid status.');
    }

    $paid_months = array_unique(array_filter($paid_months));
    $remaining_months = array_unique(array_filter($remaining_months));

    // Calculate monthly due
    $old_total = (float)$payment->due_amount;
    $original_remaining_count = count(array_map('trim', explode(',', $payment->remaining_months)));
    $monthly_amount = ($original_remaining_count > 0) ? $old_total / $original_remaining_count : 0;
    $new_due_amount = round($monthly_amount * count($remaining_months), 2);

    $wpdb->update($table, [
        'paid_months'      => implode(', ', $paid_months),
        'remaining_months' => implode(', ', $remaining_months),
        'due_amount'       => $new_due_amount,
        'payment_method'   => $payment_method, // SAVE
        'last_paid_amount'  => $pay_amount,
        'last_paid_date'    => $last_paid_date
    ], [
        'student_id' => $student_id,
        'class_name' => $class_name
    ]);

    wp_send_json_success('Month status updated.');
}
// Daily Financial Report Page//
function daily_financial_submenu() {  
    add_submenu_page(
        'student-registration',         // Parent slug
        'Daily Financial Report',       // Page title
        'Daily Financial Report',       // Menu title
        'edit_pages',                   // Capability
        'daily-financial-report',       // Submenu slug
        'daily_financial_report'        // ✅ Callback function name (no quotes)
    );
}
add_action('admin_menu', 'daily_financial_submenu');

function daily_financial_report() {
    ?>
    <div class="wrap">
        <h1>Daily Financial Report</h1>
            <style>
            .payment-label-bold {
                font-weight: bold;
            }
        </style>

        <form id="financial-report-form">
            <label for="start_date">Start Date:</label>
            <input type="date" id="start_date" name="start_date" required>

            <label for="end_date">End Date:</label>
            <input type="date" id="end_date" name="end_date" required>
        </form>

        <div id="report-results" style="margin-top: 30px;">
            <!-- Radio buttons and total will appear here -->
        </div>

        <div id="report-summary" style="margin-top: 30px;">
            <!-- Summary for selected method -->
        </div>
    </div>

<script>
    jQuery(document).ready(function($) {
        // Set default date values to today
        const today = new Date().toISOString().split('T')[0];
        $('#start_date').val(today);
        $('#end_date').val(today);

        // Fetch report on initial load
        fetchFinancialReport();

        // Fetch when either date changes
        $('#start_date, #end_date').on('change', fetchFinancialReport);

        function fetchFinancialReport() {
            const start = $('#start_date').val();
            const end = $('#end_date').val();

            if (!start || !end) return;

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'get_financial_report',
                    start_date: start,
                    end_date: end
                },
                success: function(response) {
                    $('#report-results').html(response.data.html);
                    $('#report-summary').html('');

                    // Attach event listener to payment method radios
                    $('input[name="payment_method"]').on('change', function() {
                            // Remove bold from all labels first
                          $('input[name="payment_method"]').each(function() {
                              $(this).parent('label').removeClass('payment-label-bold');
                          });

                          // Add bold to the selected one
                          $(this).parent('label').addClass('payment-label-bold');

                          // Show corresponding summary
                          $('#report-summary').html(response.data.summaries[$(this).val()]);
                         });

                    // Auto-select 'cash' radio and trigger change
                    $('input[name="payment_method"][value="cash"]').prop('checked', true).trigger('change');
                }
            });
        }
    });
</script>

    <?php
}

add_action('wp_ajax_get_financial_report', 'get_financial_report');
function get_financial_report() {
    global $wpdb;

$start_date = sanitize_text_field($_POST['start_date']) . ' 00:00:00';
$end_date = sanitize_text_field($_POST['end_date']) . ' 23:59:59';


    $table = $wpdb->prefix . 'student_payments';

    // Get total amount grouped by payment method within date range
    $results = $wpdb->get_results(
        $wpdb->prepare("
            SELECT LOWER(payment_method) as method, SUM(last_paid_amount) as total
            FROM $table
            WHERE last_paid_date BETWEEN %s AND %s
            GROUP BY payment_method
        ", $start_date, $end_date),
        OBJECT_K
    );

    // Get all individual records in that range for summary
$payments = $wpdb->get_results(
    $wpdb->prepare("
        SELECT student_id, payment_method, last_paid_amount, paid_months, class_name
        FROM $table
        WHERE last_paid_date BETWEEN %s AND %s
    ", $start_date, $end_date),
    ARRAY_A
);


    // Initialize totals with fallback 0 if no rows returned
    $totals = [
        'cash' => isset($results['cash']) ? floatval($results['cash']->total) : 0,
        'banktransfer' => isset($results['banktransfer']) ? floatval($results['banktransfer']->total) : 0,
        'card' => isset($results['card']) ? floatval($results['card']->total) : 0,
    ];

$summaries = [
    'cash' => '<table style="width:100%; border-collapse: collapse; border: 1px solid #000;">
        <tr>
            <th style="border: 1px solid #000; padding: 6px;">Student ID</th>
            <th style="border: 1px solid #000; padding: 6px;">Class</th>
            <th style="border: 1px solid #000; padding: 6px;">Month</th>
            <th style="border: 1px solid #000; padding: 6px;">Amount</th>
        </tr>',
    'banktransfer' => '<table style="width:100%; border-collapse: collapse; border: 1px solid #000;">
        <tr>
            <th style="border: 1px solid #000; padding: 6px;">Student ID</th>
            <th style="border: 1px solid #000; padding: 6px;">Class</th>
            <th style="border: 1px solid #000; padding: 6px;">Month</th>
            <th style="border: 1px solid #000; padding: 6px;">Amount</th>
        </tr>',
    'card' => '<table style="width:100%; border-collapse: collapse; border: 1px solid #000;">
        <tr>
            <th style="border: 1px solid #000; padding: 6px;">Student ID</th>
            <th style="border: 1px solid #000; padding: 6px;">Class</th>
            <th style="border: 1px solid #000; padding: 6px;">Month</th>
            <th style="border: 1px solid #000; padding: 6px;">Amount</th>
        </tr>',
];



foreach ($payments as $payment) {
    $method = strtolower($payment['payment_method']);
    $amount = floatval($payment['last_paid_amount']);
    $class_name = esc_html($payment['class_name']);
    $month = esc_html($payment['paid_months']); // Adjust if using multiple months

    if (isset($summaries[$method])) {
        $summaries[$method] .= "<tr>
            <td style='border: 1px solid #000; padding: 6px;'>{$payment['student_id']}</td>
            <td style='border: 1px solid #000; padding: 6px;'>{$class_name}</td>
            <td style='border: 1px solid #000; padding: 6px;'>{$month}</td>
            <td style='border: 1px solid #000; padding: 6px;'>" . number_format($amount, 2) . "</td>
        </tr>";
    }
}


// Properly close each table
foreach ($summaries as $key => $table) {
    $summaries[$key] .= '</table>';
}



    $total_all = array_sum($totals);

    ob_start();
    ?>
<div style="max-width: 400px;">
    <?php
    $methods = [
        'cash' => 'Cash Payments',
        'banktransfer' => 'Bank Transfers',
        'card' => 'Card Payments'
    ];
    foreach ($methods as $key => $label): ?>
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 5px 0;">
            <label style="flex: 1;">
                <input type="radio" name="payment_method" value="<?php echo $key; ?>"> <?php echo $label; ?>
            </label>
            <div style="text-align: right; min-width: 100px;">
                <?php echo number_format($totals[$key], 2); ?>
            </div>
        </div>
    <?php endforeach; ?>
    <div style="margin-top: 10px; font-weight: bold; text-align: right;">
        Total: <?php echo number_format($total_all, 2); ?>
    </div>
</div>

    <?php
    $html = ob_get_clean();

    wp_send_json_success([
        'html' => $html,
        'summaries' => $summaries
    ]);
}




//End Daily Financial Report Page//


//
//<!-- Modal -->
//<div id="paymentModal" style="display:none; position:fixed; z-index:9999; top:0; left:0; width:100%; height:100%; background-color:rgba(0,0,0,0.6);">
//  <div style="background:#fff; margin:10% auto; padding:20px; width:400px; position:relative;">
//    <span id="closeModal" style="position:absolute; right:10px; top:5px; cursor:pointer;">&times;</span>
//    <div id="modalContent">
//      <!-- Form content loads here -->
//    </div>
//  </div>
//</div>
//
//<script>
//jQuery(document).ready(function($){
//    $('.open-payment-modal').on('click', function(e){
//        e.preventDefault();
//        let modalContent = $(this).closest('tr').next('.payment-form-row').html();
//        $('#modalContent').html(modalContent);
//        $('#paymentModal').fadeIn();
//    });
//
//    $('#closeModal').on('click', function(){
//        $('#paymentModal').fadeOut();
//    });
//
//    $(document).on('click', function(e){
//        if ($(e.target).is('#paymentModal')) {
//            $('#paymentModal').fadeOut();
//        }
//    });
//});
//</script>
//<?php




// Hook to admin menu
add_action('admin_menu', function() {
    add_menu_page('Student Payments', 'Student Payments', 'manage_options', 'student-payment-page', 'student_payment_page');
});

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
