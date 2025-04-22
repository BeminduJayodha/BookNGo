<?php   

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
        payment_slip VARCHAR(255).,
        payment_status VARCHAR(20) DEFAULT 'Pending',  // Added payment_status column
        reminder_count INT DEFAULT 0,
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
        is_restricted TINYINT(1) DEFAULT 0,
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
    // Top-level menu (sidebar label)
    add_menu_page(
        'Booking Calendar',      // Page title
        'LMS',     // Top-level menu label (change this!)
        'edit_pages',
        'booking-calendar',
        'booking_calendar_page',
        'dashicons-calendar-alt'
    );

    // First submenu — same slug as main menu to override the auto-generated one
    add_submenu_page(
        'booking-calendar',
        'Booking Calendar',        // Page title
        'Booking Calendar',        // Submenu label (what you want to display)
        'edit_pages',
        'booking-calendar',
        'booking_calendar_page'
    );

    // Other submenus
    add_submenu_page('booking-calendar', 'Customer Registration', 'Customer Registration', 'edit_pages', 'customer-registration', 'customer_registration_page');
    add_submenu_page('booking-calendar', 'Customer List', 'Customer List', 'edit_pages', 'customer-list', 'customer_list_page');
    add_submenu_page(null, 'Customer Edit', null, 'edit_pages', 'customer-edit', 'customer_edit_page');
    add_submenu_page('booking-calendar', 'View Invoice', 'View Invoice', 'edit_pages', 'view-invoice', 'display_invoice_page');
    add_submenu_page('booking-calendar', 'View Payment', 'View Payment', 'edit_pages', 'view-payment', 'display_payment_page');
}
add_action('admin_menu', 'booking_calendar_menu');

function restrict_dashboard_for_editors() {
    if (current_user_can('editor')) { // Apply only for Editors
        global $menu;
        $allowed_menus = ['booking-calendar']; // Allow only Booking Calendar Plugin

        foreach ($menu as $key => $item) {
            if (!in_array($item[2], $allowed_menus)) {
                unset($menu[$key]); // Remove all other menus
            }
        }
    }
}
add_action('admin_menu', 'restrict_dashboard_for_editors', 999);


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




//function add_invoice_page() {
//    add_submenu_page('booking-calendar', 'View Invoice', 'View Invoice', 'manage_options', 'view-invoice', 'display_invoice_page');
//}
//add_action('admin_menu', 'add_invoice_page');


add_action('admin_enqueue_scripts', 'enqueue_jspdf_script');
function enqueue_jspdf_script() {
    wp_enqueue_script('jspdf', 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js', array(), null, true);
}


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
                    <th style="border: 1px solid #ddd;">Booking Date(s)</th>
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
            echo '<td style="border: 1px solid #ddd;">' . esc_html($booking->start_date) . ' to ' . esc_html($booking->booking_date) . '</td>';
            echo '<td style="border: 1px solid #ddd;">Rs. ' . esc_html(number_format($invoice->amount, 2)) . '</td>';
            echo '<td style="border: 1px solid #ddd;">
        
        <button class="button download-pdf"
            data-invoice-number="' . esc_attr($invoice->invoice_number) . '"
            data-customer-name="' . esc_attr($booking->customer_name) . '"
            data-start-date="' . esc_attr($booking->start_date) . '"
            data-end-date="' . esc_attr($booking->booking_date) . '"
            data-booking-type="' . esc_attr($booking->booking_type) . '"
            data-amount="' . esc_attr(number_format($invoice->amount, 2)) . '">
            View Invoice
        </button>
      </td>';

            echo '</tr>';
        }
    }

    echo '</tbody>
          </table>';
}


add_action('admin_footer', 'add_pdf_download_script');
function add_pdf_download_script() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.download-pdf').forEach(button => {
            button.addEventListener('click', () => {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();

                // Data from button
                const invoiceNumber = button.dataset.invoiceNumber;
                const customerName = button.dataset.customerName;
                const startDate = button.dataset.startDate;
                const endDate = button.dataset.endDate;
                const amount = button.dataset.amount;
                const bookingType = button.dataset.bookingType; // Added booking type
                const today = new Date().toLocaleDateString();

                // INVOICE Title (Top-right corner)
                doc.setFontSize(20);
                doc.setFont("helvetica", "bold");
                doc.text("INVOICE", 200, 20, { align: "right" });

                // Company Name and Generated Date on same line
                let y = 30;
                doc.setFontSize(16);
                doc.setFont("helvetica", "bold");
                doc.text("Lankatronics Pvt Ltd", 20, y);

                doc.setFontSize(11);
                doc.setFont("helvetica", "normal");
                doc.text("Invoice Date: " + today, 200, y, { align: "right" });

                // Company Address
                y += 7;
                doc.text("No. 8, 1/3, Sunethradevi Road,", 20, y);
                y += 6;
                doc.text("Kohuwala, Nugegoda, Sri Lanka", 20, y);
                y += 6;
                doc.text("Phone: 077 5678 000", 20, y);
                y += 6;
                doc.text("Email: info@lankatronics.lk", 20, y);

                // Invoice Number
                y += 15;
                doc.setFontSize(12);
                doc.setFont("helvetica", "normal");
                doc.text("Invoice Number: #" + invoiceNumber, 20, y);

                // Customer Info
                y += 12;
                doc.setFont("helvetica", "bold");
                doc.text("Customer Information", 20, y);
                y += 8;
                doc.setFont("helvetica", "normal");
                doc.text("Name: " + customerName, 20, y);

                // Booking Details Table
                y += 15;
                doc.setFont("helvetica", "bold");
                doc.text("Booking Details", 20, y);

                y += 8;

                // Table Header
                const col1X = 20;
                const col2X = 80;
                const col3X = 140;
                const colWidth = 60;
                doc.setFont("helvetica", "bold");

                // Draw table header with column borders
                doc.rect(col1X, y - 4, colWidth, 10); // Booking Type
                doc.rect(col2X, y - 4, colWidth, 10); // Start to End Date
                doc.rect(col3X, y - 4, colWidth, 10); // Total Amount
                doc.text("Booking Type", col1X + 2, y);
                doc.text("Start to End Date", col2X + 2, y);
                doc.text("Total Amount", col3X + 2, y);

                // No gap: Directly start content from the same Y coordinate after header
                y += 10; // Move down to start the content row directly below header

                // Table Content (single row example)
                doc.setFont("helvetica", "normal");

                // Draw row content directly under the header, no gap
                doc.rect(col1X, y - 4, colWidth, 10); // Booking Type
                doc.rect(col2X, y - 4, colWidth, 10); // Start to End Date
                doc.rect(col3X, y - 4, colWidth, 10); // Total Amount
                doc.text(bookingType, col1X + 2, y);
                doc.text(startDate + " to " + endDate, col2X + 2, y);
                doc.text("Rs. " + amount, col3X + 2, y);

                // Draw line after the content row to separate it
                doc.line(col1X, y + 6, col3X + colWidth, y + 6);

                // Footer
                y += 15; // Add space for footer content
                doc.setFontSize(10);
                doc.text("Thank you for your booking!", 20, y + 5);
                doc.text("Visit us at: www.lankatronics.lk", 20, y + 10);

                // Save PDF
                doc.save(`Invoice_${invoiceNumber}.pdf`);
            });
        });
    });
    </script>
    <?php
}
//function add_payment_page() {
//    add_submenu_page(
//        'booking-calendar',        // Parent menu slug (same as 'booking-calendar' or whatever your parent menu is)
//        'View Payment',            // Page title
//        'View Payment',            // Menu title
//        'manage_options',          // Capability required to access this menu
//        'view-payment',            // Slug for the new submenu page
//        'display_payment_page'     // Function that will display the content of the page
//    );
//}
//add_action('admin_menu', 'add_payment_page');



