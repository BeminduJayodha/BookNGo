<?php   
/**
 * Plugin Name: 
 * Description: 
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
    $invoice_table = $wpdb->prefix . 'booking_invoices';
    $customer_table = $wpdb->prefix . 'booking_customers';
    $charset_collate = $wpdb->get_charset_collate();

    // Booking Table
    $sql1 = "CREATE TABLE $table_name (
        id INT NOT NULL AUTO_INCREMENT,
        customer_name VARCHAR(255) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        booking_date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        color VARCHAR(7) NOT NULL,
        booking_type VARCHAR(50) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    // Invoice Table
    $sql2 = "CREATE TABLE $invoice_table (
        id INT NOT NULL AUTO_INCREMENT,
        booking_id INT NOT NULL,
        invoice_number VARCHAR(20) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        FOREIGN KEY (booking_id) REFERENCES $table_name(id) ON DELETE CASCADE
    ) $charset_collate;";

    // Customers Table
    $sql3 = "CREATE TABLE $customer_table (
        id INT NOT NULL AUTO_INCREMENT,
        customer_type VARCHAR(50) NOT NULL,
        customer_name VARCHAR(255) NOT NULL,
        customer_email VARCHAR(255) NOT NULL,
        customer_phone VARCHAR(20) NOT NULL,
        date_registered TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql1);
    dbDelta($sql2);
    dbDelta($sql3);
}
register_activation_hook(__FILE__, 'booking_calendar_install');



// Uninstall hook to remove database table
function booking_calendar_uninstall() {
    global $wpdb;
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}booking_calendar");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}booking_invoices");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}booking_customers");
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
    
    // Add submenu for Customer Registration
    add_submenu_page('booking-calendar', 'Customer Registration', 'Customer Registration', 'manage_options', 'customer-registration', 'customer_registration_page');
    add_submenu_page('booking-calendar', 'Customer List', 'Customer List', 'manage_options', 'customer-list', 'customer_list_page');
    add_submenu_page('booking-calendar', 'Customer Edit', 'Customer Edit', 'manage_options', 'customer-edit', 'customer_edit_page');
}
add_action('admin_menu', 'booking_calendar_menu');

function customer_registration_page() {
    ?>
<style>
    .customer-form-container {
        max-width: 500px;
        min-height: 400px;
        margin: 50px auto;
        padding: 30px;
        background: #fff;
        text-align: center;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .customer-form-container h2 {
        margin-bottom: 20px;
    }

    .customer-form-container .form-table {
        width: 100%;
        border-collapse: collapse;
    }

    .customer-form-container th,
    .customer-form-container td {
        padding: 8px;
        vertical-align: middle; /* Ensures both label and input/select align */
    }

    .customer-form-container th {
        text-align: left;
        padding-right: 10px;
        width: 40%;
        vertical-align: middle; /* Ensures labels align properly */
        white-space: nowrap;  /* Prevents label from wrapping */
    }

    .customer-form-container input,
    .customer-form-container select {
        width: 100%;
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 4px;
        height: 40px; /* Ensures same height */
        box-sizing: border-box; /* Prevents padding from increasing size */
    }

    .customer-form-container select {
        appearance: none; /* Removes default browser styles */
    }

    .customer-form-container .submit {
        width: 100%;
        background: #0073aa;
        color: white;
        padding: 10px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
        margin-top: 10px;
    }

    .customer-form-container .submit:hover {
        background: #005f8d;
    }

    .popup-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.3);
        justify-content: center;
        align-items: flex-start;
        padding-top: 20px;
    }

    .popup-box {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.2);
        text-align: center;
        max-width: 400px;
        width: 90%;
    }

    .popup-box h3 {
        margin-bottom: 15px;
    }

    .popup-box button {
        background: #0073aa;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
    }

    .popup-box button:hover {
        background: #005f8d;
    }
        .customer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .customer-table th, .customer-table td {
            padding: 10px;
            text-align: left;
            border: 1px solid #ddd;
        }

        .customer-table th {
            background-color: #f4f4f4;
        }
</style>

<div class="wrap"> 
    <div class="customer-form-container">
        <h2>Customer Registration</h2>
        <form method="post" id="customer-registration-form">
            <table class="form-table">
                <tr>
                    <th><label for="customer_type">Customer Type</label></th>
                    <td>
                        <select name="customer_type" id="customer_type" required>
                            <option value="">Select Customer Type</option>
                            <option value="teacher">Teacher</option>
                            <option value="workspace">Workspace</option>
                            <option value="conference">Conference</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="customer_name">Customer Name</label></th>
                    <td><input type="text" name="customer_name" id="customer_name" required></td>
                </tr>
                <tr>
                    <th><label for="customer_email">Email</label></th>
                    <td><input type="email" name="customer_email" id="customer_email" required></td>
                </tr>
                <tr>
                    <th><label for="customer_phone">Phone</label></th>
                    <td><input type="text" name="customer_phone" id="customer_phone" required></td>
                </tr>
            </table>
            <?php submit_button('Register Customer', 'primary', 'register_customer', false); ?>
        </form>
    </div>
