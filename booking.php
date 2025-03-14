<?php   

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
        customer_image TEXT;
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
    /* Optional: Style the overlay button */
    #image-upload-overlay {
        font-size: 30px;
        color: white;
        padding: 10px;
        border-radius: 50%;
        cursor: pointer;
    }
</style>

<div class="wrap"> 
    <div class="customer-form-container">
        <h2>Customer Registration</h2>
        <form method="post" id="customer-registration-form" enctype="multipart/form-data">
            <table class="form-table">
                <tr>
                    <th><label for="customer_image">Customer Image</label></th>
                    <td>
                        <!-- Image preview container with "+" button overlay -->
                        <div id="image-preview-container" style="position: relative; display: inline-block;">
                            <!-- Sample black and white image -->
                            <img id="image-preview" src="https://designhouse.lk/wp-content/uploads/2025/03/sample.png" alt="Sample Image" style="max-width: 150px; display: block; padding: 5px;">
                            <!-- Overlay with '+' button -->
                            <div id="image-upload-overlay" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 30px; color: black; padding: 10px; border-radius: 50%; cursor: pointer;">
                                +
                            </div>
                            <!-- Hidden file input that gets triggered by the overlay click -->
                            <input type="file" name="customer_image" id="customer_image" accept="image/*" onchange="previewImage(event)" style="display: none;">
                        </div>
                    </td>
                </tr>

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
<script>
    // Trigger file input when the "+" button is clicked
    document.getElementById('image-upload-overlay').addEventListener('click', function() {
        document.getElementById('customer_image').click();
    });

    function previewImage(event) {
        var file = event.target.files[0];
        var reader = new FileReader();

        reader.onload = function(e) {
            var preview = document.getElementById('image-preview');
            var placeholder = document.getElementById('image-preview-placeholder');

            preview.style.display = 'block';
            preview.src = e.target.result;
            placeholder.style.display = 'none';
        };

        if (file) {
            reader.readAsDataURL(file);
        }
    }