function display_payment_page() {   
    global $wpdb;

    // Get unique invoice numbers
    $invoice_numbers = $wpdb->get_results("SELECT DISTINCT invoice_number FROM {$wpdb->prefix}booking_invoices");

    if (empty($invoice_numbers)) {
        echo '<h2>No invoices found.</h2>';
        return;
    }

    echo '<h2>Payment Details</h2>';

    echo '<form method="post" enctype="multipart/form-data">';
    echo '<style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: #fff;
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #ddd;
            width: 40%;
            box-shadow: 0px 0px 10px #000;
        }
        .close-modal {
            float: right;
            font-size: 20px;
            cursor: pointer;
        }
        .invoices-table {
            border-collapse: collapse;
    }
    .invoices-table thead th {
        font-weight: bold;
        background-color: #dedede;
        border-right: 1px solid #ccc;
        text-align: center;
    }
    .invoices-table tbody td {
        border-right: 1px solid #ccc;
        border-bottom: 1px solid #eee;
        text-align: center;
        vertical-align: middle;
    }
    .invoices-table thead th:last-child,
    .invoices-table tbody td:last-child {
        border-right: none;
    }
        /* Creative payment status styles */
    .status-badge {
        width: 90px;                 /* Fixed width for all badges */
        display: inline-block;
        text-align: center;
        padding: 6px 0;              /* Same vertical padding */
        border-radius: 5px;
        font-weight: bold;
        color: #fff;
        font-size: 13px;
    }

    .status-paid {
        background-color: #4CAF50; /* Green */
       
    }

    .status-pending {
        background-color: #FF9800; /* Orange */
        
    }
    </style>';
$selected_status = isset($_POST['filter_status']) ? $_POST['filter_status'] : 'all';

$selected_customer = isset($_POST['filter_customer']) ? $_POST['filter_customer'] : 'all';
$customer_names = $wpdb->get_col("SELECT DISTINCT customer_name FROM {$wpdb->prefix}booking_calendar ORDER BY customer_name");

echo '<div style="margin-bottom: 20px; display: flex; align-items: center; gap: 30px;">';

echo '<div>
    <label for="filter_status"><strong>Filter by Payment Status:</strong></label>
    <select name="filter_status" id="filter_status" onchange="this.form.submit()" style="margin-left: 10px;">
        <option value="all"' . selected($selected_status, 'all', false) . '>All</option>
        <option value="Paid"' . selected($selected_status, 'Paid', false) . '>Paid</option>
        <option value="Pending"' . selected($selected_status, 'Pending', false) . '>Pending</option>
    </select>
</div>';

echo '<div>
    <label for="filter_customer"><strong>Customer Name:</strong></label>
    <select name="filter_customer" id="filter_customer" onchange="this.form.submit()" style="margin-left: 10px;">
        <option value="all"' . selected($selected_customer, 'all', false) . '>All</option>';
foreach ($customer_names as $customer_name) {
    echo '<option value="' . esc_attr($customer_name) . '"' . selected($selected_customer, $customer_name, false) . '>' . esc_html($customer_name) . '</option>';
}
echo '</select>
</div>';

echo '</div>';


    echo '<table class="wp-list-table widefat fixed striped invoices-table" cellspacing="0" cellpadding="5" style="width:100%; border: 1px solid #ddd; margin-bottom: 20px;">
        <thead>
            <tr>
                <th>Invoice Number</th>
                <th>Customer Name</th>
                <th>Booking Date(s)</th>
                <th>Amount</th>
                <th>Payment Status</th>
                <th>Payment Slip</th>
            </tr>
        </thead>
        <tbody>';
// Modify the query to include the filter for payment status
$payment_status_condition = ($selected_status !== 'all') ? "AND payment_status = %s" : '';
$invoice_numbers = $wpdb->get_results(
    $wpdb->prepare("SELECT DISTINCT invoice_number FROM {$wpdb->prefix}booking_invoices WHERE 1=1 $payment_status_condition", $selected_status !== 'all' ? $selected_status : null)
);

    // Loop through the unique invoice numbers
foreach ($invoice_numbers as $invoice_number) {
    $invoice = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$wpdb->prefix}booking_invoices WHERE invoice_number = %s LIMIT 1", $invoice_number->invoice_number)
    );

    if ($invoice) {
        // Apply filter for payment status
        $payment_status = isset($invoice->payment_status) ? esc_html($invoice->payment_status) : 'Pending';
        
        // Skip invoices that don't match the selected payment status
        if ($selected_status !== 'all' && $payment_status !== $selected_status) {
            continue;
        }

        $booking = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}booking_calendar WHERE id = {$invoice->booking_id}");

        if ($selected_customer !== 'all' && (!isset($booking->customer_name) || $booking->customer_name !== $selected_customer)) {
            continue;
        }

        if ($booking) {
            echo '<tr>';
            echo '<td>' . esc_html($invoice->invoice_number) . '</td>';
            echo '<td>' . esc_html($booking->customer_name) . '</td>';
            echo '<td>' . esc_html($booking->start_date) . ' to ' . esc_html($booking->booking_date) . '</td>';
            echo '<td>Rs. ' . esc_html(number_format($invoice->amount, 2)) . '</td>';

            $status_class = ($payment_status === 'Paid') ? 'status-paid' : 'status-pending';
            echo '<td><span class="status-badge ' . $status_class . '">' . $payment_status . '</span></td>';

            if (!empty($invoice->payment_slip)) {
                echo '<td><a href="' . esc_url(wp_upload_dir()['baseurl'] . '/' . $invoice->payment_slip) . '" target="_blank"><span class="dashicons dashicons-visibility"></span> View Slip</a></td>';
            } else {
                echo '<td><button type="button" class="button upload-slip" data-id="' . esc_attr($invoice->id) . '">
                    <span class="dashicons dashicons-upload"></span> Upload Slip
                </button></td>';
            }

            echo '</tr>';
        }
    }
}



    echo '</tbody></table></form>';

    // Upload Slip Modal
    echo '<div id="uploadModal" class="modal">
            <div class="modal-content">
                <span class="close-modal">&times;</span>
                <h2>Upload Payment Slip</h2>
                <form id="uploadSlipForm" method="post" enctype="multipart/form-data">
                    <input type="hidden" id="invoice_id" name="invoice_id">
                    <label for="payment_slip">Select Payment Slip:</label>
                    <input type="file" name="payment_slip" id="payment_slip">
                    <br><br>
                    <button type="submit" name="upload_slip" class="button button-primary">Submit</button>
                </form>
            </div>
        </div>';

    handle_payment_slip_upload($invoices);

    echo '<script>
        document.addEventListener("DOMContentLoaded", function () {
            var modal = document.getElementById("uploadModal");
            var closeModal = document.querySelector(".close-modal");
            var uploadButtons = document.querySelectorAll(".upload-slip");

            uploadButtons.forEach(button => {
                button.addEventListener("click", function () {
                    document.getElementById("invoice_id").value = this.dataset.id;
                    modal.style.display = "block";
                });
            });

            closeModal.addEventListener("click", function () {
                modal.style.display = "none";
            });

            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = "none";
                }
            };
        });
    </script>';
}




function handle_payment_slip_upload() {  
    global $wpdb;

    // Check if the form is submitted
    if (isset($_POST['upload_slip']) && isset($_FILES['payment_slip'])) {
        $invoice_id = intval($_POST['invoice_id']); // Get invoice ID
        $file = $_FILES['payment_slip']; // Get uploaded file

        // Check if a file was uploaded
        if (!empty($file['name'])) {
            // Handle file upload
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');

            $upload_overrides = array('test_form' => false);
            $movefile = wp_handle_upload($file, $upload_overrides);

            if ($movefile && !isset($movefile['error'])) {
                $file_url = str_replace(wp_upload_dir()['baseurl'] . '/', '', $movefile['url']);

                // Retrieve the invoice_number associated with the uploaded invoice
                $invoice_number = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT invoice_number FROM {$wpdb->prefix}booking_invoices WHERE id = %d",
                        $invoice_id
                    )
                );

                // If an invoice_number is found, update all related invoices' payment_status to 'Paid'
                if ($invoice_number) {
                    $wpdb->update(
                        "{$wpdb->prefix}booking_invoices",
                        array(
                            'payment_slip' => $file_url,
                            'payment_status' => 'Paid'  // Update payment status to Paid
                        ),
                        array('invoice_number' => $invoice_number)
                    );
                }

                // Retrieve customer email from the invoice
                $customer_email = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT customer_email FROM {$wpdb->prefix}booking_customers 
                         WHERE customer_email = (SELECT customer_email FROM {$wpdb->prefix}booking_invoices WHERE id = %d LIMIT 1) 
                         LIMIT 1",
                        $invoice_id
                    )
                );

                // If customer exists, reset restriction
                if ($customer_email) {
                    $wpdb->update(
                        "{$wpdb->prefix}booking_customers",
                        array('is_restricted' => 0), // Reset restriction
                        array('customer_email' => $customer_email)
                    );
                }

                // Redirect to the same page to show the updated status
                wp_redirect($_SERVER['REQUEST_URI']);
                exit;
            } else {
                echo '<div class="notice notice-error"><p>Error uploading payment slip: ' . esc_html($movefile['error']) . '</p></div>';
            }
        }
    }
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