</div>

       
            
                        <!-- Popup Modal -->
    <div class="popup-overlay" id="popup">
        <div class="popup-box">
            <h3>Customer Registered Successfully!</h3>
            <button onclick="window.location.href='admin.php?page=booking-calendar'">Go to Booking Calendar</button>
        </div>
    </div>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        const form = document.getElementById("customer-registration-form");
        form.addEventListener("submit", function (event) {
            // Basic validation for customer type selection
            if (document.getElementById("customer_type").value === "") {
                alert("Please select a customer type.");
                event.preventDefault();
                return;
            }

            event.preventDefault(); // Prevent form default submission
            const formData = new FormData(form);

            fetch("", {
                method: "POST",
                body: formData
            })
            .then(response => response.text())
            .then(() => {
                document.getElementById("popup").style.display = "flex";
            });
        });
    });
</script>

<?php
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_customer'])) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'booking_customers';

    $customer_name  = sanitize_text_field($_POST['customer_name']);
    $customer_email = sanitize_email($_POST['customer_email']);
    $customer_phone = sanitize_text_field($_POST['customer_phone']);
    $customer_type  = sanitize_text_field($_POST['customer_type']);

    $wpdb->insert($table_name, [
        'customer_name'  => $customer_name,
        'customer_email' => $customer_email,
        'customer_phone' => $customer_phone,
        'customer_type'  => $customer_type
    ]);



    exit;  // Stop further execution as the success message is handled by JavaScript
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    global $wpdb;
    $table_name = $wpdb->prefix . 'booking_customers';

    $customer_name  = sanitize_text_field($_POST['customer_name']);
    $customer_email = sanitize_email($_POST['customer_email']);
    $customer_phone = sanitize_text_field($_POST['customer_phone']);
    $customer_type  = sanitize_text_field($_POST['customer_type']);

    $wpdb->insert($table_name, [
        'customer_name'  => $customer_name,
        'customer_email' => $customer_email,
        'customer_phone' => $customer_phone,
        'customer_type'  => $customer_type
    ]);
     // Prepare the email content
     $subject = "Welcome to Our Service";
     $message = "
     Hello $customer_name,
     
     Thank you for registering with us. We are excited to have you as a $customer_type.
     
     If you have any questions or need assistance, feel free to contact us.
     
     Best Regards,
     Makerspace Team
     ";
     
     // Set email headers to ensure it comes from your company's email
     $headers = [
         'From: Your Company Name <no-reply@makerspace.lk>',
         'Content-Type: text/html; charset=UTF-8'
     ];
     
     // Send the email
     wp_mail($customer_email, $subject, nl2br($message), $headers);

    echo "<div class='updated'><p>Customer Registered Successfully!</p></div>";
}

}
function customer_list_page() { 
    global $wpdb;
    // Handle customer deletion
    if (isset($_GET['delete_customer']) && is_numeric($_GET['delete_customer'])) {
        $customer_id = $_GET['delete_customer'];

        // Delete the customer from the database
        $wpdb->delete(
            "{$wpdb->prefix}booking_customers", 
            array('id' => $customer_id), 
            array('%d')
        );

        // Redirect to the customer list page after deletion
        wp_redirect(admin_url('admin.php?page=customer-list'));
        exit;
    }
    // Fetch customers from wp_booking_customers table
    $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}booking_customers");

    ?>
    <div class="wrap">
        <h1>Customer List</h1>
        
        <?php if (!empty($results)): ?>
            <table class="wp-list-table widefat fixed striped customers" style="border-collapse: collapse; width: 100%;">
                <thead>
                    <tr style="border: 1px solid black;">
                        <th style="border: 1px solid black; font-weight: bold; text-align: center;">Customer Type</th>
                        <th style="border: 1px solid black; font-weight: bold; text-align: center;">Customer Name</th>
                        <th style="border: 1px solid black; font-weight: bold; text-align: center;">Email</th>
                        <th style="border: 1px solid black; font-weight: bold; text-align: center;">Phone</th>
                        <th style="border: 1px solid black; font-weight: bold; text-align: center;">Date Registered</th>
                        <th style="border: 1px solid black; font-weight: bold; text-align: center;">Actions</th>


                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $customer): ?>
                        <tr style="border: 1px solid black;">
                            <td style="border: 1px solid black; text-align: center;"><?php echo esc_html($customer->customer_type); ?></td>
                            <td style="border: 1px solid black; text-align: center;"><?php echo esc_html($customer->customer_name); ?></td>
                            <td style="border: 1px solid black; text-align: center;"><?php echo esc_html($customer->customer_email); ?></td>
                            <td style="border: 1px solid black; text-align: center;"><?php echo esc_html($customer->customer_phone); ?></td>
                            <td style="border: 1px solid black; text-align: center;"><?php echo esc_html($customer->date_registered); ?></td>
                            <td style="border: 1px solid black; text-align: center;">
                                <a href="<?php echo admin_url('admin.php?page=customer-edit&customer_id=' . $customer->id); ?>" 
                                title="Edit" style="text-decoration: none; color: #0073aa;">
                                <span class="dashicons dashicons-edit"></span>
                                </a> |
                                <a href="<?php echo admin_url('admin.php?page=customer-list&delete_customer=' . $customer->id); ?>" 
                                   onclick="return confirm('Are you sure you want to delete this customer?');" 
                                   title="Delete" style="text-decoration: none; color: #0073aa;">
                                   <span class="dashicons dashicons-trash"></span>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No customers registered yet.</p>
        <?php endif; ?>
    </div>
    <?php
}
function customer_edit_page() {
    global $wpdb;
    
    // Check if customer_id is set and valid
    if (isset($_GET['customer_id']) && is_numeric($_GET['customer_id'])) {
        $customer_id = $_GET['customer_id'];
        
        // Fetch the customer data
        $customer = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}booking_customers WHERE id = $customer_id");
        
        if ($customer) {
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                // Handle form submission and update the database
                $customer_type = sanitize_text_field($_POST['customer_type']);
                $customer_email = sanitize_email($_POST['customer_email']);
                $customer_phone = sanitize_text_field($_POST['customer_phone']);
                
                // Update customer data in the database
                $wpdb->update(
                    "{$wpdb->prefix}booking_customers",
                    array(
                        'customer_type' => $customer_type,
                        'customer_email' => $customer_email,
                        'customer_phone' => $customer_phone
                    ),
                    array('id' => $customer_id),
                    array('%s', '%s', '%s'),
                    array('%d')
                );
                
                // Redirect to the customer list page after saving
                wp_redirect(admin_url('admin.php?page=customer-list'));
                exit;
            }
            
            ?>
            <div class="wrap">
                <h1>Edit Customer</h1>
                <form method="POST">
                    <table class="form-table">
                        <tr>
                            <th><label for="customer_type">Customer Type</label></th>
                            <td>
                                <select name="customer_type" id="customer_type" required>
                                    <option value="teacher" <?php selected($customer->customer_type, 'teacher'); ?>>Teacher</option>
                                    <option value="workspace" <?php selected($customer->customer_type, 'workspace'); ?>>Workspace</option>
                                    <option value="conference" <?php selected($customer->customer_type, 'conference'); ?>>Conference</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="customer_email">Email</label></th>
                            <td><input type="email" name="customer_email" id="customer_email" value="<?php echo esc_attr($customer->customer_email); ?>" required></td>
                        </tr>
                        <tr>
                            <th><label for="customer_phone">Phone</label></th>
                            <td><input type="text" name="customer_phone" id="customer_phone" value="<?php echo esc_attr($customer->customer_phone); ?>" required></td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes">
                    </p>
                </form>
            </div>
            <?php
        } else {
            echo '<p>Customer not found.</p>';
        }
    } else {
        echo '<p>No customer ID provided.</p>';
    }
}