</script>
       
            
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

    $image_url = '';

    if (!empty($_FILES['customer_image']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $uploaded = media_handle_upload('customer_image', 0);

        if (!is_wp_error($uploaded)) {
            $image_url = wp_get_attachment_url($uploaded);
        }
    }

    $wpdb->insert($table_name, [
        'customer_name'  => $customer_name,
        'customer_email' => $customer_email,
        'customer_phone' => $customer_phone,
        'customer_type'  => $customer_type,
        'customer_image' => $image_url
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

    $image_url = '';
    if (!empty($_FILES['customer_image']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $uploaded = media_handle_upload('customer_image', 0);
        if (!is_wp_error($uploaded)) {
            $image_url = wp_get_attachment_url($uploaded);
        }
    }

    $wpdb->insert($table_name, [
        'customer_name'  => $customer_name,
        'customer_email' => $customer_email,
        'customer_phone' => $customer_phone,
        'customer_type'  => $customer_type,
        'customer_image' => $image_url,
    ]);

    // Send welcome email
    $subject = "Welcome to Our Service";
    $message = "
    Hello $customer_name,
    
    Thank you for registering with us. We are excited to have you as a $customer_type.
    
    If you have any questions or need assistance, feel free to contact us.
    
    Best Regards,
    Makerspace Team
    ";

    $headers = [
        'From: Your Company Name <no-reply@makerspace.lk>',
        'Content-Type: text/html; charset=UTF-8'
    ];

    wp_mail($customer_email, $subject, nl2br($message), $headers);

    exit;
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
                        <th style= "border: 1px solid black; font-weight: bold; text-align: center;">Image</th>
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
                    <td style="text-align: center;">
    <?php if (!empty($customer->customer_image)): ?>
        <img src="<?php echo esc_url($customer->customer_image); ?>" alt="Customer Image" width="60">
    <?php else: ?>
        No image
    <?php endif; ?>
</td>
                            <td style="border: 1px solid black; text-align: center;"><?php echo esc_html($customer->customer_type); ?></td>
                            <td style="border: 1px solid black; text-align: center;"><?php echo esc_html($customer->customer_name); ?></td>
                            <td style="border: 1px solid black; text-align: center;"><?php echo esc_html($customer->customer_email); ?></td>
                            <td style="border: 1px solid black; text-align: center;"><?php echo esc_html($customer->customer_phone); ?></td>
                            <td style="border: 1px solid black; text-align: center;"><?php echo esc_html($customer->date_registered); ?></td>

                            <td style="border: 1px solid black; text-align: center;">
                                <a href="<?php echo admin_url('admin.php?page=customer-edit&customer_id=' . $customer->id); ?>" 
                                title="Edit" style="text-decoration: none; color: #0073aa;">
                                <span class="dashicons dashicons-edit"></span>
                                </a> 
                                <?php /*<a href="<?php echo admin_url('admin.php?page=customer-list&delete_customer=' . $customer->id); ?>" 
                                   onclick="return confirm('Are you sure you want to delete this customer?');" 
                                   title="Delete" style="text-decoration: none; color: #0073aa;">
                                <span class="dashicons dashicons-trash"></span>
                                </a>
                                */ ?>
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
$current_day = isset($_GET['day']) ? intval($_GET['day']) : date('d');

// Calculate current week's Monday as start
$today = date('Y-m-d');
$day_of_week = date('w'); // 0 (Sunday) to 6 (Saturday)
$week_start = isset($_GET['week_start']) 
    ? $_GET['week_start'] 
    : date('Y-m-d', strtotime($today . ' -' . ($day_of_week == 0 ? 6 : $day_of_week - 1) . ' days'));

// End of the current week is 6 days after the start
$week_end = isset($_GET['week_end']) 
    ? $_GET['week_end'] 
    : date('Y-m-d', strtotime($week_start . ' +6 days'));



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
    // Output inline CSS to style the active button
echo '<style>
    .button {
        font-size: 16px;
        padding: 6px 12px;
        text-decoration: none;
        border: 1px solid #ffffff;
        background-color: white;
        color: #333;
        border-radius: 4px;
        margin-left: 10px;
        cursor: pointer;
    }

    .button:hover {
        background-color: #04a8ff !important;
        color: white !important;; /* Ensure text color changes on hover */
    }

    .button.active {
        background-color: #04a8ff !important;
        color: white !important;
        font-weight: bold;
    }
</style>';


    echo '<div class="wrap">
            <h1>Booking Calendar</h1>
            <div class="calendar-navigation-box" style="background-color: #f0f0f0; padding: 15px; border-radius: 8px; border: 1px solid #ccc; box-shadow: 2px 2px 10px rgba(0,0,0,0); margin-bottom: 20px;">
                <div class="calendar-navigation-section" style="display: flex; justify-content: space-between; align-items: center;">';

    // Show navigation buttons
    echo '<div style="display: flex; align-items: center; justify-content: center; flex-grow: 1;">';
    
    if ($view == 'day') {
        echo '<a href="?page=booking-calendar&view=day&day=' . date('d', strtotime($previous_day)) . '&month=' . date('m', strtotime($previous_day)) . '&year=' . date('Y', strtotime($previous_day)) . '" class="button" title="Previous Day"><strong style="font-size: 15px;">&#8249;</strong></a>';
        echo '<span style="margin: 0 15px; font-size: 18px; font-weight: bold;">' . date('F j, Y', mktime(0, 0, 0, $current_month, $current_day, $current_year)) . '</span>';
        echo '<a href="?page=booking-calendar&view=day&day=' . date('d', strtotime($next_day)) . '&month=' . date('m', strtotime($next_day)) . '&year=' . date('Y', strtotime($next_day)) . '" class="button" title="Next Day"><strong style="font-size: 15px;">&#8250;</strong></a>';
    
    } elseif ($view == 'week') {
        echo '<a href="?page=booking-calendar&view=week&week_start=' . $previous_week_start . '&week_end=' . $previous_week_end . '" class="button" title="Previous Week"><strong style="font-size: 15px;">&#8249;</strong></a>';
        echo '<span style="margin: 0 15px; font-size: 18px; font-weight: bold;">Week of ' . date('F j, Y', strtotime($week_start)) . ' - ' . date('F j, Y', strtotime($week_end)) . '</span>';
        echo '<a href="?page=booking-calendar&view=week&week_start=' . $next_week_start . '&week_end=' . $next_week_end . '" class="button" title="Next Week"><strong style="font-size: 15px;">&#8250;</strong></a>';
    
    } elseif ($view == 'year') {
        echo '<a href="?page=booking-calendar&view=year&year=' . $previous_year . '" class="button" title="Previous Year"><strong style="font-size: 15px;">&#8249;</strong></a>';
        echo '<span style="margin: 0 15px; font-size: 18px; font-weight: bold;">' . $current_year . '</span>';
        echo '<a href="?page=booking-calendar&view=year&year=' . $next_year . '" class="button" title="Next Year"><strong style="font-size: 15px;">&#8250;</strong></a>';
    
    } else {
        // Wrap-around month fix
        $previous_month = $current_month - 1;
        $next_month = $current_month + 1;
        $prev_month_year = $current_year;
        $next_month_year = $current_year;
    
        if ($previous_month < 1) {
            $previous_month = 12;
            $prev_month_year--;
        }
        if ($next_month > 12) {
            $next_month = 1;
            $next_month_year++;
        }
    
        echo '<a href="?page=booking-calendar&month=' . $previous_month . '&year=' . $prev_month_year . '" class="button" title="Previous Month"><strong style="font-size: 15px;">&#8249;</strong></a>';
        echo '<span style="margin: 0 15px; font-size: 18px; font-weight: bold;">' . date('F Y', mktime(0, 0, 0, $current_month, 1, $current_year)) . '</span>';
        echo '<a href="?page=booking-calendar&month=' . $next_month . '&year=' . $next_month_year . '" class="button" title="Next Month"><strong style="font-size: 15px;">&#8250;</strong></a>';
    }
    
    echo '</div>';


    // Display the correct date header based on the selected view
    //echo '<span style="font-size: 18px; font-weight: bold; margin: 0 15px; text-align: center; flex-grow: 1;">';
//
    //if ($view == 'month') {
    //    // For month view, show month and year
    //    echo date('F Y', mktime(0, 0, 0, $current_month, 1, $current_year));
    //} elseif ($view == 'week') {
    //    // For week view, show the week range (start to end)
    //    echo "Week of " . date('F j, Y', strtotime($week_start)) . " - " . date('F j, Y', strtotime($week_end));
    //} elseif ($view == 'day') {
    //    // For day view, show the current day (day, month, year)
    //    echo date('F j, Y', mktime(0, 0, 0, $current_month, $current_day, $current_year));
    //} elseif ($view == 'year') {
    //    // For year view, show the current year
    //    echo $current_year;
    //}
//
    //echo '</span>';

    // Navigation buttons for view change
    echo '<a href="?page=booking-calendar&view=month&month=' . $current_month . '&year=' . $current_year . '" class="button ' . ($view == 'month' ? 'active' : '') . '" style="margin-left: 10px;">Month</a>
          <a href="?page=booking-calendar&view=week&week_start=' . $week_start . '&week_end=' . $week_end . '" class="button ' . ($view == 'week' ? 'active' : '') . '" style="margin-left: 10px;">Week</a>
          <a href="?page=booking-calendar&view=day&month=' . $current_month . '&year=' . $current_year . '" class="button ' . ($view == 'day' ? 'active' : '') . '" style="margin-left: 10px;">Day</a>
          <a href="?page=booking-calendar&view=year&month=' . $current_month . '&year=' . $current_year . '" class="button ' . ($view == 'year' ? 'active' : '') . '" style="margin-left: 10px;">Year</a>
      </div>
  </div>';

    // Render the calendar based on the selected view
    echo '<div id="booking-calendar-container">';
    if ($view == 'month') {
        display_month_view($bookings, $current_month, $current_year);
    } elseif ($view == 'week') {
        display_week_view($bookings, $week_start, $week_end);
    } elseif ($view == 'day') {
        display_day_view($current_day, $current_month, $current_year);
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
    $start_date = sanitize_text_field($_POST['start_date']); 
    $end_date = sanitize_text_field($_POST['end_date']);
    $booking_type = sanitize_text_field($_POST['booking_type']);

    // Convert dates to DateTime objects
    $start_date = new DateTime($start_date);
    $end_date = new DateTime($end_date);

    // Generate a random color
    $color = '#' . strtoupper(dechex(rand(0, 0xFFFFFF)));

    // Loop through the weeks, booking the class on the same weekday until the end date
    $current_date = clone $start_date; 
    while ($current_date <= $end_date) {
        $booking_date = $current_date->format('Y-m-d');

        // **Check for existing booking conflict**
        $existing_booking = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}booking_calendar 
                 WHERE booking_date = %s 
                 AND ((start_time >= %s AND start_time < %s) 
                 OR (end_time > %s AND end_time <= %s) 
                 OR (start_time <= %s AND end_time >= %s))",
                $booking_date, $start_time, $end_time, $start_time, $end_time, $start_time, $end_time
            )
        );

        if ($existing_booking > 0) {
            // **Return error message if the time slot is already booked**
            wp_send_json_error(['message' => 'Time slot is already booked for this date. Please choose another time.']);
            return;
        }

        // **Insert booking if no conflicts**
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

        $current_date->modify('+1 week');
    }

    // Generate invoice
    $invoice_number = 'INV-' . strtoupper(uniqid());
    $amount = ($booking_type == 'premium') ? 100.00 : 50.00;
    $booking_id = $wpdb->insert_id;

    $wpdb->insert(
        $wpdb->prefix . 'booking_invoices',
        array(
            'booking_id' => $booking_id,
            'invoice_number' => $invoice_number,
            'amount' => $amount
        )
    );

    $invoice_id = $wpdb->insert_id;
    $invoice_url = admin_url('admin.php?page=view-invoice&invoice_id=' . $invoice_id);

    // Send success response
    wp_send_json_success([
        'message' => 'Booking saved successfully!',
        'invoice_url' => $invoice_url
    ]);
}

add_action('wp_ajax_save_booking', 'save_booking');


function display_month_view($bookings, $current_month, $current_year) {  
    // Get today's date
    $current_date = date('Y-m-d');

    // Calculate the first day of the month and number of days
    $first_day_of_month = strtotime("{$current_year}-{$current_month}-01");
    $start_day = date('N', $first_day_of_month);  // 1 = Monday, 7 = Sunday
    $num_days = date('t', $first_day_of_month);   // Number of days in the month

    // Query the database to get existing bookings
    global $wpdb;
    $booked_times = $wpdb->get_results("SELECT booking_date, start_time, end_time FROM wp_booking_calendar WHERE YEAR(booking_date) = {$current_year} AND MONTH(booking_date) = {$current_month}");

    // Create a structure to store booked times by date
    $booked_times_by_date = [];
    foreach ($booked_times as $booking) {
        $booked_times_by_date[$booking->booking_date][] = [
            'start_time' => $booking->start_time,
            'end_time' => $booking->end_time
        ];
    }
    // Create a structure to store customer images by customer name
    $customer_images = [];
    $customers = $wpdb->get_results("SELECT customer_name, customer_image FROM wp_booking_customers");
    foreach ($customers as $customer) {
        $customer_images[$customer->customer_name] = $customer->customer_image;
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
                    $icon = '';
                    if ($booking->booking_type == 'Class Rent') {
                        $icon = '<span class="dashicons dashicons-welcome-learn-more"></span>';
                    } elseif ($booking->booking_type == 'Conference Rent') {
                        $icon = '<span class="dashicons dashicons-groups"></span>';
                    } elseif ($booking->booking_type == 'Workspace Rent') {
                        $icon = '<span class="dashicons dashicons-money"></span>';
                    }
                
                    $customer_image_url = isset($customer_images[$booking->customer_name]) ? $customer_images[$booking->customer_name] : '';
                    $booked_time_str .= '<div style="position: relative; background-color:' . esc_attr($booking->color) . '; color: white; padding: 15px; margin: 5px 0; border-radius: 6px; display: flex; flex-direction: column; justify-content: center; position: relative;">';
                    
                    // Icon in the top-left corner
                    $booked_time_str .= '<span style="position: absolute; top: 5px; left: 5px; font-size: 18px;">' . $icon . '</span>';
                    
                    // Booking details aligned to the right
                    $booked_time_str .= '<div style="position: absolute; top: 2px; right: 5px;">';
                    if (!empty($customer_image_url)) {
                        $booked_time_str .= '<img src="' . esc_url($customer_image_url) . '" alt="Customer Image" style="width: 30px; height: 30px; border-radius: 50%;"> ';
                    }
                    $booked_time_str .= '</div>';
                    $booked_time_str .= '<br>';
                    $booked_time_str .= esc_html($booking->customer_name) . '<br>' . esc_html(date('H:i', strtotime($booking->start_time)) . ' - ' . date('H:i', strtotime($booking->end_time)));
                    $booked_time_str .= '<br><small>Type: ' . esc_html($booking->booking_type) . '</small>';
                
                    $booked_time_str .= '</div></div>';
                }
            }

            // Highlight today's date with a red border
            $highlight_border = ($current_cell_date == $current_date) ? 'border: 3px solid #49d200;' : 'border: 1px solid #000;';

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
            // Convert the booked times to timestamps for comparison
            $booking_start_time = strtotime($booked_time['start_time']);
            $booking_end_time = strtotime($booked_time['end_time']);
            $slot_start_timestamp = strtotime($start_time);
            $slot_end_timestamp = strtotime($end_time);

            // If the slot overlaps with the booked time, mark it as unavailable
            if (($slot_start_timestamp >= $booking_start_time && $slot_start_timestamp < $booking_end_time) ||
                ($slot_end_timestamp > $booking_start_time && $slot_end_timestamp <= $booking_end_time)) {
                $is_available = false;
                break;
            }
        }

        // Only add available slots
        if ($is_available) {
            $available_slots[] = $start_time . ' - ' . $end_time;
        }
    }
}


            // Only show the available slots if there are bookings for the day
            if (($row == 1 && $day >= $start_day) || ($row > 1 && $current_day <= $num_days)) {
                // Only display the modal if the date is not in the past
                $onclick_event = '';
                if ($current_cell_date >= $current_date) {
                    if (!empty($bookings_on_date)) {
                        // Assuming that $booking->id is available as the unique identifier for each booking
                        $onclick_event = "onclick=\"showBookingModal('{$current_cell_date}', '" . implode(',', $available_slots) . "')\"";
                    } else {
                        $onclick_event = "onclick=\"showBookingModal('{$current_cell_date}', '')\"";
                    }
                    echo '<td class="booking-slot" data-day="' . $day . '" data-date="' . $current_cell_date . '" 
                        style="' . $highlight_border . ' height: 100px; vertical-align: top; width: 14.28%; 
                        text-align: right; padding: 5px;" ' . $onclick_event . '>' . $current_day . $booked_time_str . '</td>';
                } else {
                    // For past dates, we don't add the onclick event and don't change any visual style.
                    echo '<td class="booking-slot" data-day="' . $day . '" data-date="' . $current_cell_date . '" 
                        style="' . $highlight_border . ' height: 100px; vertical-align: top; width: 14.28%; 
                        text-align: right; padding: 5px;opacity: 0.7;">' . $current_day . $booked_time_str . '</td>';
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
             <select name="booking_type" id="booking_type" required style="width: 100%;" onchange="checkBookingType()">
                 <option value="" disabled selected>Select Booking Type</option>
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
                        
                        <input type="date" name="start_date" id="start_date" style="width: 100%;" required readonly>
                        
                        <label for="end_date">End Date:</label>
                        <input type="date" name="end_date" id="end_date" style="width: 100%;" required>
                    </div>
                    
                    <div id="timeSlotContainer" style="display: none;">
    <label>Available Time Slots:</label>
    <div id="availableSlots" style="margin-bottom: 10px;"></div>
</div>


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
function checkBookingType() { 
    var bookingSelect = document.getElementById("booking_type");
    var bookingType = bookingSelect.value;

    // Show the start and end date selectors for all booking types
    var teacherDateSelectors = document.getElementById("teacherDateSelectors");
    teacherDateSelectors.style.display = "block";

    var startDate = document.getElementById("start_date");
    var endDate = document.getElementById("end_date");

    // Handle date logic
    if (bookingType === "Workspace Rent" || bookingType === "Conference Rent") {
        if (startDate && endDate) {
            endDate.value = startDate.value;
            endDate.setAttribute("readonly", true);
        }
    } else if (bookingType === "Class Rent") {
        if (endDate) {
            endDate.removeAttribute("readonly");
            endDate.value = "";
        }
    }

    // Handle time slot visibility
    var timeSlotContainer = document.getElementById("timeSlotContainer");
    if (bookingType === "Class Rent") {
        if (timeSlotContainer) {
            timeSlotContainer.style.display = "none";
        }
    } else {
        if (timeSlotContainer) {
            timeSlotContainer.style.display = "block";
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
    var currentDate = new Date().toISOString().split('T')[0]; // Get the current date in 'YYYY-MM-DD' format
    
    // Prevent opening the modal for past dates
    if (date < currentDate) {
        alert("Cannot book for past dates.");
        return;
    }

    console.log("showBookingModal() called", date, availableSlots); // Debugging

    document.getElementById("bookingDate").value = date;
    document.getElementById("start_date").value = date;

    // Get the available slots container and label
    let slotsContainer = document.getElementById("availableSlots");
    let slotsLabel = slotsContainer.previousElementSibling; // Assuming label is right before the div

    if (slotsContainer) {
        slotsContainer.innerHTML = ""; // Clear previous slots

        if (availableSlots && availableSlots.trim() !== "") {
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

            // Show label only if slots exist
            slotsLabel.style.display = "block"; 
            slotsContainer.style.display = "block";
        } else {
            slotsLabel.style.display = "none"; 
            slotsContainer.style.display = "none";
        }
    }

    document.getElementById("bookingModal").style.display = "block";
}



        // Function to update start_time and end_time based on selected slots
function updateStartEndTime() {
    console.log("updateStartEndTime() triggered");

    // Get all slot checkboxes
    let allCheckboxes = Array.from(document.querySelectorAll('input[name="selected_slots[]"]'));
    let checkedIndexes = allCheckboxes
        .map((cb, idx) => cb.checked ? idx : -1)
        .filter(idx => idx !== -1);

    // If at least two checkboxes are selected, fill the gap
    if (checkedIndexes.length >= 2) {
        let minIndex = Math.min(...checkedIndexes);
        let maxIndex = Math.max(...checkedIndexes);

        // Automatically check all in-between checkboxes
        for (let i = minIndex; i <= maxIndex; i++) {
            allCheckboxes[i].checked = true;
        }
    }

    // Now update start and end time as usual
    let selectedSlots = [];
    allCheckboxes.forEach(checkbox => {
        if (checkbox.checked) {
            console.log("Selected Slot:", checkbox.value);
            selectedSlots.push(checkbox.value);
        }
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
                        alert('Time slot is already booked for this date. Please choose available time slots.');
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
    // Get today's date
    $today = date('Y-m-d');  // Today's date in Y-m-d format
    
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
        
        // Check if this is today's day and highlight it
        $highlight_day = (date('Y-m-d', strtotime("+$i day", strtotime($week_start))) == $today) ? 'color: #49d200; font-weight: bold;' : '';
        
        echo "<th style='$highlight_day'>$day<br>$date</th>";  // Display day and date with highlighted day if it's today
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

                        // Prepare the booking info to display (removes seconds)
                        $booking_info = esc_html($booking->customer_name);
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






function display_day_view($day, $month, $year) {
    global $wpdb;

    $selected_date = date('Y-m-d', mktime(0, 0, 0, $month, $day, $year));

    // Fetch only bookings for this specific day
    $bookings = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}booking_calendar 
             WHERE booking_date = %s ORDER BY start_time",
            $selected_date
        )
    );

    //echo '<h2 style="margin-top: 20px;">Day View – ' . date('F j, Y', strtotime($selected_date)) . '</h2>';

    // Time range (8AM to 7PM)
    $start_hour = 8;
    $end_hour = 19;
    $total_hours = $end_hour - $start_hour;

    echo '<div style="display: flex; border: 1px solid #ccc; height: ' . (($end_hour - $start_hour) * 65) . 'px; overflow-y: auto; position: relative; font-family: Arial, sans-serif;">';


    // Time labels
    echo '<div style="width: 80px; background-color: #f9f9f9; border-right: 1px solid #ccc;">';
    for ($hour = $start_hour; $hour <= $end_hour; $hour++) {
        $time_label = date('g A', mktime($hour, 0));
        echo '<div style="height: 50px; padding: 5px; font-size: 12px; text-align: right; color: #666;">' . $time_label . '</div>';
    }
    echo '</div>';

    // Booking slots container
    echo '<div style="flex-grow: 1; position: relative;">';

    // Grid background
    for ($hour = $start_hour; $hour <= $end_hour; $hour++) {
        echo '<div style="height: 50px; border-bottom: 1px solid #eee;"></div>';
    }

    // Place bookings
    foreach ($bookings as $booking) {
        $start = DateTime::createFromFormat('H:i:s', $booking->start_time);
        $end = DateTime::createFromFormat('H:i:s', $booking->end_time);

        $start_minutes = ($start->format('H') * 60) + $start->format('i');
        $end_minutes = ($end->format('H') * 60) + $end->format('i');
        $day_start_minutes = $start_hour * 60;

        // Ensure booking is within display window
        if ($start_minutes >= ($end_hour * 60 + 60) || $end_minutes <= ($start_hour * 60)) {
            continue;
        }

        $hour_height = 60; // Use the actual row height

        $start_minutes = ($start->format('H') * 60) + $start->format('i');
        $end_minutes = ($end->format('H') * 60) + $end->format('i');
        $day_start_minutes = $start_hour * 60;
        
        // Clamp to visible range
        $start_minutes = max($start_minutes, $day_start_minutes);
        $end_minutes = min($end_minutes, $end_hour * 60); 
        
        // Correct top position
        $top = (($start_minutes - $day_start_minutes) / 60) * $hour_height;
        
        // Correct height
        $height = max(20, (($end_minutes - $start_minutes) / 60) * $hour_height);


        // Reduced width for booking area (you can change the `width` here)
        $booking_width = 'calc(10% - 20px)'; // Adjust width, or specify a fixed width like '200px'

        echo '<div style="
            position: absolute;
            top: ' . $top . 'px;
            left: 10px; /* Left padding */
            width: ' . $booking_width . ';
            height: ' . $height . 'px;
            background-color: ' . esc_attr($booking->color) . ';
            color: #fff;
            padding: 8px;
            border-radius: 5px;
            font-size: 13px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;">
                <strong>' . esc_html($booking->customer_name) . '</strong><br>
                <small>' . esc_html(date('g:i A', strtotime($booking->start_time))) . ' - ' . esc_html(date('g:i A', strtotime($booking->end_time))) . '</small><br>
                <span style="font-size: 12px;">' . esc_html(ucfirst($booking->booking_type)) . '</span>
            </div>';
    }

    echo '</div>'; // Grid
    echo '</div>'; // Outer container
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