function reset_invoice_counter_on_activation() {
    update_option('invoice_counter', 1); // Reset the invoice counter to 1
}
register_activation_hook(__FILE__, 'reset_invoice_counter_on_activation');



function save_booking() {
    global $wpdb;

    // Get data from the AJAX request
    $customer_name = sanitize_text_field($_POST['customer_name']);
    $start_time = sanitize_text_field($_POST['start_time']);
    $end_time = sanitize_text_field($_POST['end_time']);
    $start_date = sanitize_text_field($_POST['start_date']); 
    $end_date = sanitize_text_field($_POST['end_date']);
    $booking_type = sanitize_text_field($_POST['booking_type']);

    // Get customer email from wp_booking_customers table
    $customer_email = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT customer_email FROM {$wpdb->prefix}booking_customers WHERE customer_name = %s LIMIT 1",
            $customer_name
        )
    );

    // Check if the customer is restricted
    $is_restricted = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT is_restricted FROM {$wpdb->prefix}booking_customers WHERE customer_email = %s LIMIT 1",
            $customer_email
        )
    );

    if ($is_restricted == 1) {
        wp_send_json_error(['message' => 'Booking is restricted for this customer due to unpaid invoices.']);
        return;
    }

    // Convert dates to DateTime objects
    $start_date_obj = new DateTime($start_date);
    $end_date_obj = new DateTime($end_date);

    // Generate a random color
    $rand = rand(0, 0xFFFFFF);
    $r = ($rand >> 16) & 0xFF;
    $g = ($rand >> 8) & 0xFF;
    $b = $rand & 0xFF;

    // Calculate brightness
    $brightness = ($r * 0.299 + $g * 0.587 + $b * 0.114);

    // If too bright, darken it
    if ($brightness > 180) {
        $r = intval($r * 0.6);
        $g = intval($g * 0.6);
        $b = intval($b * 0.6);
    }

    $color = sprintf("#%02X%02X%02X", $r, $g, $b);

    $booking_dates = []; // Track all the booking dates
    $current_date = clone $start_date_obj;

    // Insert each booking date and store their booking_ids
    $booking_ids = [];

    while ($current_date <= $end_date_obj) {
        $booking_date = $current_date->format('Y-m-d');

        // Check for existing booking conflict
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
            wp_send_json_error(['message' => 'Time slot is already booked for this date: ' . $booking_date]);
            return;
        }

        // Insert booking
        $wpdb->insert(
            $wpdb->prefix . 'booking_calendar',
            array(
                'customer_name' => $customer_name,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'booking_date' => $booking_date,
                'start_date' => $start_date_obj->format('Y-m-d'),
                'end_date' => $end_date_obj->format('Y-m-d'),
                'color' => $color,
                'booking_type' => $booking_type
            )
        );

        // Collect booking date and booking_id
        $booking_ids[] = $wpdb->insert_id;
        $booking_dates[] = $booking_date;

        $current_date->modify('+1 week');
    }

    $invoice_counter = get_option('invoice_counter', 1); // Default to 1

    // Group booking dates by month
    $monthly_bookings = [];
    foreach ($booking_dates as $date) {
        $month_key = date('Y-m', strtotime($date));
        if (!isset($monthly_bookings[$month_key])) {
            $monthly_bookings[$month_key] = [];
        }
        $monthly_bookings[$month_key][] = $date;
    }

    $invoice_urls = [];
    $all_invoice_numbers = [];
    $total_amount = 0;

    // Counter for delay in seconds (2-minute delay between each email)
    $delay_counter = 0;

    foreach ($monthly_bookings as $month => $dates) {
        $count = count($dates);
        $amount = $count * 4000;
        $total_amount += $amount;

        // Generate invoice number
        $invoice_number = 'INV-' . str_pad($invoice_counter, 5, '0', STR_PAD_LEFT);
        $all_invoice_numbers[] = $invoice_number;

        // Insert invoice for each booking_id
        $invoice_sent_at = current_time('mysql'); // Get current time in MySQL format
        foreach ($booking_ids as $booking_id) {
            $wpdb->insert(
                $wpdb->prefix . 'booking_invoices',
                array(
                    'booking_id' => $booking_id,
                    'invoice_number' => $invoice_number,
                    'amount' => $amount,
                    'invoice_sent_at' => $invoice_sent_at // Store timestamp when invoice is sent
                )
            );
        }

        $invoice_id = $wpdb->insert_id;
        $invoice_urls[] = admin_url('admin.php?page=view-invoice&invoice_id=' . $invoice_id);

        $invoice_counter++;

        // Send invoice emails and schedule reminder checks
        if ($customer_email && $delay_counter == 0) {
            send_invoice_email($customer_email, $invoice_number, $amount, $invoice_urls, $customer_name, $dates);

            // Schedule reminder email 3 minutes after the first invoice email
            wp_schedule_single_event(time() + 3 * 60, 'check_and_send_reminder_email', array($customer_email, $invoice_number, $invoice_url, $customer_name, $booking_type, $start_date, $end_date, (string)$amount));
        }

        // Schedule subsequent invoice emails with a delay
        if ($customer_email && $delay_counter > 0) {
            wp_schedule_single_event(time() + $delay_counter, 'send_subsequent_invoice_email', array($customer_email, $invoice_number, $amount, $invoice_urls, $customer_name, $dates));

            // Schedule reminder email 3 minutes after the subsequent invoice email is sent
            wp_schedule_single_event(time() + $delay_counter + 3 * 60, 'check_and_send_reminder_email', array($customer_email, $invoice_number, $invoice_url, $customer_name, $booking_type, $start_date, $end_date, (string)$amount));
        }

        // Increment delay by 2 minutes (120 seconds) for each subsequent email
        $delay_counter += 2 * 60; // 2 minutes (120 seconds)
    }

    // Update the invoice counter in options
    update_option('invoice_counter', $invoice_counter);

    $redirect_url = 'https://designhouse.lk/wp-admin/admin.php?page=view-invoice';

    wp_send_json_success([
        'message' => 'Booking saved successfully!',
        'invoice_urls' => $invoice_urls,
        'redirect_url' => $redirect_url
    ]);
}

add_action('wp_ajax_save_booking', 'save_booking');




// Send invoice email function
// Include the FPDF library
require_once( plugin_dir_path( __FILE__ ) . 'fpdf/fpdf.php'); // Correct path to fpdf library