function add_invoice_page() {
    add_submenu_page('booking-calendar', 'View Invoice', 'View Invoice', 'manage_options', 'view-invoice', 'display_invoice_page');
}
add_action('admin_menu', 'add_invoice_page');

function display_invoice_page() {
    global $wpdb;

    // Fetch all invoices from the database
    $invoices = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}booking_invoices");

    if (empty($invoices)) {
        echo '<h2>No invoices generated yet.</h2>';
        return;
    }

    echo '<h2>Generated Invoices</h2>';
    echo '<table class="wp-list-table widefat fixed striped invoices-table" cellspacing="0" cellpadding="5" style="width:100%; border: 1px solid #ddd; margin-bottom: 20px;">
            <thead>
                <tr>
                    <th style="border: 1px solid #ddd;">Invoice Number</th>
                    <th style="border: 1px solid #ddd;">Customer Name</th>
                    <th style="border: 1px solid #ddd;">Booking Date</th>
                    <th style="border: 1px solid #ddd;">Amount</th>
                    <th style="border: 1px solid #ddd;">Actions</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($invoices as $invoice) {
        // Get the associated booking
        $booking = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}booking_calendar WHERE id = {$invoice->booking_id}");

        if ($booking) {
            echo '<tr>';
            echo '<td style="border: 1px solid #ddd;">' . esc_html($invoice->invoice_number) . '</td>';
            echo '<td style="border: 1px solid #ddd;">' . esc_html($booking->customer_name) . '</td>';
            echo '<td style="border: 1px solid #ddd;">' . esc_html($booking->booking_date) . '</td>';
            echo '<td style="border: 1px solid #ddd;">$' . esc_html(number_format($invoice->amount, 2)) . '</td>';
            echo '<td style="border: 1px solid #ddd;">
                    <a href="' . admin_url('admin.php?page=view-invoice&invoice_id=' . $invoice->id) . '" class="button">View Invoice</a>
                  </td>';
            echo '</tr>';
        }
    }

    echo '</tbody>
          </table>';
}