function send_invoice_email($customer_email, $invoice_number, $amount, $invoice_urls, $customer_name, $dates) {
    $pdf = new FPDF();
    $pdf->AddPage();
    
    // Invoice Title (Top-right corner)
    $pdf->SetFont('Helvetica', 'B', 20);
    $pdf->SetXY(160, 10);
    $pdf->Cell(40, 10, 'INVOICE');

    // Company Name
    $pdf->SetXY(20, 30);
    $pdf->SetFont('Helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Lankatronics Pvt Ltd', 0, 1);

    // Invoice Date (Top-right)
    $today = date('Y-m-d');
    $pdf->SetFont('Helvetica', '', 11);
    $pdf->SetXY(150, 30);
    $pdf->Cell(0, 10, 'Invoice Date: ' . $today, 0, 1, 'R');

    // Company Address
    $pdf->SetFont('Helvetica', '', 11);
    $pdf->SetXY(20, 45);
    $pdf->MultiCell(0, 6, "No. 8, 1/3, Sunethradevi Road,\nKohuwala, Nugegoda, Sri Lanka\nPhone: 077 5678 000\nEmail: info@lankatronics.lk", 0);

    // Invoice Number
    $pdf->SetXY(20, 75);
    $pdf->SetFont('Helvetica', '', 12);
    $pdf->Cell(0, 10, 'Invoice Number: #' . $invoice_number, 0, 1);

    // Customer Info
    $pdf->SetXY(20, 100); // Set position for title
    $pdf->SetFont('Helvetica', 'B', 12);
    $pdf->Cell(50, 10, 'Customer Information', 0, 1); // Define a fixed width (50)
    
    $pdf->SetXY(20, 110); // Set position for the next line
    $pdf->SetFont('Helvetica', '', 12);
    $pdf->Cell(50, 10, 'Name: ' . $customer_name, 0, 1); // Use the same fixed width


    // Booking Details Table
    $pdf->Ln(8);
    $pdf->SetFont('Helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Booking Details', 0, 1);
    
    // Table Header
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetFillColor(200, 200, 200);
    $pdf->Cell(60, 10, 'Booking Type', 1, 0, 'C', true);
    $pdf->Cell(60, 10, 'Start to End Date', 1, 0, 'C', true);
    $pdf->Cell(60, 10, 'Total Amount', 1, 1, 'C', true);

    // Table Content (Dynamic Row)
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(60, 10, 'Class Rent', 1, 0, 'C');
    $pdf->Cell(60, 10, reset($dates) . ' to ' . end($dates), 1, 0, 'C');
    $pdf->Cell(60, 10, 'Rs. ' . number_format((float)$amount, 2), 1, 1, 'C');

    // Footer
    $pdf->Ln(15);
    $pdf->SetFont('Helvetica', 'I', 10);
    $pdf->Cell(0, 10, 'Thank you for your booking!', 0, 1, 'C');
    $pdf->Cell(0, 10, 'Visit us at: www.lankatronics.lk', 0, 1, 'C');

    // Save PDF and Send Email
    $upload_dir = wp_upload_dir();
    $temp_file = $upload_dir['path'] . '/Invoice-' . $invoice_number . '.pdf';
    $pdf->Output('F', $temp_file);

    // Email
    $subject = 'Your Booking Invoice – ' . date('F Y', strtotime(reset($dates)));
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
            .email-container { background-color: #ffffff; padding: 20px; border-radius: 8px; }
            .email-header { font-size: 24px; color: #333; }
            .email-body { font-size: 16px; color: #555; }
            .email-footer { font-size: 14px; color: #777; text-align: center; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='email-header'>Hi $customer_name,</div>
            <div class='email-body'>
                <p>Thank you for your booking.</p>
                <p><strong>Invoice Number:</strong> $invoice_number</p>
                <p><strong>Amount:</strong> Rs. $amount</p>
                <p><strong>Booking Period:</strong> " . reset($dates) . " to " . end($dates) . "</p>
                <p>You can download your invoice by clicking the attachment below:</p>
            </div>
            <div class='email-footer'>Best regards,<br>Makerspace Team</div>
        </div>
    </body>
    </html>";

    $headers = array('Content-Type: text/html; charset=UTF-8');
    wp_mail($customer_email, $subject, $message, $headers, array($temp_file));

    unlink($temp_file);
}



function check_and_send_reminder_email($customer_email, $invoice_number, $invoice_url, $customer_name, $booking_type, $start_date, $end_date, $amount) {
    global $wpdb;

    // Get payment status for this invoice
    $payment_status = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT payment_status FROM {$wpdb->prefix}booking_invoices WHERE invoice_number = %s LIMIT 1",
            $invoice_number
        )
    );

    // Stop if invoice is paid
    if ($payment_status === 'Paid') {
        return;
    }

    // Get current reminder count
    $reminder_count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT reminder_count FROM {$wpdb->prefix}booking_invoices WHERE invoice_number = %s LIMIT 1",
            $invoice_number
        )
    );

    require_once(plugin_dir_path(__FILE__) . 'fpdf/fpdf.php');
    $pdf = new FPDF();
    $pdf->AddPage();

    // Invoice Title (Top-right)
    $pdf->SetFont('Helvetica', 'B', 20);
    $pdf->SetXY(160, 10);
    $pdf->Cell(40, 10, 'INVOICE');

    // Company Name
    $pdf->SetXY(20, 30);
    $pdf->SetFont('Helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Lankatronics Pvt Ltd', 0, 1);

    // Invoice Date (Top-right)
    $today = date('Y-m-d');
    $pdf->SetFont('Helvetica', '', 11);
    $pdf->SetXY(150, 30);
    $pdf->Cell(0, 10, 'Invoice Date: ' . $today, 0, 1, 'R');

    // Company Address
    $pdf->SetFont('Helvetica', '', 11);
    $pdf->SetXY(20, 45);
    $pdf->MultiCell(0, 6, "No. 8, 1/3, Sunethradevi Road,\nKohuwala, Nugegoda, Sri Lanka\nPhone: 077 5678 000\nEmail: info@lankatronics.lk", 0);

    // Invoice Number
    $pdf->SetXY(20, 75);
    $pdf->SetFont('Helvetica', '', 12);
    $pdf->Cell(0, 10, 'Invoice Number: #' . $invoice_number, 0, 1);

    // Customer Info
    $pdf->SetXY(20, 100);
    $pdf->SetFont('Helvetica', 'B', 12);
    $pdf->Cell(50, 10, 'Customer Information', 0, 1);
    $pdf->SetXY(20, 110);
    $pdf->SetFont('Helvetica', '', 12);
    $pdf->Cell(50, 10, 'Name: ' . $customer_name, 0, 1);

    // Booking Details Table
    $pdf->Ln(8);
    $pdf->SetFont('Helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Booking Details', 0, 1);

    // Table Header
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetFillColor(200, 200, 200);
    $pdf->Cell(60, 10, 'Booking Type', 1, 0, 'C', true);
    $pdf->Cell(60, 10, 'Start to End Date', 1, 0, 'C', true);
    $pdf->Cell(60, 10, 'Total Amount', 1, 1, 'C', true);

    // Table Content (Dynamic Row)
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(60, 10, $booking_type, 1, 0, 'C');
    $pdf->Cell(60, 10, $start_date . ' to ' . $end_date, 1, 0, 'C');
    $pdf->Cell(60, 10, 'Rs. ' . $amount, 1, 1, 'C');

    // Footer
    $pdf->Ln(15);
    $pdf->SetFont('Helvetica', 'I', 10);
    $pdf->Cell(0, 10, 'Thank you for your booking!', 0, 1, 'C');
    $pdf->Cell(0, 10, 'Visit us at: www.lankatronics.lk', 0, 1, 'C');

    // Save PDF and Send Email
    $upload_dir = wp_upload_dir();
    $temp_file = $upload_dir['path'] . '/Invoice-' . $invoice_number . '.pdf';
    $pdf->Output('F', $temp_file);

    // Email Content
    $subject = 'Reminder: Your Booking Invoice – ' . $invoice_number;
    $message = "
    <html>
    <body>
        <p>Dear $customer_name,</p>
        <p>This is reminder #" . ($reminder_count + 1) . " for your booking invoice.</p>
        <p><strong>Invoice Number:</strong> $invoice_number</p>
        <p><a href='$invoice_url'>View Your Invoice</a></p>
        <p>Please make the payment to avoid booking restrictions.</p>
        <p>Best regards,<br>Makerspace Team</p>
    </body>
    </html>";

    $headers = array('Content-Type: text/html; charset=UTF-8');
    $attachments = array($temp_file);
    $sent = wp_mail($customer_email, $subject, $message, $headers, $attachments);

    // Delete the file after sending
    unlink($temp_file);

    // Log error if email fails
    if (!$sent) {
        error_log("Reminder email failed to send to $customer_email.");
    }

    // Increment the reminder count
    $wpdb->update(
        $wpdb->prefix . 'booking_invoices',
        array('reminder_count' => $reminder_count + 1),
        array('invoice_number' => $invoice_number)
    );

    // Restrict the customer after the 3rd reminder
    if ($reminder_count + 1 >= 3) {
        $wpdb->update(
            $wpdb->prefix . 'booking_customers',
            array('is_restricted' => 1),
            array('customer_email' => $customer_email)
        );
        return;
    }

    // Schedule next reminder if less than 3 have been sent
    if ($reminder_count + 1 < 3 && !wp_next_scheduled('check_and_send_reminder_email', array($customer_email, $invoice_number, $invoice_url, $customer_name, $booking_type, $start_date, $end_date, $amount))) {
        wp_schedule_single_event(time() + 3 * 60, 'check_and_send_reminder_email', array($customer_email, $invoice_number, $invoice_url, $customer_name, $booking_type, $start_date, $end_date, $amount));
    }
}

// Hook the function properly
add_action('check_and_send_reminder_email', 'check_and_send_reminder_email', 10, 8);