// Booking calendar page with a structured table layout and navigation
function booking_calendar_page() { 
    global $wpdb;

    // Fetch all existing bookings from the database
    $bookings = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}booking_calendar");

    // Get view from the query parameters
    $view = isset($_GET['view']) ? $_GET['view'] : 'month'; // Default to 'month' view
    $current_month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
    $current_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
    $current_day = isset($_GET['day']) ? intval($_GET['day']) : date('d'); // Get the current day if in 'day' view
    $week_start = isset($_GET['week_start']) ? $_GET['week_start'] : date('Y-m-d', strtotime('last Monday'));
    $week_end = isset($_GET['week_end']) ? $_GET['week_end'] : date('Y-m-d', strtotime('last Monday +6 days'));


    // Calculate the previous and next day correctly using strtotime
    $previous_day = date('Y-m-d', strtotime('-1 day', strtotime("$current_year-$current_month-$current_day")));
    $next_day = date('Y-m-d', strtotime('+1 day', strtotime("$current_year-$current_month-$current_day")));

    // Calculate previous and next week
    $previous_week_start = date('Y-m-d', strtotime('-7 days', strtotime($week_start)));
    $previous_week_end = date('Y-m-d', strtotime('-7 days', strtotime($week_end)));
    $next_week_start = date('Y-m-d', strtotime('+7 days', strtotime($week_start)));
    $next_week_end = date('Y-m-d', strtotime('+7 days', strtotime($week_end)));

    // Calculate previous and next year
    $previous_year = $current_year - 1;
    $next_year = $current_year + 1;

    echo '<div class="wrap">
            <h1>Booking Calendar</h1>
            <div class="calendar-navigation-box" style="background-color: #f0f0f0; padding: 15px; border-radius: 8px; border: 1px solid #ccc; box-shadow: 2px 2px 10px rgba(0,0,0,0); margin-bottom: 20px;">
                <div class="calendar-navigation-section" style="display: flex; justify-content: space-between; align-items: center;">';

    // Show navigation buttons
    if ($view == 'day') {
        // Day navigation buttons
        echo '<a href="?page=booking-calendar&view=day&day=' . date('d', strtotime($previous_day)) . '&month=' . date('m', strtotime($previous_day)) . '&year=' . date('Y', strtotime($previous_day)) . '" class="button">&#8249; Previous Day</a>
              <a href="?page=booking-calendar&view=day&day=' . date('d', strtotime($next_day)) . '&month=' . date('m', strtotime($next_day)) . '&year=' . date('Y', strtotime($next_day)) . '" class="button">Next Day &#8250;</a>';
    } elseif ($view == 'week') {
        // Week navigation buttons
        echo '<a href="?page=booking-calendar&view=week&week_start=' . $previous_week_start . '&week_end=' . $previous_week_end . '" class="button">&#8249; Previous Week</a>
              <a href="?page=booking-calendar&view=week&week_start=' . $next_week_start . '&week_end=' . $next_week_end . '" class="button">Next Week &#8250;</a>';
    } elseif ($view == 'year') {
        // Year navigation buttons
        echo '<a href="?page=booking-calendar&view=year&year=' . $previous_year . '" class="button">&#8249; Previous Year</a>
              <a href="?page=booking-calendar&view=year&year=' . $next_year . '" class="button">Next Year &#8250;</a>';
    } else {
        // Month navigation buttons
        echo '<a href="?page=booking-calendar&month=' . ($current_month - 1) . '&year=' . $current_year . '" class="button">&#8249; Previous</a>
              <a href="?page=booking-calendar&month=' . ($current_month + 1) . '&year=' . $current_year . '" class="button">Next &#8250;</a>';
    }

    // Display the correct date header based on the selected view
    echo '<span style="font-size: 18px; font-weight: bold; margin: 0 15px; text-align: center; flex-grow: 1;">';

    if ($view == 'month') {
        // For month view, show month and year
        echo date('F Y', mktime(0, 0, 0, $current_month, 1, $current_year));
    } elseif ($view == 'week') {
        // For week view, show the week range (start to end)
        echo "Week of " . date('F j, Y', strtotime($week_start)) . " - " . date('F j, Y', strtotime($week_end));
    } elseif ($view == 'day') {
        // For day view, show the current day (day, month, year)
        echo date('F j, Y', mktime(0, 0, 0, $current_month, $current_day, $current_year));
    } elseif ($view == 'year') {
        // For year view, show the current year
        echo $current_year;
    }

    echo '</span>';

    // Navigation buttons for view change
    echo '<a href="?page=booking-calendar&view=month&month=' . $current_month . '&year=' . $current_year . '" class="button" style="margin-left: 10px;">Month</a>
          <a href="?page=booking-calendar&view=week&week_start=' . $week_start . '&week_end=' . $week_end . '" class="button" style="margin-left: 10px;">Week</a>
          <a href="?page=booking-calendar&view=day&month=' . $current_month . '&year=' . $current_year . '" class="button" style="margin-left: 10px;">Day</a>
          <a href="?page=booking-calendar&view=year&month=' . $current_month . '&year=' . $current_year . '" class="button" style="margin-left: 10px;">Year</a>
      </div>
  </div>';

    // Render the calendar based on the selected view
    echo '<div id="booking-calendar-container">';
    if ($view == 'month') {
        display_month_view($bookings, $current_month, $current_year);
    } elseif ($view == 'week') {
        display_week_view($bookings, $week_start, $week_end);
    } elseif ($view == 'day') {
        display_day_view($bookings, $current_month, $current_year, $current_day);
    } elseif ($view == 'year') {
        display_year_view($bookings, $current_year);
    }
    echo '</div>';

    echo '</div>';
}