// Send reminder email function
function send_reminder_email($customer_email, $invoice_number, $invoice_url) {
    $subject = 'Reminder: Your Booking Invoice – ' . $invoice_number;
    $message = "
    <html>
    <head>
        <style>
            body {
                font-family: Arial, sans-serif;
                background-color: #f4f4f4;
                padding: 20px;
            }
            .email-container {
                background-color: #ffffff;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            }
            .email-header {
                font-size: 24px;
                color: #333333;
                margin-bottom: 20px;
            }
            .email-body {
                font-size: 16px;
                color: #555555;
                margin-bottom: 20px;
            }
            .email-footer {
                font-size: 14px;
                color: #777777;
                text-align: center;
                margin-top: 20px;
            }
            .invoice-link {
                font-size: 16px;
                color: #007bff;
                text-decoration: none;
            }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='email-header'>Hi,</div>
            <div class='email-body'>
                <p>This is a reminder for your booking invoice.</p>
                <p><strong>Invoice Number:</strong> $invoice_number</p>
                <p>You can view your invoice by clicking the link below:</p>
                <p><a href='$invoice_url' class='invoice-link'>View Your Invoice</a></p>
            </div>
            <div class='email-footer'>
                <p>Best regards,<br>Makerspace Team</p>
            </div>
        </div>
    </body>
    </html>";

    $headers = array('Content-Type: text/html; charset=UTF-8');
    wp_mail($customer_email, $subject, $message, $headers);
}

// Hook to ensure the scheduled event runs
add_action('send_reminder_email', 'send_reminder_email', 10, 3);
// Function to send subsequent invoice emails
function send_subsequent_invoice_email($customer_email, $invoice_number, $amount, $invoice_urls, $customer_name, $dates) {
    // You can add your logic here to send the invoice email (similar to your first email)
    $formatted_start = reset($dates); // First date of the month
    $formatted_end = end($dates);     // Last date of the month

    $subject = 'Your Booking Invoice – ' . date('F Y', strtotime($formatted_start));
    $message = "
    <html>
    <head>
        <style>
            body {
                font-family: Arial, sans-serif;
                background-color: #f4f4f4;
                padding: 20px;
            }
            .email-container {
                background-color: #ffffff;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            }
            .email-header {
                font-size: 24px;
                color: #333333;
                margin-bottom: 20px;
            }
            .email-body {
                font-size: 16px;
                color: #555555;
                margin-bottom: 20px;
            }
            .email-footer {
                font-size: 14px;
                color: #777777;
                text-align: center;
                margin-top: 20px;
            }
            .invoice-link {
                font-size: 16px;
                color: #007bff;
                text-decoration: none;
            }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='email-header'>Hi $customer_name,</div>
            <div class='email-body'>
                <p>Thank you for your booking.</p>
                <p><strong>Invoice Number:</strong> $invoice_number</p>
                <p><strong>Amount:</strong> Rs. $amount</p>
                <p><strong>Booking Period:</strong> $formatted_start to $formatted_end</p>
                <p>You can view your invoice by clicking the link below:</p>
                <p><a href='$invoice_urls[0]' class='invoice-link'>View Your Invoice</a></p>
            </div>
            <div class='email-footer'>
                <p>Best regards,<br>Makerspace Team</p>
            </div>
        </div>
    </body>
    </html>";

    $headers = array('Content-Type: text/html; charset=UTF-8');
    wp_mail($customer_email, $subject, $message, $headers);
}

// Hook to send subsequent invoice emails
add_action('send_subsequent_invoice_email', 'send_subsequent_invoice_email', 10, 6);


// Hook to detect admin login and logout
//add_action('wp_login', 'send_invoice_on_admin_login', 10, 2);
//add_action('wp_logout', 'store_admin_logout_time'); // Hook the logout time storage function
//
//// Function to store admin logout time
//function store_admin_logout_time() {
//    // Only run for administrators
//    if (current_user_can('administrator')) {
//        // Get the current timestamp
//        $logout_time = current_time('timestamp');
//        
//        // Store the logout time in the options table
//        $result = update_option('admin_last_logout_time', $logout_time);
//        
//        // Log the result to ensure it's working
//        if ($result) {
//            error_log('Admin logout time saved: ' . $logout_time); // Check if it was saved successfully
//        } else {
//            error_log('Failed to save admin logout time'); // Log failure
//        }
//    }
//}
//
//// Function to send emails after admin login
//function send_invoice_on_admin_login($user_login, $user) {
//    if ($user->has_cap('administrator')) { // Check if the logged-in user is an admin
//        $last_logout_time = get_option('admin_last_logout_time');
//        $current_time = time();
//        
//        // Send email only if last logout time is different from current time
//        if ($last_logout_time && ($current_time - $last_logout_time) > 60) { // Ensure emails are sent once after logout-login cycle
//            // Schedule the email to be sent 5 minutes after login
//            wp_schedule_single_event(time() + 300, 'send_scheduled_email_event');
//        }
//
//        // Update the last logout time for the next login cycle
//        update_option('admin_last_logout_time', $current_time);
//    }
//}
//
//// Function to handle the email sending event
//function send_scheduled_email() {
//    send_booking_emails_to_customers();
//    error_log('Scheduled email sent after 5 minutes.');
//}
//add_action('send_scheduled_email_event', 'send_scheduled_email');
//
//// Function to handle email sending to customers
//function send_booking_emails_to_customers() {
//    global $wpdb;
//
//    // Get all bookings (you can modify the query if you want to limit to specific months or users)
//    $bookings = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}booking_calendar");
//
//    // Group bookings by customer
//    $customers_bookings = [];
//    foreach ($bookings as $booking) {
//        $customer_email = $wpdb->get_var(
//            $wpdb->prepare(
//                "SELECT customer_email FROM {$wpdb->prefix}booking_customers WHERE customer_name = %s LIMIT 1",
//                $booking->customer_name
//            )
//        );
//
//        if ($customer_email) {
//            $month = date('Y-m', strtotime($booking->booking_date));
//            $customers_bookings[$customer_email][$month][] = $booking;
//        }
//    }
//
//    // Send email for each customer with their monthly booking details
//    foreach ($customers_bookings as $customer_email => $monthly_bookings) {
//        foreach ($monthly_bookings as $month => $bookings) {
//            $invoice_url = ''; // Generate your invoice URL here
//            $total_amount = count($bookings) * 4000; // Calculate amount based on the number of bookings
//
//            // Email content
//            $subject = 'Your Booking Invoice Reminder for ' . date('F Y', strtotime($month));
//            $message = "
//            <html>
//            <head>
//                <style>
//                    body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
//                    .email-container { background-color: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); }
//                    .email-header { font-size: 24px; color: #333333; margin-bottom: 20px; }
//                    .email-body { font-size: 16px; color: #555555; margin-bottom: 20px; }
//                    .invoice-link { font-size: 16px; color: #007bff; text-decoration: none; }
//                </style>
//            </head>
//            <body>
//                <div class='email-container'>
//                    <div class='email-header'>Hi,</div>
//                    <div class='email-body'>
//                        <p>Thank you for your bookings. Please find your invoice for the bookings made in " . date('F Y', strtotime($month)) . ":</p>
//                        <p><strong>Total Amount: Rs. $total_amount</strong></p>
//                        <p>For your convenience, here is your invoice: <a href='$invoice_url' class='invoice-link'>View Your Invoice</a></p>
//                    </div>
//                    <div class='email-footer'>
//                        <p>Best regards,<br>Makerspace</p>
//                    </div>
//                </div>
//            </body>
//            </html>";
//
//            $headers = array('Content-Type: text/html; charset=UTF-8');
//            wp_mail($customer_email, $subject, $message, $headers);
//        }
//    }
//}








function handle_delete_booking() {
    // Check if the booking_id is set and is a valid number
    if (isset($_POST['booking_id']) && is_numeric($_POST['booking_id'])) {
        global $wpdb;
        $booking_id = intval($_POST['booking_id']);
        
        // Try to delete the booking from wp_booking_calendar
        $result = $wpdb->delete(
            'wp_booking_calendar', 
            array('id' => $booking_id), 
            array('%d') // Format for booking_id as integer
        );

        // Check if the deletion was successful
        if ($result !== false) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error deleting booking']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid booking ID']);
    }

    wp_die(); // Terminate the request and send the response
}
add_action("wp_ajax_delete_booking", "handle_delete_booking");
add_action("wp_ajax_nopriv_delete_booking", "handle_delete_booking");



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

    
        // Query the database to get payment statuses
    $payment_statuses = $wpdb->get_results("SELECT booking_id, payment_status FROM wp_booking_invoices");
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

        // Create a structure to store payment statuses by booking_id
    $payment_status_by_booking_id = [];
    foreach ($payment_statuses as $status) {
        $payment_status_by_booking_id[$status->booking_id] = $status->payment_status;
    }
    // Include the JavaScript and CSS for the popup
    echo '
<script>
function showBookingPopup(customerName, startTime, endTime, bookingType, paymentStatus, bookingId) {
    var bookingIdElement = document.getElementById("editBookingId");
    if (!bookingIdElement) {
        console.error("editBookingId element not found");
        return; // Exit the function if the element doesnt exist
    }

    document.getElementById("bookingPopup").style.display = "block";

    // Set values in non-editable view mode (display booking details)
    document.getElementById("popupCustomerName").innerText = customerName;
    document.getElementById("popupStartTime").innerText = startTime;
    document.getElementById("popupEndTime").innerText = endTime;
    document.getElementById("popupBookingType").innerText = bookingType;
    document.getElementById("popupPaymentStatus").innerText = paymentStatus;

    // Set values in input fields (hidden by default)
    bookingIdElement.value = bookingId;

    // Show booking details by default
    toggleEditMode(false);
}