// Handle the booking form submission via AJAX
function save_booking() { 
    global $wpdb;

    // Get data from the AJAX request
    $customer_name = sanitize_text_field($_POST['customer_name']);
    $start_time = sanitize_text_field($_POST['start_time']);
    $end_time = sanitize_text_field($_POST['end_time']);
    $start_date = sanitize_text_field($_POST['start_date']); // Start date of the teacher's class
    $end_date = sanitize_text_field($_POST['end_date']); // End date of the teacher's class
    $booking_type = sanitize_text_field($_POST['booking_type']);

    // Convert start and end dates to DateTime objects
    $start_date = new DateTime($start_date);
    $end_date = new DateTime($end_date);

    // Generate a random color
    $color = '#' . strtoupper(dechex(rand(0, 0xFFFFFF)));

    // Loop through the weeks, booking the class on the same weekday until the end date
    $current_date = clone $start_date; // Clone the start date to modify it
    while ($current_date <= $end_date) {
        // Format the current date as 'Y-m-d' for the booking
        $booking_date = $current_date->format('Y-m-d');

        // Insert the booking into the database for this date
        $wpdb->insert(
            $wpdb->prefix . 'booking_calendar',
            array(
                'customer_name' => $customer_name,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'booking_date' => $booking_date,
                'color' => $color,
                'booking_type' => $booking_type
            )
        );

        // Move to the next week (same day of the week)
        $current_date->modify('+1 week');
    }

    // Generate an invoice number
    $invoice_number = 'INV-' . strtoupper(uniqid());

    // Set an amount based on booking type (Modify as needed)
    $amount = ($booking_type == 'premium') ? 100.00 : 50.00;

    // Save invoice in the database
    $booking_id = $wpdb->insert_id;
    $wpdb->insert(
        $wpdb->prefix . 'booking_invoices',
        array(
            'booking_id' => $booking_id,
            'invoice_number' => $invoice_number,
            'amount' => $amount
        )
    );

    // Get invoice ID
    $invoice_id = $wpdb->insert_id;

    // Generate invoice link
    $invoice_url = admin_url('admin.php?page=view-invoice&invoice_id=' . $invoice_id);

    // Send success response with redirect URL
    wp_send_json_success([
        'message' => 'Booking saved successfully!',
        'invoice_url' => $invoice_url
    ]);
}

add_action('wp_ajax_save_booking', 'save_booking');

function display_month_view($bookings, $current_month, $current_year) {
    // Calculate the first day of the month and number of days
    $first_day_of_month = strtotime("{$current_year}-{$current_month}-01");
    $start_day = date('N', $first_day_of_month);  // 1 = Monday, 7 = Sunday
    $num_days = date('t', $first_day_of_month);   // Number of days in the month

    // Query the database to get existing bookings
    global $wpdb;
    $booked_times = $wpdb->get_results("
        SELECT booking_date, start_time, end_time 
        FROM wp_booking_calendar 
        WHERE YEAR(booking_date) = {$current_year} AND MONTH(booking_date) = {$current_month}
    ");

    // Create a structure to store booked times by date
    $booked_times_by_date = [];
    foreach ($booked_times as $booking) {
        $booked_times_by_date[$booking->booking_date][] = [
            'start_time' => $booking->start_time,
            'end_time' => $booking->end_time
        ];
    }

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
    $previous_month_day = date('t', strtotime("{$current_year}-" . ($current_month - 1) . "-01")) - $start_day + 2; // Start from the day before the first of the month
    $next_month_days = 1; // Start from 1 for next month days

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
                    $booked_time_str .= '<br><small>Type: ' . esc_html($booking->booking_type) . '</small>';  // Added booking type
                    $booked_time_str .= '</div>';
                }
            }

            // Fetch booked times for the current date
            $booked_times_on_date = isset($booked_times_by_date[$current_cell_date]) ? $booked_times_by_date[$current_cell_date] : [];

            // Define the available slots
            $available_slots = [];
            $slot_start_time = strtotime('08:00');
            $slot_end_time = strtotime('19:00');

            // Generate available slots by checking against booked times only if the day has bookings
            if (!empty($booked_times_on_date)) {
                for ($i = $slot_start_time; $i < $slot_end_time; $i += 3600) {
                    $start_time = date('H:i', $i);
                    $end_time = date('H:i', $i + 3600);
                    $is_available = true;

                    // Check if the slot is already booked
                    foreach ($booked_times_on_date as $booked_time) {
                        if (($start_time >= $booked_time['start_time'] && $start_time < $booked_time['end_time']) ||
                            ($end_time > $booked_time['start_time'] && $end_time <= $booked_time['end_time'])) {
                            $is_available = false;
                            break;
                        }
                    }

                    if ($is_available) {
                        $available_slots[] = $start_time . ' - ' . $end_time;
                    }
                }
            }

            // Only show the available slots if there are bookings for the day
            if (($row == 1 && $day >= $start_day) || ($row > 1 && $current_day <= $num_days)) {
                if (!empty($bookings_on_date)) {
                    // Assuming that $booking->id is available as the unique identifier for each booking
echo '<td class="booking-slot" data-day="' . $day . '" data-date="' . $current_cell_date . '" 
                    style="border: 1px solid #000; height: 100px; vertical-align: top; width: 14.28%; 
                    text-align: right; padding: 5px;" 
                    onclick="showBookingModal(\'' . $current_cell_date . '\', \'' . implode(',', $available_slots) . '\')">' . 
                    $current_day . $booked_time_str . '</td>';

                } else {
                    // If there are no bookings, just display the date with any existing bookings
                    echo '<td class="booking-slot" data-day="' . $day . '" data-date="' . $current_cell_date . '" 
                    style="border: 1px solid #000; height: 100px; vertical-align: top; width: 14.28%; text-align: right; padding: 5px;" 
                    onclick="showBookingModal(\'' . $current_cell_date . '\', \'\')">' . $current_day . $booked_time_str . '</td>';
                }
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
// Add booking modal HTML
global $wpdb;

// Query the database to get customer names and types from the wp_booking_customers table
$customers = $wpdb->get_results("SELECT customer_name, customer_type FROM wp_booking_customers");

// Start the modal HTML with the dropdown
echo '<div id="bookingModal" class="modal" style="display:none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); z-index: 9999;">
        <div class="modal-content" style="background: #fff; width: 400px; margin: 100px auto; padding: 20px; border-radius: 8px;">
            <h2>Book Time Slot</h2>
            <form id="bookingForm">
                <input type="hidden" name="booking_date" id="bookingDate">
                
                <label for="booking_type">Booking Type:</label>
                <select name="booking_type" id="booking_type" required style="width: 100%;">
                    <option value="Class Rent">Class Rent</option>
                    <option value="Conference Rent">Conference Rent</option>
                    <option value="Workspace Rent">Workspace Rent</option>
                </select>
                
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <label for="customer_name">Customer Name:</label>
                    <select name="customer_name" id="customer_name" required style="width: 100%;" onchange="checkCustomerType()">';
                
// Fetch customers from the database as before
if (!empty($customers)) {
    foreach ($customers as $customer) {
        echo '<option value="' . esc_attr($customer->customer_name) . '" data-type="' . esc_attr($customer->customer_type) . '">' . esc_html($customer->customer_name) . '</option>';
    }
} else {
    echo '<option value="">No customers found</option>';
}

echo '</select>

                    <!-- Start and End Date Selectors, initially hidden -->
                    <div id="teacherDateSelectors" style="display: none;">
                        <label for="start_date">Start Date:</label>
                        <input type="date" name="start_date" id="start_date" style="width: 100%;" required>
                        
                        <label for="end_date">End Date:</label>
                        <input type="date" name="end_date" id="end_date" style="width: 100%;" required>
                    </div>
                    
                    <label>Available Time Slots:</label>
                    <div id="availableSlots" style="margin-bottom: 10px;"></div>

                    <div style="display: flex; justify-content: space-between; gap: 10px;">
                        <div style="flex: 1;">
                            <label for="start_time">Start Time:</label>
                            <input type="time" name="start_time" id="start_time" required min="08:00" max="19:00" style="width: 100%;">
                        </div>
                        <div style="flex: 1;">
                            <label for="end_time">End Time:</label>
                            <input type="time" name="end_time" id="end_time" required min="08:00" max="19:00" style="width: 100%;">
                        </div>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px;">
                        <input type="submit" value="Save Booking" style="background-color: #21759b; color: white; padding: 10px 20px; border: none; cursor: pointer; border-radius: 5px;">
                        <button type="button" onclick="closeBookingModal()" style="background-color: #21759b; color: white; padding: 10px 20px; border: none; cursor: pointer; border-radius: 5px;">Close</button>
                    </div>
                </div>
            </form>
        </div>
    </div>';

// Add JavaScript to handle customer type change
echo '<script>
    function checkCustomerType() {
    var customerSelect = document.getElementById("customer_name");
    var selectedCustomer = customerSelect.options[customerSelect.selectedIndex];
    var customerType = selectedCustomer.getAttribute("data-type");

    // Show the start and end date selectors for all customer types
    var teacherDateSelectors = document.getElementById("teacherDateSelectors");
    teacherDateSelectors.style.display = "block"; // Show for all customers

    // Get the start and end date elements
    var startDate = document.getElementById("start_date");
    var endDate = document.getElementById("end_date");

    if (customerType === "workspace" || customerType === "conference") {
        // Set end date to the same as the selected start date for workspace and conference
        if (startDate && endDate) {
            endDate.value = startDate.value;
        }
    } else if (customerType === "teacher") {
        // For teachers, end date should not be set automatically
        // Ensure the end date input is empty (if required)
        if (endDate) {
            endDate.value = ""; // Clear the end date, as it should be selected manually
        }
    }
}


</script>';


}