function closeBookingPopup() {
    document.getElementById("bookingPopup").style.display = "none";
}

function toggleEditMode(editMode) {
    if (editMode) {
        // Hide the booking details and headings when editing
        document.getElementById("popupCustomerName").style.display = "none";
        document.getElementById("popupStartTime").style.display = "none";
        document.getElementById("popupEndTime").style.display = "none";
        document.getElementById("popupBookingType").style.display = "none";
        document.getElementById("popupPaymentStatus").style.display = "none";

        // Hide the corresponding headings
        document.getElementById("customerHeading").style.display = "none";
        document.getElementById("startTimeHeading").style.display = "none";
        document.getElementById("endTimeHeading").style.display = "none";
        document.getElementById("bookingTypeHeading").style.display = "none";
        document.getElementById("paymentStatusHeading").style.display = "none";
        document.querySelectorAll(".popup-break").forEach(br => br.style.display = "none");
                // Hide the "Booking Details" heading
        document.querySelector("h3").style.display = "none"

        // Show the message and the "Delete" button
        document.getElementById("editMessage").style.display = "inline";
        document.getElementById("deleteButton").style.display = "inline";

        // Hide edit/save buttons and input fields
        document.getElementById("editButton").style.display = "none";
        document.getElementById("saveButton").style.display = "none";
        document.getElementById("editCustomerName").style.display = "none";
        document.getElementById("editStartTime").style.display = "none";
        document.getElementById("editEndTime").style.display = "none";
        document.getElementById("editBookingType").style.display = "none";
    } else {
        // Show the booking details and headings when not editing
        document.getElementById("popupCustomerName").style.display = "inline";
        document.getElementById("popupStartTime").style.display = "inline";
        document.getElementById("popupEndTime").style.display = "inline";
        document.getElementById("popupBookingType").style.display = "inline";
        document.getElementById("popupPaymentStatus").style.display = "inline";

        // Show the corresponding headings
        document.getElementById("customerHeading").style.display = "inline";
        document.getElementById("startTimeHeading").style.display = "inline";
        document.getElementById("endTimeHeading").style.display = "inline";
        document.getElementById("bookingTypeHeading").style.display = "inline";
        document.getElementById("paymentStatusHeading").style.display = "inline";
        
        document.querySelectorAll(".popup-break").forEach(br => br.style.display = "block");
        // Show the "Booking Details" heading again
        document.querySelector("h3").style.display = "block"; 

        // Hide the message and delete button
        document.getElementById("editMessage").style.display = "none";
        document.getElementById("deleteButton").style.display = "none";

        // Show the edit/save buttons and input fields
        document.getElementById("editButton").style.display = "inline";
        document.getElementById("saveButton").style.display = "none";
        document.getElementById("editCustomerName").style.display = "none";
        document.getElementById("editStartTime").style.display = "none";
        document.getElementById("editEndTime").style.display = "none";
        document.getElementById("editBookingType").style.display = "none";
    }
}

function saveBookingDetails() {
    document.getElementById("popupCustomerName").innerText = document.getElementById("editCustomerName").value;
    document.getElementById("popupStartTime").innerText = document.getElementById("editStartTime").value;
    document.getElementById("popupEndTime").innerText = document.getElementById("editEndTime").value;
    document.getElementById("popupBookingType").innerText = document.getElementById("editBookingType").value;

    toggleEditMode(false); // Show updated details and hide edit inputs
}

function deleteBooking() {
    if (confirm("Are you sure you want to delete this booking?")) {
        var bookingIdElement = document.getElementById("editBookingId");

        if (bookingIdElement) {
            var bookingId = bookingIdElement.value;

            jQuery.ajax({
    url: ajaxurl,  // WordPress AJAX URL
    type: "POST",
    data: {
        action: "delete_booking",  // Custom action name
        booking_id: bookingId     // Pass the booking ID
    },
    success: function(response) {
        try {
            var data = JSON.parse(response); // Parse JSON response
            if (data.status === "success") {
                alert("Booking deleted successfully");
                closeBookingPopup();
                location.reload(); // Reload the page to reflect the changes
            } else {
                alert(data.message || "Error deleting booking");
            }
        } catch (e) {
            console.error("Failed to parse response:", e);
            alert("Error processing the request.");
        }
    },
    error: function() {
        alert("Error processing the request.");
    }
});

        } else {
            alert("Booking ID not found");
        }
    }
}
</script>

<div id="bookingPopup" style="display:none; position:fixed; left:50%; top:50%; transform:translate(-50%, -50%); background-color:white; padding:20px; box-shadow:0px 4px 6px rgba(0,0,0,0.1); border-radius:10px; z-index:1000;">
    <h3>Booking Details</h3>

    <!-- Display headings inline with their values -->
<p id="customerHeading"><strong>Customer:</strong> <span id="popupCustomerName"></span></p><br class="popup-break">
<p id="startTimeHeading"><strong>Start Time:</strong> <span id="popupStartTime"></span></p><br class="popup-break">
<p id="endTimeHeading"><strong>End Time:</strong> <span id="popupEndTime"></span></p><br class="popup-break">
<p id="bookingTypeHeading"><strong>Booking Type:</strong> <span id="popupBookingType"></span></p><br class="popup-break">
<p id="paymentStatusHeading"><strong>Payment Status:</strong> <span id="popupPaymentStatus"></span></p><br class="popup-break">


    <!-- Custom message when the booking status is pending (shown during edit mode) -->
    <p id="editMessage" style="display:none;"><strong>This customers payment status is still pending. You can make a new booking when you delete this slot.</strong></p><br>

    <!-- Hidden input for booking ID -->
    <input type="hidden" id="editBookingId" value="<?php echo $booking->booking_id; ?>">

    <!-- Edit/Delete/Save buttons -->
    <button id="editButton" onclick="toggleEditMode(true)">Edit</button>
    <button id="saveButton" onclick="saveBookingDetails()" style="display:none;">Save</button>

    <!-- Delete button visible in edit mode -->
    <button id="deleteButton" onclick="deleteBooking()" style="display:none; background-color:red;">Delete</button>

    <button onclick="closeBookingPopup()">Close</button>
</div>

<style>
#bookingPopup {
    background: white;
    border: 2px solid #333;
    padding: 20px;
    width: 300px;
    text-align: center;
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    border-radius: 8px;
    z-index: 1001;
}

#bookingPopup button {
    background: #007bff;
    color: white;
    border: none;
    padding: 8px 12px;
    cursor: pointer;
    border-radius: 5px;
    margin: 5px;
}

#bookingPopup button:hover {
    background: #0056b3;
}