// JavaScript for booking modal handling
function booking_calendar_modal_js() { 
    ?>
    <script type="text/javascript">
        // Function to show the booking modal
        function showBookingModal(date, availableSlots) {
            console.log("showBookingModal() called", date, availableSlots); // Debugging

            document.getElementById("bookingDate").value = date;
            document.getElementById("start_date").value = date;

            // Update available slots inside the modal with checkboxes
            let slotsContainer = document.getElementById("availableSlots");
            if (slotsContainer) {
                slotsContainer.innerHTML = ""; // Clear previous slots

                if (availableSlots) {
                    let slotsArray = availableSlots.split(",");
                    slotsArray.forEach(slot => {
                        let slotElement = document.createElement("div");
                        slotElement.style.margin = "5px 0";

                        let checkbox = document.createElement("input");
                        checkbox.type = "checkbox";
                        checkbox.name = "selected_slots[]";
                        checkbox.value = slot;
                        checkbox.addEventListener('change', updateStartEndTime); // Ensure event listener

                        slotElement.appendChild(checkbox);

                        let label = document.createElement("label");
                        label.textContent = slot;
                        slotElement.appendChild(label);

                        slotsContainer.appendChild(slotElement);
                    });
                } else {
                    slotsContainer.innerHTML = "<p>No available slots</p>";
                }
            }

            document.getElementById("bookingModal").style.display = "block";
        }

        // Function to update start_time and end_time based on selected slots
        function updateStartEndTime() {
            console.log("updateStartEndTime() triggered");

            let selectedSlots = [];
            let checkboxes = document.querySelectorAll('input[name="selected_slots[]"]:checked');

            checkboxes.forEach(checkbox => {
                console.log("Selected Slot:", checkbox.value); // Debugging
                selectedSlots.push(checkbox.value);
            });

            if (selectedSlots.length > 0) {
                let firstSlot = selectedSlots[0].split(' - ');
                let lastSlot = selectedSlots[selectedSlots.length - 1].split(' - ');

                console.log("Start Time:", firstSlot[0]);
                console.log("End Time:", lastSlot[1]);

                document.getElementById("start_time").value = firstSlot[0]; 
                document.getElementById("end_time").value = lastSlot[1]; 
            } else {
                console.log("No slots selected, resetting fields.");
                document.getElementById("start_time").value = '';
                document.getElementById("end_time").value = '';
            }
        }

        function closeBookingModal() {
            document.getElementById('bookingModal').style.display = 'none';
        }

        // Ensure checkboxes work even if dynamically created
        document.addEventListener("DOMContentLoaded", function () {
            document.addEventListener("change", function (event) {
                if (event.target.matches('input[name="selected_slots[]"]')) {
                    updateStartEndTime();
                }
            });
        });

        jQuery(document).ready(function ($) {
            $('#bookingForm').submit(function (e) {
                e.preventDefault();

                var formData = $(this).serialize();

                $.post(ajaxurl, formData + '&action=save_booking', function (response) {
                    if (response.success) {
                        window.location.href = response.data.invoice_url;
                    } else {
                        alert('Booking failed. Please try again.');
                    }
                });
            });
        });
    </script>
    <?php
}
add_action('admin_footer', 'booking_calendar_modal_js');








function generateCustomerColor($customer_name) {
    // Generate a color based on the customer's name using hash and then create a color code
    $hash = md5($customer_name);
    $r = hexdec(substr($hash, 0, 2));
    $g = hexdec(substr($hash, 2, 2));
    $b = hexdec(substr($hash, 4, 2));

    // Return the color in rgb format
    return "rgb($r, $g, $b)";
}

function display_week_view($bookings, $week_start, $week_end) {
    // Filter bookings for the current week
    $filtered_bookings = array_filter($bookings, function ($booking) use ($week_start, $week_end) {
        return ($booking->booking_date >= $week_start && $booking->booking_date <= $week_end);
    });

    echo '<table border="1" cellspacing="0" cellpadding="5" style="width:100%; text-align:center; border-collapse: collapse; table-layout: fixed;">
            <thead>
                <tr>
                    <th style="width: 10%;">Time</th>'; // Time column header

    // Generate weekday headers dynamically with the date
    for ($i = 0; $i < 7; $i++) {
        $day = date('l', strtotime("+$i day", strtotime($week_start)));  // Day of the week (Monday, Tuesday, etc.)
        $date = date('n/j', strtotime("+$i day", strtotime($week_start))); // Date in the format Month/Day (2/24, 2/25, etc.)
        echo "<th>$day<br>$date</th>";  // Display day and date
    }

    echo '    </tr>
            </thead>
            <tbody>';

    // Generate time slots from 7:00 AM to 7:00 PM (7 to 19 in 24-hour format)
for ($hour = 7; $hour <= 19; $hour++) {
    // Format hour as 07.00, 08.00, etc.
    $formatted_hour = sprintf("%02d.00", $hour);
    echo '<tr>';
    echo '<td style="border: 1px solid #000; font-weight: bold;">' . $formatted_hour . '</td>'; // Time column with formatted time

    // Fill in the week days with bookings
    for ($i = 0; $i < 7; $i++) {
        $current_date = date('Y-m-d', strtotime("+$i day", strtotime($week_start)));
        $cell_style = 'border: 1px solid #000; height: 50px; vertical-align: middle; text-align: center; position: relative;';
        $booking_info = "";
        $background = "";

        foreach ($filtered_bookings as $booking) {
            if ($booking->booking_date == $current_date) {
                $start_time = strtotime($booking->start_time);
                $end_time = strtotime($booking->end_time);
                $slot_start = strtotime("$hour:00");
                $slot_end = strtotime("$hour:59");

                // If booking starts or ends in this hour slot
                if ($slot_start <= $end_time && $slot_end >= $start_time) {
                    $top_fill = 0;
                    $bottom_fill = 100;

                    // Adjust for half-hour start (color bottom 50% if start time is half-hour)
                    if ($start_time > $slot_start && $start_time <= strtotime("$hour:30")) {
                        $top_fill = 50;  // This fills the bottom 50% of the current hour cell
                    } elseif ($start_time > strtotime("$hour:30") && $start_time < $slot_end) {
                        $top_fill = 0;  // No fill in the first half of the hour
                    }

                    // Adjust for half-hour end (color top 50% if end time is half-hour)
                    if ($end_time >= strtotime("$hour:00") && $end_time <= strtotime("$hour:30")) {
                        $bottom_fill = 100; // Color the top 50% for half-hour end
                    } elseif ($end_time > strtotime("$hour:30") && $end_time <= $slot_end) {
                        $bottom_fill = 50; // Color the whole cell for the rest of the hour
                    }

                    // Special case for bookings ending at the very end of an hour (e.g., 6:00 PM)
                    if ($end_time == $slot_end) {
                        $bottom_fill = 100; // Ensure the full cell is colored if the end time matches the slot's end
                    }

                    // Generate a color for the customer
                    $customer_color = generateCustomerColor($booking->customer_name);

                    // Gradient fill for partial bookings
                    $background = "background: linear-gradient(to bottom, $customer_color {$top_fill}%, $customer_color {$bottom_fill}%, white {$bottom_fill}%); color: white; font-weight: bold;";

                    // Prepare the booking info to display
                    $booking_info = esc_html($booking->customer_name) . '<br>' . esc_html($booking->start_time . ' - ' . $booking->end_time);
                }
            }
        }

        // Display the booking info with the background color and customer name for all relevant cells
        if (!empty($booking_info)) {
            echo '<td data-date="' . $current_date . '" style="' . $cell_style . $background . '">';
            echo '<div style="padding: 5px;">' . $booking_info . '</div>';
            echo '</td>';
        } else {
            echo '<td data-date="' . $current_date . '" style="' . $cell_style . '"></td>';
        }
    }

    echo '</tr>';
}


    echo '    </tbody>
          </table>';
}