</style>';
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

    for ($row = 1; $row <= 6; $row++) { // Assuming 5 weeks per month
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
                
                    $customer_image_url = isset($customer_images[$booking->customer_name]) && !empty($customer_images[$booking->customer_name])
    ? $customer_images[$booking->customer_name]
    : 'https://designhouse.lk/wp-content/uploads/2025/04/sample-300x300.png';

                    $booked_text_color = ($current_cell_date < $current_date) ? '#f0f0f1' : '#edebec'; // Gray for past, white for future/today
                    

$payment_status = isset($payment_status_by_booking_id[$booking->id]) ? $payment_status_by_booking_id[$booking->id] : 'Not found';

$onclick = '';
$booking_date = $booking->booking_date;  // The booking date from your booking object
if (strtolower($payment_status) === 'pending') {
    // Check if the booking date is not in the past
    if ($booking_date >= $current_date) {
        $onclick = 'onclick="showBookingPopup(\'' 
        . esc_js($booking->customer_name) . '\', \'' 
        . esc_js($booking->start_time) . '\', \'' 
        . esc_js($booking->end_time) . '\', \'' 
        . esc_js($booking->booking_type) . '\', \'' 
        . esc_js($payment_status) . '\', \'' 
        . esc_js($booking->id) . '\')"';
    }
}



$booked_time_str .= '<div ' . $onclick . ' 
    style="cursor: ' . ($onclick ? 'pointer' : 'default') . '; position: relative; background-color:' . esc_attr($booking->color) . '; color: ' . $booked_text_color . '; padding: 15px; margin: 5px 0; border-radius: 6px; display: flex; flex-direction: column; justify-content: center;">';


                    
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
                    // Retrieve the payment status using the correct column name for matching
                    $payment_status = isset($payment_status_by_booking_id[$booking->id]) ? $payment_status_by_booking_id[$booking->id] : 'Not found';
                
                    // Display the payment status below the booking type
                    //$booked_time_str .= '<br><small>Status: ' . esc_html($payment_status) . '</small>';
                
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
                        $onclick_event = "onclick=\"if(event.target === this) showBookingModal('{$current_cell_date}', '" . implode(',', $available_slots) . "')\"";
                    } else {
                        $onclick_event = "onclick=\"showBookingModal('{$current_cell_date}', '')\"";
                    }
                    echo '<td class="booking-slot" data-day="' . $day . '" data-date="' . $current_cell_date . '" 
                        style="' . $highlight_border . ' height: 100px; vertical-align: top; width: 14.28%; 
                        text-align: right; padding: 5px;" ' . $onclick_event . '>' . $current_day . $booked_time_str . '</td>';
                } else {
                    // For past dates, don't add the onclick event and don't change any visual style.
                    echo '<td class="booking-slot" data-day="' . $day . '" data-date="' . $current_cell_date . '" 
                        style="' . $highlight_border . ' height: 100px; vertical-align: top; width: 14.28%; 
                        text-align: right; padding: 5px;opacity: 0.3;">' . $current_day . $booked_time_str . '</td>';
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
    <div id="availableSlots" style="margin-bottom: 2px;"></div>
</div>
      

                    <div style="display: flex; justify-content: space-between; gap: 10px;">
                        <div style="flex: 1;">
                            <label for="start_time">Start Time:</label>
                            <input type="time" name="start_time" id="start_time" required min="08:00" max="19:00" style="width: 100%;" step="3600" readonly onkeydown="return false;">
                        </div>
                        <div style="flex: 1;">
                            <label for="end_time">End Time:</label>
                            <input type="time" name="end_time" id="end_time" required min="08:00" max="19:00" style="width: 100%;" step="3600" readonly onkeydown="return false;">
                        </div>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 2px;">
                        <input type="submit" value="Save Booking" style="background-color: #21759b; color: white; padding: 10px 20px; border: none; cursor: pointer; border-radius: 5px;">
                        <button type="button" onclick="closeBookingModal()" style="background-color: #21759b; color: white; padding: 10px 20px; border: none; cursor: pointer; border-radius: 5px;">Close</button>
                    </div>
                </div>
            </form>
        </div>
    </div>';
echo '<script>
    // Function to restrict minutes to always be 00
    function forceHourlyInput(inputElement) {
        const value = inputElement.value;

        // Check if value is valid and contains the minutes part
        if (value) {
            const [hours, minutes] = value.split(":");

            // Ensure minutes are always 00
            if (minutes !== "00") {
                inputElement.value = `${hours}:00`;
            }
        }
    }

    // Add event listeners to ensure the minutes part is non-editable
    document.getElementById("start_time").addEventListener("input", function () {
        forceHourlyInput(this);
    });

    document.getElementById("end_time").addEventListener("input", function () {
        forceHourlyInput(this);
    });

    // Prevent manual input of minutes other than 00
    document.getElementById("start_time").addEventListener("blur", function () {
        forceHourlyInput(this);
    });

    document.getElementById("end_time").addEventListener("blur", function () {
        forceHourlyInput(this);
    });
</script>';
echo '<script>
function checkBookingType() { 
    var bookingSelect = document.getElementById("booking_type");
    var bookingType = bookingSelect.value;

    // Show the start and end date selectors for all booking types
    var teacherDateSelectors = document.getElementById("teacherDateSelectors");
    teacherDateSelectors.style.display = "block";

    var startDate = document.getElementById("start_date");
    var endDate = document.getElementById("end_date");

    // Handle time slot visibility
    var timeSlotContainer = document.getElementById("timeSlotContainer");
    var availableSlots = document.getElementById("availableSlots");
    availableSlots.innerHTML = "<p>No available slots</p>";

    // Handle date and slot visibility based on booking type
    if (bookingType === "Workspace Rent" || bookingType === "Conference Rent") {
        if (timeSlotContainer) {
            timeSlotContainer.style.display = "block";
        }

        // Set the end date field to readonly for Workspace Rent and Conference Rent
        endDate.setAttribute("readonly", "readonly");
        endDate.value = startDate.value;  // Set the end date to the start date

        // Fetch available slots for the selected start date
        if (startDate.value) {
            fetch(ajaxurl + "?action=get_available_workspace_conference_slots&start_date=" + startDate.value)
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById("availableSlots");
                    container.innerHTML = "";

                    if (data.length === 0) {
                        container.innerHTML = "<p>No available slots</p>";
                        return;
                    }

                    data.forEach(day => {
                        const dayBlock = document.createElement("div");
                        dayBlock.style.marginBottom = "20px";
                        const dayLabel = document.createElement("strong");
                        dayLabel.innerHTML = "Available Slots for " + day.date;
                        dayBlock.appendChild(dayLabel);

                        // Create checkboxes for each slot
                        day.slots.forEach(slot => {
                            const checkboxContainer = document.createElement("div");

                            const checkbox = document.createElement("input");
                            checkbox.type = "checkbox";
                            checkbox.name = "time_slot[]"; // Group checkboxes under the same name
                            checkbox.value = slot;  // The time slot value

                            const label = document.createElement("label");
                            label.innerText = slot;

                            checkboxContainer.appendChild(checkbox);
                            checkboxContainer.appendChild(label);
                            dayBlock.appendChild(checkboxContainer);
                            
                            checkbox.addEventListener("change", updateStartEndTime);
                        });

                        container.appendChild(dayBlock);
                    });
                })
                .catch(error => {
                    console.error("Error fetching slots:", error);
                });
        }
    } else if (bookingType === "Class Rent") {
        if (timeSlotContainer) {
            timeSlotContainer.style.display = "block";
        }

        // Remove the readonly attribute for Class Rent
        endDate.removeAttribute("readonly");

        // Fetch available slots for the selected date range (start_date and end_date)
        if (startDate.value && endDate.value) {
            fetch(ajaxurl + "?action=get_available_class_slots&start_date=" + startDate.value + "&end_date=" + endDate.value)
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById("availableSlots");
                    container.innerHTML = "";

                    if (data.length === 0) {
                        container.innerHTML = "<p>No available slots</p>";
                        return;
                    }

                    data.forEach(week => {
                        const weekBlock = document.createElement("div");
                        weekBlock.style.marginBottom = "20px";
                        const weekLabel = document.createElement("strong");
                        weekLabel.innerHTML = "Week of " + week.date;
                        weekBlock.appendChild(weekLabel);

                        // Create checkboxes for each slot
                        week.slots.forEach(slot => {
                            const checkboxContainer = document.createElement("div");

                            const checkbox = document.createElement("input");
                            checkbox.type = "checkbox";
                            checkbox.name = "time_slot[]"; // Group checkboxes under the same name
                            checkbox.value = slot;  // The time slot value

                            const label = document.createElement("label");
                            label.innerText = slot;

                            checkboxContainer.appendChild(checkbox);
                            checkboxContainer.appendChild(label);
                            weekBlock.appendChild(checkboxContainer);
                            
                            checkbox.addEventListener("change", updateStartEndTime);
                        });

                        container.appendChild(weekBlock);
                    });
                })
                .catch(error => {
                    console.error("Error fetching slots:", error);
                });
        }
    } else {
        if (timeSlotContainer) {
            timeSlotContainer.style.display = "none";
        }

        // Reset the readonly state for other booking types
        endDate.removeAttribute("readonly");
    }
}

// Listen for changes to the booking type and update date handling
document.getElementById("booking_type").addEventListener("change", checkBookingType);

// Listen for changes to the end date to update class slots dynamically
document.getElementById("end_date").addEventListener("change", function() {
    var bookingSelect = document.getElementById("booking_type");
    var bookingType = bookingSelect.value;
    var startDate = document.getElementById("start_date");
    var endDate = document.getElementById("end_date");

    if (bookingType === "Class Rent" && startDate.value && endDate.value) {
        fetch(ajaxurl + "?action=get_available_class_slots&start_date=" + startDate.value + "&end_date=" + endDate.value)
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById("availableSlots");
                container.innerHTML = "";

                if (data.slots.length === 0) {
                    container.innerHTML = "<p>No available slots</p>";
                    return;
                }

                const block = document.createElement("div");
                block.style.marginBottom = "20px";
                block.innerHTML = "<strong>Common Available Slots</strong><br>";

                // Create checkboxes for each available slot
                data.slots.forEach(slot => {
                    const checkboxContainer = document.createElement("div");

                    const checkbox = document.createElement("input");
                    checkbox.type = "checkbox";
                    checkbox.name = "time_slot[]";
                    checkbox.value = slot;

                    const label = document.createElement("label");
                    label.innerText = slot;

                    checkboxContainer.appendChild(checkbox);
                    checkboxContainer.appendChild(label);
                    block.appendChild(checkboxContainer);
                    
                    checkbox.addEventListener("change", updateStartEndTime);
                });

                container.appendChild(block);
            })
            .catch(error => {
                console.error("Error fetching slots:", error);
            });
    }
});
</script>';






}
add_action('wp_ajax_get_available_class_slots', 'get_available_class_slots');
add_action('wp_ajax_nopriv_get_available_class_slots', 'get_available_class_slots');

function get_available_class_slots() {
    global $wpdb;

    $start_date = sanitize_text_field($_GET['start_date']);
    $end_date = sanitize_text_field($_GET['end_date']);

    $start_date_obj = new DateTime($start_date);
    $end_date_obj = new DateTime($end_date);

    $common_available_slots = [];
    $first_iteration = true;

    $loop_date = clone $start_date_obj;

    // Loop weekly from start to end date
    while ($loop_date <= $end_date_obj) {
        $current_day = $loop_date->format('Y-m-d');

        // Fetch bookings on this date
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT start_time, end_time FROM wp_booking_calendar WHERE booking_date = %s",
            $current_day
        ));

        $booked_slots = [];
        foreach ($bookings as $b) {
            $booked_slots[] = ['start' => $b->start_time, 'end' => $b->end_time];
        }

        // Generate available hourly slots
        $available_slots = [];
        for ($hour = 8; $hour < 19; $hour++) {
            $slot_start = sprintf('%02d:00', $hour);
            $slot_end = sprintf('%02d:00', $hour + 1);

            $is_conflict = false;
            foreach ($booked_slots as $bs) {
                if (
                    ($slot_start >= $bs['start'] && $slot_start < $bs['end']) ||
                    ($slot_end > $bs['start'] && $slot_end <= $bs['end']) ||
                    ($slot_start <= $bs['start'] && $slot_end >= $bs['end'])
                ) {
                    $is_conflict = true;
                    break;
                }
            }

            if (!$is_conflict) {
                $available_slots[] = "$slot_start - $slot_end";
            }
        }

        if ($first_iteration) {
            $common_available_slots = $available_slots;
            $first_iteration = false;
        } else {
            $common_available_slots = array_intersect($common_available_slots, $available_slots);
        }

        // Move to same weekday in next week
        $loop_date->modify('+1 week');
    }

    wp_send_json([
        'slots' => array_values($common_available_slots)
    ]);
}


// Handle available slots for Workspace Rent and Conference Rent
add_action('wp_ajax_get_available_workspace_conference_slots', 'get_available_workspace_conference_slots');
add_action('wp_ajax_nopriv_get_available_workspace_conference_slots', 'get_available_workspace_conference_slots');

function get_available_workspace_conference_slots() {
    global $wpdb;

    $start_date = sanitize_text_field($_GET['start_date']);
    
    // Debugging: Log the start date to confirm it's being passed correctly
    error_log('Start Date: ' . $start_date);
    
    // Convert to DateTime object
    $start_date_obj = new DateTime($start_date);

    $result = [];

    // Check availability for the selected day
    $current_day = $start_date_obj->format('Y-m-d');
    
    // Debugging: Log the current day we are checking
    error_log('Checking availability for: ' . $current_day);
    
    // Get bookings for this date
    $bookings = $wpdb->get_results($wpdb->prepare(
        "SELECT start_time, end_time FROM wp_booking_calendar WHERE booking_date = %s", 
        $current_day
    ));

    $booked_slots = [];
    foreach ($bookings as $b) {
        $booked_slots[] = ['start' => $b->start_time, 'end' => $b->end_time];
    }

    // Generate available slots for the day
    $available_slots = [];
    for ($hour = 8; $hour < 19; $hour++) {  // Assuming checking from 8 AM to 7 PM
        $slot_start = sprintf('%02d:00:00', $hour);
        $slot_end = sprintf('%02d:00:00', $hour + 1);

        $is_conflict = false;
        foreach ($booked_slots as $bs) {
            if (
                ($slot_start >= $bs['start'] && $slot_start < $bs['end']) ||
                ($slot_end > $bs['start'] && $slot_end <= $bs['end']) ||
                ($slot_start <= $bs['start'] && $slot_end >= $bs['end'])
            ) {
                $is_conflict = true;
                break;
            }
        }

        if (!$is_conflict) {
            $available_slots[] = "$slot_start - $slot_end";
        }
    }

    // If there are available slots, add them to the result
    if (count($available_slots) > 0) {
        $result[] = [
            'date' => $current_day,
            'slots' => $available_slots
        ];
    }

    // Send the result back as a JSON response
    wp_send_json($result);
}


// JavaScript for booking modal handling
function booking_calendar_modal_js() { 
    ?>
    <script type="text/javascript">
        // Function to show the booking modal
function showBookingModal(date, availableSlots) {
    var currentDate = new Date().toISOString().split('T')[0];
    
    if (date < currentDate) {
        alert("Cannot book for past dates.");
        return;
    }

    document.getElementById("bookingDate").value = date;
    document.getElementById("start_date").value = date;

    let slotsContainer = document.getElementById("availableSlots");
    let slotsLabel = slotsContainer.previousElementSibling;

    slotsContainer.innerHTML = "";

    if (availableSlots && availableSlots.trim() !== "") {
        // Show available server-provided slots
        let slotsArray = availableSlots.split(",");
        slotsArray.forEach(slot => {
            let slotElement = document.createElement("div");
            slotElement.style.margin = "5px 0";

            let checkbox = document.createElement("input");
            checkbox.type = "checkbox";
            checkbox.name = "selected_slots[]";
            checkbox.value = slot;
            checkbox.addEventListener('change', updateStartEndTime);

            let label = document.createElement("label");
            label.textContent = slot;

            slotElement.appendChild(checkbox);
            slotElement.appendChild(label);
            slotsContainer.appendChild(slotElement);
        });

        slotsLabel.style.display = "block";
        slotsContainer.style.display = "block";

    } else {
        // No available slots => show default hourly checkboxes
        for (let hour = 8; hour < 19; hour++) {
            let start = `${hour.toString().padStart(2, '0')}:00`;
            let end = `${(hour + 1).toString().padStart(2, '0')}:00`;
            let slot = `${start} - ${end}`;

            let slotElement = document.createElement("div");
            slotElement.style.margin = "5px 0";

            let checkbox = document.createElement("input");
            checkbox.type = "checkbox";
            checkbox.name = "time_slot[]"; // different name for default slots
            checkbox.value = slot;
            checkbox.addEventListener('change', updateStartEndTime);

            let label = document.createElement("label");
            label.textContent = slot;

            slotElement.appendChild(checkbox);
            slotElement.appendChild(label);
            slotsContainer.appendChild(slotElement);
        }

        slotsLabel.style.display = "block";
        slotsContainer.style.display = "block";
    }

    document.getElementById("bookingModal").style.display = "block";
}




        // Function to update start_time and end_time based on selected slots
function updateStartEndTime() {
    // Combine both types of checkboxes
    const allSlots = Array.from(document.querySelectorAll('input[name="time_slot[]"], input[name="selected_slots[]"]'));
    const checkedSlots = allSlots.filter(cb => cb.checked);

    if (checkedSlots.length === 0) {
        document.getElementById("start_time").value = '';
        document.getElementById("end_time").value = '';
        return;
    }

    // Get indexes of all slots and checked slots
    const slotValues = allSlots.map(cb => cb.value);
    const checkedValues = checkedSlots.map(cb => cb.value);

    // Find the first and last checked index
    const firstCheckedIndex = slotValues.indexOf(checkedValues[0]);
    const lastCheckedIndex = slotValues.indexOf(checkedValues[checkedValues.length - 1]);

    // Select all checkboxes in between
    for (let i = firstCheckedIndex; i <= lastCheckedIndex; i++) {
        allSlots[i].checked = true;
    }

    // Recalculate start and end time
    const updatedCheckedSlots = allSlots.slice(firstCheckedIndex, lastCheckedIndex + 1);
    const times = updatedCheckedSlots.map(cb => {
        const [start, end] = cb.value.split(" - ");
        return { start, end };
    });

    document.getElementById("start_time").value = times[0].start;
    document.getElementById("end_time").value = times[times.length - 1].end;
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
                window.location.href = response.data.redirect_url;
            } else {
                // Display the actual error message from the server
                alert(response.data.message);
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