function display_day_view($bookings, $current_month, $current_year, $current_day) {
    // Calculate current date based on the selected month, year, and day
    $current_date = mktime(0, 0, 0, $current_month, $current_day, $current_year);
    $formatted_date = date('F j, Y', $current_date); // Display the date as "Month Day, Year"

    //// Display the current day
    //echo "<h3>Day View " . $formatted_date . "</h3>";
//
    //// Display relevant booked slots for the current date in a table
    //echo "<h4>Booked Slots for " . $formatted_date . "</h4>";

    // Filter the bookings for the current date
    $relevant_bookings = array_filter($bookings, function ($booking) use ($current_date) {
        return $booking->booking_date == date('Y-m-d', $current_date);  // Filter bookings that match the current date
    });

    // If there are no bookings
    if (empty($relevant_bookings)) {
        echo "<p>No bookings for today.</p>";
    } else {
        // Start the table
        echo "<table border='1' cellspacing='0' cellpadding='10' style='width:100%; border-collapse: collapse;'>";
        echo "<thead><tr><th>Customer Name</th><th>Start Time</th><th>End Time</th></tr></thead>";
        echo "<tbody>";

        // Loop through each booking and display its details
        foreach ($relevant_bookings as $booking) {
            echo "<tr>";
            echo "<td>" . esc_html($booking->customer_name) . "</td>";
            echo "<td>" . esc_html($booking->start_time) . "</td>";
            echo "<td>" . esc_html($booking->end_time) . "</td>";
            echo "</tr>";
        }

        echo "</tbody></table>";
    }
}


function display_year_view($bookings, $current_year) {
    // Display the current year with months and bookings
    //echo "<h3>Year View for " . $current_year . "</h3>";

    // Loop through all 12 months and display them
    for ($month = 1; $month <= 12; $month++) {
        $month_name = date('F', mktime(0, 0, 0, $month, 1, $current_year));
        echo "<h4>$month_name</h4>";

        // Filter the bookings for the current month and year
        $relevant_bookings = array_filter($bookings, function ($booking) use ($current_year, $month) {
            return date('Y', strtotime($booking->booking_date)) == $current_year && date('m', strtotime($booking->booking_date)) == $month;
        });

        // If there are no bookings
        if (empty($relevant_bookings)) {
            echo "<p>No bookings for this month.</p>";
        } else {
            // Start the table for bookings in this month
            echo "<table border='1' cellspacing='0' cellpadding='10' style='width:100%; border-collapse: collapse;'>";
            echo "<thead><tr><th>Customer Name</th><th>Booking Date</th><th>Start Time</th><th>End Time</th></tr></thead>";
            echo "<tbody>";

            // Loop through each booking and display its details
            foreach ($relevant_bookings as $booking) {
                echo "<tr>";
                echo "<td>" . esc_html($booking->customer_name) . "</td>";
                echo "<td>" . esc_html($booking->booking_date) . "</td>";
                echo "<td>" . esc_html($booking->start_time) . "</td>";
                echo "<td>" . esc_html($booking->end_time) . "</td>";
                echo "</tr>";
            }

            echo "</tbody></table>";
        }
    }
}







