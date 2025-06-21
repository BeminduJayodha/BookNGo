<?php   

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
function booking_calendar_send_custom_email($to, $subject, $message, $headers = '', $attachments = array()) {
    // Add filters to override default "From" name and email
    add_filter('wp_mail_from_name', 'booking_calendar_custom_mail_from_name');
    add_filter('wp_mail_from', 'booking_calendar_custom_mail_from');

    // Send the email
    wp_mail($to, $subject, $message, $headers, $attachments);

    // Remove the filters afterward to avoid affecting other emails
    remove_filter('wp_mail_from_name', 'booking_calendar_custom_mail_from_name');
    remove_filter('wp_mail_from', 'booking_calendar_custom_mail_from');
}

// Custom sender name
function booking_calendar_custom_mail_from_name($name) {
    return 'Makerspace'; // You can change this
}

// Custom sender email
function booking_calendar_custom_mail_from($email) {
    return 'bookings@designhouse.lk'; // Make sure this email is authenticated on your domain
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
        group_id VARCHAR(64) DEFAULT NULL,
        description TEXT DEFAULT NULL,
        course_fee DECIMAL(10,2) DEFAULT 0,
        PRIMARY KEY (id)
    ) $charset_collate;";

    // Invoice Table
    $sql2 = "CREATE TABLE $invoice_table (
        id INT NOT NULL AUTO_INCREMENT,
        booking_id INT NOT NULL,
        invoice_number VARCHAR(20) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        payment_slip VARCHAR(255),
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
        
        // Allow these menus for Editors
        $allowed_menus = [
            'booking-calendar',        // Your Booking Calendar plugin
            'student-registration'     // Your Student Registration plugin (this slug must match!)
        ];

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
                            
                            <option value="makerspace">Makerspace</option> 
                            <option value="rent">Rent</option> 
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
<script>
    document.addEventListener("DOMContentLoaded", function () {
        const customerTypeSelect = document.getElementById("customer_type");
        const customerEmailInput = document.getElementById("customer_email");
        

        customerTypeSelect.addEventListener("change", function () {
            const selectedType = customerTypeSelect.value;

            // Auto-fill email and phone for "Makerspace" customer type
            if (selectedType === "makerspace") {
                customerEmailInput.value = "sslt.xd@gmail.com";
                customerEmailInput.readOnly = true;
                
            } else {
                // Clear the fields if another customer type is selected
                customerEmailInput.value = "";
                customerEmailInput.readOnly = false;
               
            }
        });

        // Additional form submission logic here...
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

    booking_calendar_send_custom_email($customer_email, $subject, nl2br($message), $headers);

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
        document.querySelectorAll('.download-pdf, .invoice-link').forEach(element => {
element.addEventListener('click', () => {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();

    // Data from element (either button or link)
    const invoiceNumber = element.dataset.invoiceNumber;
    const customerName = element.dataset.customerName;
    const startDate = element.dataset.startDate;
    const endDate = element.dataset.endDate;
    const amount = element.dataset.amount;
    const bookingType = element.dataset.bookingType;
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
        .invoices-table tbody tr:nth-child(odd) {
        background-color: #ffffff;
    }

    .invoices-table tbody tr:nth-child(even) {
        background-color: #f9f9f9;
    }
    .invoices-table tbody tr:hover {
    background-color: #e0f7fa;
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
    .invoices-table th,
.invoices-table td {
    padding-top: 8px !important;
    padding-bottom: 8px !important;
}
    .invoices-table th,
    .invoices-table td {
        vertical-align: middle !important;
    }

    .invoices-table input[type="checkbox"] {
        margin: 0 auto;
        display: block;
    }
    </style>';
$selected_status = isset($_POST['filter_status']) ? $_POST['filter_status'] : 'Pending';
$selected_customer = isset($_POST['filter_customer']) ? $_POST['filter_customer'] : 'all';
$customer_names = $wpdb->get_col("SELECT DISTINCT customer_name FROM {$wpdb->prefix}booking_calendar ORDER BY customer_name");

// First Row: Payment Status & Instructor Name
echo '<div style="margin-bottom: 10px; display: flex; align-items: center; gap: 30px;">';

echo '<div>
    <label for="filter_status"><strong>Payment Status:</strong></label>
    <select name="filter_status" id="filter_status" onchange="this.form.submit()" style="margin-left: 10px;">
        <option value="all"' . selected($selected_status, 'all', false) . '>All</option>
        <option value="Paid"' . selected($selected_status, 'Paid', false) . '>Paid</option>
        <option value="Pending"' . selected($selected_status, 'Pending', false) . '>Pending</option>
    </select>
</div>';

echo '<div>
    <label for="filter_customer"><strong>Instructor Name:</strong></label>
    <select name="filter_customer" id="filter_customer" onchange="this.form.submit()" style="margin-left: 10px;">
        <option value="all"' . selected($selected_customer, 'all', false) . '>All</option>';
foreach ($customer_names as $customer_name) {
    echo '<option value="' . esc_attr($customer_name) . '"' . selected($selected_customer, $customer_name, false) . '>' . esc_html($customer_name) . '</option>';
}
echo '</select>
</div>';

echo '</div>'; // End of first row

// Second Row: Date Range Toggle
echo '<div style="margin-bottom: 10px;">
    <label for="dateRangeToggle" style="margin-right: 10px; cursor: pointer;"><strong>Date Range:</strong></label>
    <button type="button" id="dateRangeToggle" style="cursor:pointer; background:none; border:none; padding:0; margin:0; outline:none;" title="Filter by date range">
        <span class="dashicons dashicons-calendar-alt"></span>
    </button>
</div>';

// Date Range Inputs (initially hidden)
$from_date = isset($_POST['from_date']) && $_POST['from_date'] !== '' ? $_POST['from_date'] : '';
$to_date = isset($_POST['to_date']) && $_POST['to_date'] !== '' ? $_POST['to_date'] : '';

echo '<div id="dateRangeFilters" style="display:none; margin-bottom: 20px;">
    <label for="from_date"><strong>From:</strong></label>
    <input type="date" name="from_date" id="from_date" value="' . esc_attr($from_date) . '" style="margin-right:20px;">
    <label for="to_date"><strong>To:</strong></label>
    <input type="date" name="to_date" id="to_date" value="' . esc_attr($to_date) . '">
    <button type="submit" style="margin-left: 20px; background-color: #2271b1; color: white; border: none; padding: 5px 12px; border-radius: 4px; cursor: pointer;">Filter</button>
</div>';

// JavaScript to toggle date range
echo '
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const toDateInput = document.getElementById("to_date");
        if (!toDateInput.value) {
            const today = new Date().toISOString().split("T")[0];
            toDateInput.value = today;
        }
    });

    document.getElementById("dateRangeToggle").addEventListener("click", function() {
        var filters = document.getElementById("dateRangeFilters");
        filters.style.display = (filters.style.display === "none" || filters.style.display === "") ? "block" : "none";
    });
</script>';



echo '<div style="display: flex; justify-content: flex-end; margin-bottom: 10px;">
    <button type="button" id="downloadCsvBtn" style="background-color: #2271b1; color: white; border: none; padding: 6px 12px; cursor: pointer; border-radius: 4px;">
        Download Selected Orders
    </button>
</div>';


    echo '<table class="wp-list-table widefat fixed striped invoices-table" cellspacing="0" cellpadding="5" style="width:100%; border: 1px solid #ddd; margin-bottom: 20px;">
        <thead>
            <tr>
                <th><input type="checkbox" id="select-all">Select All</th>
                <th>Invoice Number</th>
                <th>Invoice Date</th>
                <th>Instructor Name</th>
                <th>Description</th>
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
if (!empty($from_date) && strtotime($booking->start_date) < strtotime($from_date)) {
    continue;
}

if (!empty($to_date) && strtotime($booking->start_date) > strtotime($to_date)) {
    continue;
}

        if ($booking) {
        echo '<tr>';
            echo '<td><input type="checkbox" class="invoice-checkbox" name="selected_invoices[]" value="' . esc_attr($invoice->invoice_number) . '"></td>';
            echo '<td>
    <a href="#" 
        class="invoice-link" 
        data-invoice-number="' . esc_attr($invoice->invoice_number) . '"
        data-customer-name="' . esc_attr($invoice->customer_name) . '"
        data-start-date="' . esc_attr($invoice->start_date) . '"
        data-end-date="' . esc_attr($invoice->end_date) . '"
        data-booking-type="' . esc_attr($invoice->booking_type) . '"
        data-amount="' . esc_attr(number_format($invoice->amount, 2)) . '"
        style="color: #2271b1; text-decoration: underline; cursor: pointer;">
        ' . esc_html($invoice->invoice_number) . '
    </a>
</td>';

            echo '<td>' . esc_html(date('Y-m-d', strtotime($invoice->date_created))) . '</td>';
            echo '<td>' . esc_html($booking->customer_name) . '</td>';
            echo '<td>' . esc_html($booking->description) . '</td>';
            echo '<td>Rs. ' . esc_html(number_format($invoice->amount, 2)) . '</td>';

$is_pending = ($payment_status === 'Pending');
$status_class = $is_pending ? 'status-pending' : 'status-paid';
$status_color = $is_pending ? '#FF9800' : '#4CAF50';

echo '<td>
    <button 
        type="button" 
        class="status-badge ' . $status_class . ($is_pending ? ' open-upload-modal' : '') . '" 
        data-id="' . esc_attr($invoice->id) . '" 
        style="border: none; background-color: ' . $status_color . '; color: white; padding: 5px 10px; border-radius: 4px; cursor: pointer;"
    >
        ' . $payment_status . '
    </button>
</td>';

$slip_url = !empty($invoice->payment_slip) ? esc_url(wp_upload_dir()['baseurl'] . '/' . $invoice->payment_slip) : '#';
$is_uploaded = !empty($invoice->payment_slip);

echo '<td>';
if ($is_uploaded) {
    echo '<a href="' . $slip_url . '" target="_blank" style="color: #0073aa; text-decoration: none;">
        <span class="dashicons dashicons-visibility"></span> View Slip
    </a>';
} else {
    echo '<span class="dashicons dashicons-visibility" style="color: #ccc; opacity: 0.5; cursor: not-allowed;" title="No slip uploaded"></span> View Slip';
}
echo '</td>';




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
document.addEventListener("DOMContentLoaded", function() {
    const modal = document.getElementById("uploadModal");
    const closeModal = document.querySelector(".close-modal");
    const invoiceIdInput = document.getElementById("invoice_id");

    // Only for pending buttons
    document.querySelectorAll(".open-upload-modal").forEach(button => {
        button.addEventListener("click", function() {
            const invoiceId = this.getAttribute("data-id");
            invoiceIdInput.value = invoiceId;
            modal.style.display = "block";
        });
    });

    closeModal.addEventListener("click", function() {
        modal.style.display = "none";
    });

    window.addEventListener("click", function(e) {
        if (e.target === modal) {
            modal.style.display = "none";
        }
    });
});
        document.addEventListener("DOMContentLoaded", function () {
    const selectAllCheckbox = document.getElementById("select-all");
    const invoiceCheckboxes = document.querySelectorAll(".invoice-checkbox");

    selectAllCheckbox.addEventListener("change", function () {
        invoiceCheckboxes.forEach(cb => cb.checked = selectAllCheckbox.checked);
    });

    invoiceCheckboxes.forEach(cb => {
        cb.addEventListener("change", function () {
            if (!this.checked) {
                selectAllCheckbox.checked = false;
            } else if ([...invoiceCheckboxes].every(i => i.checked)) {
                selectAllCheckbox.checked = true;
            }
        });
    });
});

    </script>';
echo '<script>
document.addEventListener("DOMContentLoaded", function () {
    document.getElementById("downloadCsvBtn").addEventListener("click", function () {
        const selectedCheckboxes = document.querySelectorAll(".invoice-checkbox:checked");

        if (selectedCheckboxes.length === 0) {
            alert("Please select at least one invoice to download.");
            return;
        }

        const rows = [
            ["Invoice Number", "Invoice Date", "Instructor Name", "Description", "Amount", "Payment Status"]
        ];

        selectedCheckboxes.forEach(function(cb) {
            const row = cb.closest("tr");
            const cells = row.querySelectorAll("td");

            const invoiceNumber = cells[1] ? cells[1].innerText.trim() : "";
            const invoiceDate = cells[2] ? cells[2].innerText.trim() : "";
            const instructor = cells[3] ? cells[3].innerText.trim() : "";
            const description = cells[4] ? cells[4].innerText.trim() : "";
            const amount = cells[5] ? cells[5].innerText.trim() : "";
            const status = cells[6] ? cells[6].innerText.trim() : "";

            rows.push([invoiceNumber, invoiceDate, instructor, description, amount, status]);
        });

        const csvContent = rows.map(function(e) {
            return e.map(function(cell) {
                return "\"" + cell.replace(/"/g, "\"\"") + "\"";
            }).join(",");
        }).join("\n");

        const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = "selected_invoices.csv";
        a.click();
        URL.revokeObjectURL(url);
    });
});
</script>';


}



add_action('init', 'handle_payment_slip_upload');
function handle_payment_slip_upload() {
    global $wpdb;

    if (isset($_POST['upload_slip']) && isset($_FILES['payment_slip'])) {
        $invoice_id = intval($_POST['invoice_id']);
        $file = $_FILES['payment_slip'];

        if (!empty($file['name'])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');

            $upload_overrides = array('test_form' => false);
            $movefile = wp_handle_upload($file, $upload_overrides);

            if ($movefile && !isset($movefile['error'])) {
                $file_url = str_replace(wp_upload_dir()['baseurl'] . '/', '', $movefile['url']);

                // Get invoice details
                $invoice = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT invoice_number, booking_id FROM {$wpdb->prefix}booking_invoices WHERE id = %d",
                        $invoice_id
                    )
                );
                // Get invoice month
                $month_name = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT month_name FROM {$wpdb->prefix}booking_invoices WHERE id = %d",
                        $invoice_id
                    )
                );

                if ($invoice) {
                    // Update payment slip and status
                    $wpdb->update(
                        "{$wpdb->prefix}booking_invoices",
                        array(
                            'payment_slip' => $file_url,
                            'payment_status' => 'Paid'
                        ),
                        array('id' => $invoice_id)
                    );

                    // Get customer_name from wp_booking_calendar using booking_id
                    $customer_name = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT customer_name FROM {$wpdb->prefix}booking_calendar WHERE id = %d",
                            $invoice->booking_id
                        )
                    );

                    // Get customer_email using customer_name
                    $customer_email = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT customer_email FROM {$wpdb->prefix}booking_customers WHERE customer_name = %s",
                            $customer_name
                        )
                    );
                    // Get customer details using customer_name
                    $customer_phone = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT customer_phone FROM {$wpdb->prefix}booking_customers WHERE customer_name = %s",
                            $customer_name
                        )
                    );
                    
                    // Get amount from invoice
                    $amount = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT amount FROM {$wpdb->prefix}booking_invoices WHERE id = %d",
                            $invoice_id
                        )
                    );
                    if ($customer_email) {
                        // Reset restriction
                        $wpdb->update(
                            "{$wpdb->prefix}booking_customers",
                            array('is_restricted' => 0),
                            array('customer_email' => $customer_email)
                        );

                        // Send email
                        $subject = 'Payment Received – Invoice ' . $invoice->invoice_number;
                            $message = "
<div style='font-family: Arial, sans-serif; background-color: #f9f9f9; padding: 20px;'>
    <div style='max-width: 600px; margin: auto; background: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.05);'>

        <!-- Logo and Company Info -->
        <div style='text-align: center; margin-bottom: 20px;'>
            <img src='https://makerspace.lk/wp-content/uploads/2025/02/Makerspace-Logo-25p.png' alt='Company Logo' style='height: 60px; margin-bottom: 10px;' />
            <div style='font-size: 14px; color: #333;'>
                <strong>" . get_bloginfo('name') . " Makerspace</strong><br>
                122/5, Attidiya Road, Bellantara Ln, Dehiwala.<br>
                +94 774 757 677 | info@makerspace.lk
            </div>
        </div>

        <!-- Payment Confirmation Title -->
        <h2 style='color: #f66e03; text-align: center;'>Payment Confirmation</h2>
            <p>Dear <strong>$customer_name</strong>,</p>
            <p>We have received your payment for <strong>Invoice #{$invoice->invoice_number}</strong>.</p>
            
        <!-- Customer Details -->
        <h3 style='color: #333; border-bottom: 1px solid #eee; margin-top: 30px;'>Customer Details</h3>
        <table style='width: 100%; margin-bottom: 20px; font-size: 14px; color: #333;'>
            <tr>
                <td style='padding: 8px; font-weight: bold; width: 150px;'>Name:</td>
                <td style='padding: 8px;'>$customer_name</td>
            </tr>
            <tr>
                <td style='padding: 8px; font-weight: bold;'>Phone:</td>
                <td style='padding: 8px;'>{$customer_phone->customer_phone}</td>
            </tr>
            <tr>
                <td style='padding: 8px; font-weight: bold;'>Email:</td>
                <td style='padding: 8px;'>$customer_email</td>
            </tr>
        </table>

        <!-- Payment Details -->
        <h3 style='color: #333; border-bottom: 1px solid #eee;'>Payment Details</h3>
        <table style='width: 100%; margin-bottom: 20px; font-size: 14px; color: #333;'>
            <tr>
                <td style='padding: 8px; font-weight: bold; width: 150px;'>Amount Paid:</td>
                <td style='padding: 8px;'>Rs. " . number_format($amount, 2) . "</td>
            </tr>
            <tr>
                <td style='padding: 8px; font-weight: bold;'>Paid Month:</td>
                <td style='padding: 8px;'>{$month_name->month_name}</td>
            </tr>
        </table>

        <!-- Footer -->
        <p style='font-size: 14px;'>If you have any questions, feel free to contact us at <strong>info@makerspace.lk</strong>.</p>
        <p style='margin-top: 30px;'>Thank you for your prompt payment.<br>
        <strong>" . get_bloginfo('name') . " Makerspace Team</strong></p>
        </div>
    </div>
    ";

                        $headers = array('Content-Type: text/html; charset=UTF-8');

                        booking_calendar_send_custom_email($customer_email, $subject, $message, $headers);
                    }
                }

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


function get_invoice_number_from_google_sheet() {
    $google_web_app_url = 'https://script.google.com/macros/s/AKfycbxFf9zMcl7rIkI4JgVsWqNuzwoAQJKzVJfv8w46RP0qO3nk9Yww79QFNEo2QbYY0dCM/exec'; // Replace with your Web App URL

    $response = wp_remote_get($google_web_app_url);

    if (is_wp_error($response)) {
        return new WP_Error('google_sheet_error', 'Error fetching invoice number from Google Sheet.');
    }

    $invoice_number = wp_remote_retrieve_body($response);

    if (empty($invoice_number)) {
        return new WP_Error('empty_invoice_number', 'No invoice number returned from Google Sheet.');
    }

    return sanitize_text_field($invoice_number);
}



function update_invoice_number_in_google_sheet($invoice_number) {
    $google_web_app_url = 'https://script.google.com/macros/s/AKfycbxFf9zMcl7rIkI4JgVsWqNuzwoAQJKzVJfv8w46RP0qO3nk9Yww79QFNEo2QbYY0dCM/exec';

    $response = wp_remote_post($google_web_app_url, [
        'body' => [
            'invoice_number' => $invoice_number
        ]
    ]);

    if (is_wp_error($response)) {
        return new WP_Error('google_sheet_error', 'Error updating invoice number in Google Sheet.');
    }

    $body = wp_remote_retrieve_body($response);
    $result = json_decode($body, true);

    if (empty($result['success']) || !$result['success']) {
        return new WP_Error('google_sheet_update_error', $result['message'] ?? 'Unknown error.');
    }

    return true;
}



function save_booking() {   
    global $wpdb;

    // Get data from the AJAX request
    $customer_name = sanitize_text_field($_POST['customer_name']);
    $start_time = sanitize_text_field($_POST['start_time']);
    $end_time = sanitize_text_field($_POST['end_time']);
    $start_date = sanitize_text_field($_POST['start_date']); 
    $end_date = sanitize_text_field($_POST['end_date']);
    $booking_type = sanitize_text_field($_POST['booking_type']);
    $description = sanitize_textarea_field($_POST['description']);
    $course_fee = isset($_POST['course_fee']) ? floatval($_POST['course_fee']) : 0;
    $discount_amount = isset($_POST['discount_amount_hidden']) ? floatval($_POST['discount_amount_hidden']) : 0;

    // Get customer email from wp_booking_customers table
    $customer_email = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT customer_email FROM {$wpdb->prefix}booking_customers WHERE customer_name = %s LIMIT 1",
            $customer_name
        )
    );

    // Get customer type
    $customer_type = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT customer_type FROM {$wpdb->prefix}booking_customers WHERE customer_email = %s LIMIT 1",
            $customer_email
        )
    );

    // Check if invoices should be skipped (only for makerspace customers)
    $skip_invoices = strtolower($customer_type) === 'makerspace';

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

    // Create description code ID (using the initials of the description)
    $description_initials = strtoupper(implode('', array_map(function ($word) {
        return strtoupper($word[0]);
    }, explode(' ', $description))));

    // Set the description_code_id to just the initials (no number increments)
    $description_code_id = $description_initials;

    $booking_dates = []; // Track all the booking dates
    $current_date = clone $start_date_obj;

    // Insert each booking date and store their booking_ids
    $booking_ids = [];
    
    $group_id = null;
    if (strtolower($booking_type) === 'class rent') {
        $group_id = uniqid('class_', true); // You can customize this format
    }

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
        $month_name = date('F Y', strtotime($booking_date));
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
                'booking_type' => $booking_type,
                'group_id' => $group_id,
                'description' => $description,
                'description_code_id' => $description_code_id, // Save description code ID
                'course_fee' => $course_fee,
                'discount_amount' => $discount_amount,
                'month_name' => $month_name
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

$selected_slot_count = isset($_POST['selected_slot_count']) ? intval($_POST['selected_slot_count']) : 0;
$per_day_cost = isset($_POST['per_day_cost']) ? floatval($_POST['per_day_cost']) : 0;
     
    if (!$skip_invoices) {
$month_index = 0;

foreach ($monthly_bookings as $month => $dates) {
    $count = count($dates);
    $amount = $count * $per_day_cost;

    $discounted_amount = ($discount_amount > 0 && $discount_amount < $amount) ? $amount : $amount;
    $total_amount += $discounted_amount;

    $month_name = date('F', strtotime($month));
    $year = date('Y', strtotime($month));
    $full_month_label = $month_name . ' ' . $year;

    // Get representative booking ID
    $representative_booking_id = null;
    foreach ($booking_ids as $booking_id) {
        $booking_date = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT booking_date FROM {$wpdb->prefix}booking_calendar WHERE id = %d LIMIT 1",
                $booking_id
            )
        );
        $booking_month = date('Y-m', strtotime($booking_date));

        if ($booking_month === $month) {
            $representative_booking_id = $booking_id;
            break;
        }
    }

    if ($month_index === 0 && !$skip_invoices) {
        // Insert into main invoice table
        $now = current_time('Y-m-d');
$date_parts = explode('-', $now);
$year = substr($date_parts[0], 2, 2); // '25'
$month = str_pad($date_parts[1], 2, '0', STR_PAD_LEFT); // '06'
$day = str_pad($date_parts[2], 2, '0', STR_PAD_LEFT);   // '07'

$invoice_number = get_invoice_number_from_google_sheet();
if (is_wp_error($invoice_number)) {
    wp_send_json_error(['message' => $invoice_number->get_error_message()]);
    return;
}
$update_result = update_invoice_number_in_google_sheet($invoice_number);
if (is_wp_error($update_result)) {
    error_log('Failed to update Class Sales sheet: ' . $update_result->get_error_message());
}


        $invoice_counter++;

        $wpdb->insert(
            $wpdb->prefix . 'booking_invoices',
            array(
                'booking_id' => $representative_booking_id,
                'invoice_number' => $invoice_number,
                'amount' => $discounted_amount,
                'month_name' => $full_month_label,
                'group_id' => $group_id,
                'invoice_sent_at' => current_time('mysql')
            )
        );

        $invoice_id = $wpdb->insert_id;
        $invoice_urls[] = admin_url('admin.php?page=view-invoice&invoice_id=' . $invoice_id);

        if ($customer_email) {
    send_invoice_email($customer_email, $invoice_number, $amount, $invoice_urls, $customer_name,$booking_type,$start_time,$end_time, $dates);

    // Schedule 3 reminders, each 3 minutes apart
    for ($i = 1; $i <= 3; $i++) {
        wp_schedule_single_event(
            time() + (60 * 2 * $i),
            'check_and_send_reminder_email',
            array($customer_email, $invoice_number, $invoice_urls[0], $customer_name, $booking_type, $start_date, $end_date, (string)$amount, $i) // include $i to track which reminder it is
        );
    }
}

    } else {
        // Store in draft table
        $wpdb->insert(
            $wpdb->prefix . 'booking_draft_invoices',
            array(
                'booking_id' => $representative_booking_id,
                'amount' => $discounted_amount,
                'month_name' => $full_month_label,
                'schedule_at' => date('Y-m-d H:i:s', time() + $delay_counter),
                'group_id' => $group_id

            )
        );

        // Schedule the job that will later move this to wp_booking_invoices and send email
        wp_schedule_single_event(time() + $delay_counter, 'generate_and_send_delayed_invoice', array($wpdb->insert_id));
    }

    $delay_counter += 60 * 8;
    $month_index++;
}


        // Update the invoice counter in options
        update_option('invoice_counter', $invoice_counter);
    }

    // Redirect to the invoice page regardless of whether invoices are skipped or not
    $redirect_url = 'https://designhouse.lk/wp-admin/admin.php?page=view-invoice';

    wp_send_json_success([
        'message' => 'Booking saved successfully!',
        'redirect_url' => $redirect_url
    ]);
}

add_action('wp_ajax_save_booking', 'save_booking');

add_action('generate_and_send_delayed_invoice', 'generate_and_send_delayed_invoice_callback');

function generate_and_send_delayed_invoice_callback($draft_invoice_id) {
    global $wpdb;

    $draft = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$wpdb->prefix}booking_draft_invoices WHERE id = %d", $draft_invoice_id),
        ARRAY_A
    );

    if (!$draft) return;

    $group_id = $draft['group_id'];

    // Step 1: Check latest invoice for same booking_id
    $last_invoice = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}booking_invoices 
             WHERE group_id = %s 
             ORDER BY invoice_sent_at DESC 
             LIMIT 1",
            $group_id
        ),
        ARRAY_A
    );

if ($last_invoice && $last_invoice['payment_status'] == 'Pending' && intval($last_invoice['reminder_count']) == 3) {
    // ✅ Fetch email BEFORE deleting anything
    $booking = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}booking_calendar WHERE group_id = %s LIMIT 1",
            $group_id
        ),
        ARRAY_A
    );

    $customer_email = null;
    if ($booking) {
        $customer_email = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT customer_email FROM {$wpdb->prefix}booking_customers WHERE customer_name = %s LIMIT 1",
                $booking['customer_name']
            )
        );
    }

    if ($customer_email) {
        // Get all unpaid invoice numbers and months
// Get all unpaid invoice numbers and months
$unpaid_invoices = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT invoice_number, month_name FROM {$wpdb->prefix}booking_invoices 
         WHERE group_id = %s AND payment_status = 'Pending' AND reminder_count = 3",
        $group_id
    ),
    ARRAY_A
);

$invoice_lines = array_map(function($inv) {
    return "<li><strong>{$inv['invoice_number']}</strong> — {$inv['month_name']}</li>";
}, $unpaid_invoices);

$invoice_details = "<ul>" . implode('', $invoice_lines) . "</ul>";

// Get customer name
$customer_name = $booking['customer_name'] ?? 'Customer';

// Build HTML message
$message = "
<html>
<body>
<p>Dear <strong>$customer_name</strong>,</p>

<p>Your booking has been removed due to multiple unpaid invoice reminders.</p>
<p>Unfortunately, we haven’t received your payment yet. We're sorry to inform you that we will now proceed to <strong>remove your booking</strong> from our system.</p>

<p>The following invoices were not paid and are now removed:</p>
$invoice_details

<p>If this is a mistake or you need assistance, please contact us immediately.</p>

<p>Best regards,<br>Makerspace Team</p>
</body>
</html>
";

// Set content type to HTML
add_filter('wp_mail_content_type', function() {
    return "text/html";
});

wp_mail(
    $customer_email,
    "Final Notice: Booking Removal Due to Unpaid Invoice",
    $message
);

// Reset content type after sending
remove_filter('wp_mail_content_type', 'set_html_content_type');


    }

    // ✅ Now do the deletions
$unpaid_months = $wpdb->get_col(
    $wpdb->prepare(
        "SELECT DISTINCT month_name FROM {$wpdb->prefix}booking_invoices 
         WHERE group_id = %s AND payment_status = 'Pending' AND reminder_count = 3",
        $group_id
    )
);

if (!empty($unpaid_months)) {
    // Step 1: Convert month names to DateTime and find earliest unpaid month
    $dates = array_map(function($month_name) {
        return DateTime::createFromFormat('F Y', $month_name);
    }, $unpaid_months);

    usort($dates, function($a, $b) {
        return $a <=> $b;
    });

    $earliest_unpaid_date = $dates[0];
    $earliest_unpaid_month_str = $earliest_unpaid_date->format('F Y');

    // Step 2: Get all calendar bookings in future or same month
    $all_future_bookings = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, month_name FROM {$wpdb->prefix}booking_calendar 
             WHERE group_id = %s",
            $group_id
        ),
        ARRAY_A
    );

    foreach ($all_future_bookings as $booking) {
        $booking_date = DateTime::createFromFormat('F Y', $booking['month_name']);
        if ($booking_date >= $earliest_unpaid_date) {
            // Check if this month is paid
            $is_paid = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}booking_invoices 
                     WHERE group_id = %s AND month_name = %s AND payment_status = 'Paid'",
                    $group_id,
                    $booking['month_name']
                )
            );

            if (!$is_paid) {
                // Delete booking if not paid
                $wpdb->delete("{$wpdb->prefix}booking_calendar", ['id' => $booking['id']]);
            }
        }
    }

    // Delete only those unpaid invoices with reminder_count = 3
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}booking_invoices 
             WHERE group_id = %s AND payment_status = 'Pending' AND reminder_count = 3",
            $group_id
        )
    );
}

// Always delete draft invoices for this group
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->prefix}booking_draft_invoices 
         WHERE group_id = %s",
        $group_id
    )
);


    return;
}




    // Get booking details
    $booking = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT customer_name, booking_type, start_date, end_date FROM {$wpdb->prefix}booking_calendar WHERE id = %d LIMIT 1",
            $draft['booking_id']
        ),
        ARRAY_A
    );

    if (!$booking) return;

    // Get customer email
    $customer_email = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT customer_email FROM {$wpdb->prefix}booking_customers WHERE customer_name = %s LIMIT 1",
            $booking['customer_name']
        )
    );

    // Get all booking dates
    $booking_dates = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT booking_date FROM {$wpdb->prefix}booking_calendar 
             WHERE group_id = %s 
             ORDER BY booking_date ASC",
            $group_id
        )
    );

    // Generate invoice number
    $invoice_counter = get_option('invoice_counter', 1);
    $now = current_time('Y-m-d');
$date_parts = explode('-', $now);
$year = substr($date_parts[0], 2, 2); // '25'
$month = str_pad($date_parts[1], 2, '0', STR_PAD_LEFT); // '06'
$day = str_pad($date_parts[2], 2, '0', STR_PAD_LEFT);   // '07'

$invoice_number = get_invoice_number_from_google_sheet();
if (is_wp_error($invoice_number)) {
    wp_send_json_error(['message' => $invoice_number->get_error_message()]);
    return;
}

    update_option('invoice_counter', $invoice_counter + 1);

    // Insert into main invoice table
    $wpdb->insert(
        $wpdb->prefix . 'booking_invoices',
        array(
            'booking_id'        => $draft['booking_id'],
            'invoice_number'    => $invoice_number,
            'amount'            => $draft['amount'],
            'month_name'        => $draft['month_name'],
            'group_id'          => $group_id,
            'invoice_sent_at'   => current_time('mysql'),
            'payment_status'    => 'Pending',
            'reminder_count'    => 0,
            'final_warning_sent' => 0
        )
    );

    $invoice_id = $wpdb->insert_id;
    $invoice_url = admin_url('admin.php?page=view-invoice&invoice_id=' . $invoice_id);

    // Send invoice email
    if ($customer_email) {
        send_invoice_email(
            $customer_email,
            $invoice_number,
            $draft['amount'],
            [$invoice_url],
            $booking['customer_name'],
            $booking['booking_type'],
            $booking['start_time'],
            $booking['end_time'],
            $booking_dates,
            $draft['month_name']
        );
    }

    // Schedule 3 reminders
    for ($i = 1; $i <= 3; $i++) {
        wp_schedule_single_event(
            current_time('timestamp') + 60 * 2 * $i, // 1, 2, 3 minutes later
            'check_and_send_reminder_email',
            array(
                $customer_email,
                $invoice_number,
                $invoice_url,
                $booking['customer_name'],
                $booking['booking_type'],
                $booking['start_date'],
                $booking['end_date'],
                (string) $draft['amount'],
                $i // reminder count
            )
        );
    }

    // Delete the draft invoice
    $wpdb->delete("{$wpdb->prefix}booking_draft_invoices", array('id' => $draft_invoice_id));
}



//add_action('admin_init', 'delete_unpaid_and_future_months_on_admin_load');
//
//function delete_unpaid_and_future_months_on_admin_load() {
//    if (!current_user_can('manage_options')) return;
//
//    global $wpdb;
//
//    // Fetch all pending invoices with final warning sent
//    $invoices = $wpdb->get_results("
//        SELECT id, booking_id, group_id, month_name 
//        FROM {$wpdb->prefix}booking_invoices 
//        WHERE payment_status = 'Pending' AND final_warning_sent = 1
//    ");
//
//    foreach ($invoices as $invoice) {
//        $group_id        = $invoice->group_id;
//        $unpaid_month_raw = $invoice->month_name;
//
//        // Convert unpaid month string to DateTime object
//        try {
//            $unpaid_month_date = DateTime::createFromFormat('F Y', $unpaid_month_raw);
//        } catch (Exception $e) {
//            continue; // Skip if invalid
//        }
//
//        // Get all months for this group
//        $all_months = $wpdb->get_col($wpdb->prepare(
//            "SELECT DISTINCT month_name FROM {$wpdb->prefix}booking_calendar 
//             WHERE group_id = %d",
//            $group_id
//        ));
//
//        foreach ($all_months as $month_raw) {
//            try {
//                $month_date = DateTime::createFromFormat('F Y', $month_raw);
//            } catch (Exception $e) {
//                continue;
//            }
//
//            // If the month is equal or after the unpaid month
//            if ($month_date >= $unpaid_month_date) {
//                // Delete booking_calendar
//                $wpdb->delete("{$wpdb->prefix}booking_calendar", [
//                    'group_id'   => $group_id,
//                    'month_name' => $month_raw
//                ]);
//
//                // Delete matching draft invoices
//                $wpdb->delete("{$wpdb->prefix}booking_draft_invoices", [
//                    'group_id'   => $group_id,
//                    'month_name' => $month_raw
//                ]);
//
//                // Delete only pending invoices
//                $wpdb->query($wpdb->prepare(
//                    "DELETE FROM {$wpdb->prefix}booking_invoices 
//                     WHERE group_id = %d AND month_name = %s AND payment_status = 'Pending'",
//                    $group_id,
//                    $month_raw
//                ));
//            }
//        }
//    }
//}








// Send invoice email function
// Include the FPDF library
require_once( plugin_dir_path( __FILE__ ) . 'fpdf/fpdf.php'); // Correct path to fpdf library

function send_invoice_email($customer_email, $invoice_number, $amount, $invoice_urls, $customer_name,$booking_type,$start_time,$end_time, $dates, $month_name = null) {
    // If month_name is not provided, fall back to first booking date month
    if (!$month_name && !empty($dates)) {
        $month_name = date('F Y', strtotime(reset($dates)));
    } elseif (!$month_name) {
        // default month name if dates empty
        $month_name = date('F Y');
    }
    
    $pdf = new FPDF();
    $pdf->AddPage();
    
    // Logo
// Add Logo
$logo_path = plugin_dir_path(__FILE__) . 'images/logo.png'; // Adjust path as needed
if (file_exists($logo_path)) {
    $pdf->Image($logo_path, 10, 10, 40); // X=10, Y=10, Width=40mm
}
    // Header Title
    $pdf->SetFont('Arial', 'B', 22);
    $pdf->SetXY(55, 10);
    $pdf->SetTextColor(255, 130, 0); // Orange
    $pdf->Cell(100, 10, 'TESLA ROBOTICS');

    // Contact Details
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(0);
    $pdf->SetXY(140, 10);
    $pdf->MultiCell(60, 5, "8, 1/4, Sunethradevi Road\nKohuwala\nSri Lanka\nTel: 071 4436737 / 077 5678000\nEmail: info@makerspace.lk\nWeb: www.makerspace.lk\nRegd. No.: PV00298446");

    // Subtitle
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetXY(55, 20);
    $pdf->SetTextColor(50, 50, 50);
    $pdf->Cell(100, 10, 'MAKERSPACE.LK');
    $pdf->SetXY(55, 26);
    $pdf->Cell(100, 10, 'TESLA ROBOTICS (PVT) LTD.');

    // Orange Line
    $pdf->SetDrawColor(255, 153, 51);
    $pdf->SetLineWidth(1.5);


    // INVOICE title
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->SetXY(80, 45);
    $pdf->SetTextColor(0);
    $pdf->Cell(50, 10, 'INVOICE', 0, 1, 'C');


    // Customer & Invoice Details (Smaller Width Table)
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetDrawColor(0); // Black border
    $pdf->SetFillColor(255); // White fill
    $pdf->SetLineWidth(0);
    // Customer and Invoice Details
    $pdf->SetFont('Arial', '', 11);
    $pdf->SetXY(10, 60);
    $pdf->Cell(100, 10, "Customer: $customer_name", 1);
    $pdf->SetXY(110, 60);
    $pdf->Cell(45, 10, "Invoice No.", 1);
    $pdf->Cell(35, 10, $invoice_number, 1);

    $pdf->SetXY(10, 70);
    $pdf->Cell(100, 10, '', 1); // Empty row
    $pdf->SetXY(110, 70);
    $pdf->Cell(45, 10, "Date", 1);
    $pdf->Cell(35, 10, date('d/m/Y'), 1);

    $pdf->SetXY(110, 80);
    $pdf->Cell(45, 10, "Month", 1);
    $pdf->Cell(35, 10, $month_name, 1);

    // Item Table Header
    $pdf->SetXY(10, 100);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(255, 153, 51);
    $pdf->Cell(10, 10, 'No', 1, 0, 'C', true);
    $pdf->Cell(80, 10, 'Description', 1, 0, 'C', true);
    $pdf->Cell(30, 10, 'Quantity', 1, 0, 'C', true);
    $pdf->Cell(35, 10, 'Unit Price', 1, 0, 'C', true);
    $pdf->Cell(35, 10, 'Subtotal', 1, 1, 'C', true);

    // Example Items
    $pdf->SetFont('Arial', '', 10);
// Calculate duration in hours
$start = new DateTime($start_time);
$end = new DateTime($end_time);
$interval = $start->diff($end);
$duration_in_hours = $interval->h + ($interval->i / 60);
if ($duration_in_hours == 0) {
    $duration_in_hours = 1;
}

// Calculate unit price based on duration
$unit_price = $amount / $duration_in_hours;
    $pdf->Cell(10, 10, '1', 1);
if (strtolower($booking_type) === 'class rent') {
    $description = 'Class Rent (' . $start_time . ' to ' . $end_time . ')';
} else {
    $description = ucfirst($booking_type) . ' (' . $start_time . ' to ' . $end_time . ')';
}
$pdf->Cell(80, 10, $description, 1);

    $pdf->Cell(30, 10, number_format((float)$duration_in_hours, 2), 1);
    $pdf->Cell(35, 10, number_format((float)$unit_price, 2), 1);
    $pdf->Cell(35, 10, number_format((float)$amount, 2), 1, 1);

    //$pdf->Cell(10, 10, '2', 1);
    //$pdf->Cell(80, 10, '', 1);
    //$pdf->Cell(30, 10, '', 1);
    //$pdf->Cell(35, 10, '', 1);
    //$pdf->Cell(35, 10, '', 1, 1);

    // Total Row
    $pdf->Cell(155, 10, 'Total Amount (LKR)', 1);
    $pdf->Cell(35, 10, number_format((float)$amount, 2), 1, 1);

    // Footer Note
    $pdf->Ln(10);
    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(0, 7, 'I hereby agree that the above goods were received by me in good condition.');

    // Save PDF and Send Email
    $upload_dir = wp_upload_dir();
    $temp_file = $upload_dir['path'] . '/Invoice-' . $invoice_number . '.pdf';
    $pdf->Output('F', $temp_file);

    // Email
    $subject = 'Your Booking Invoice – ' . $month_name;
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
    booking_calendar_send_custom_email($customer_email, $subject, $message, $headers, array($temp_file));

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
    $sent = booking_calendar_send_custom_email($customer_email, $subject, $message, $headers, $attachments);

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
    // Schedule final warning email and deletion after 3 minutes
//wp_schedule_single_event(time() + 60 *2, 'send_final_warning_and_delete_booking', array(
//    $customer_email, $invoice_number, $invoice_url, $customer_name
//));
//
        return;
    }

    // Schedule next reminder if less than 3 have been sent

}

// Hook the function properly
add_action('check_and_send_reminder_email', 'check_and_send_reminder_email', 10, 8);
function send_final_warning_and_delete_booking($customer_email, $invoice_number, $invoice_url, $customer_name) {
    global $wpdb;
        // Re-check payment status before acting
    $invoice = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, booking_id, payment_status, final_warning_sent FROM {$wpdb->prefix}booking_invoices WHERE invoice_number = %s LIMIT 1",
            $invoice_number
        ),
        ARRAY_A
    );

    if (!$invoice || $invoice['payment_status'] === 'Paid') {
        return; // Stop if invoice is missing or already paid
    }

    $final_subject = 'Final Notice: Booking Removal Due to Unpaid Invoice';
    $final_message = "
    <html>
    <body>
        <p>Dear $customer_name,</p>
        <p>We've sent you 3 reminders regarding your unpaid invoice <strong>#$invoice_number</strong>.</p>
        <p>Unfortunately, we haven’t received your payment yet.We're sorry to inform you about, we will now proceed to <strong>remove your booking</strong> from our system.</p>
        <p>If this is a mistake or you need assistance, please contact us immediately.</p>
        <p><a href='$invoice_url'>View Your Invoice</a></p>
        <p>Best regards,<br>Makerspace Team</p>
    </body>
    </html>
    ";

    $sent = booking_calendar_send_custom_email($customer_email, $final_subject, $final_message, ['Content-Type: text/html; charset=UTF-8']);

    if ($sent) {
        // Mark final warning as sent
        $wpdb->update(
            $wpdb->prefix . 'booking_invoices',
            array('final_warning_sent' => 1),
            array('id' => $invoice['id'])
        );
    } else {
        error_log("Final warning email failed to send to $customer_email.");
    }
}
add_action('send_final_warning_and_delete_booking', 'send_final_warning_and_delete_booking', 10, 4);





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
    booking_calendar_send_custom_email($customer_email, $subject, $message, $headers);
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
    booking_calendar_send_custom_email($customer_email, $subject, $message, $headers);
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
    if (isset($_POST['booking_id']) && is_numeric($_POST['booking_id'])) {
        global $wpdb;
        $booking_id = intval($_POST['booking_id']);
        $today = date('Y-m-d');

        // Step 1: Get the group_id for the booking_id
        $group_id = $wpdb->get_var(
            $wpdb->prepare("SELECT group_id FROM wp_booking_calendar WHERE id = %d", $booking_id)
        );

        if ($group_id !== null) {
            // Step 2: Delete only bookings in this group that are today or in the future
            $result = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM wp_booking_calendar 
                     WHERE group_id = %s AND booking_date >= %s",
                    $group_id,
                    $today
                )
            );

            if ($result !== false) {
                echo json_encode(['status' => 'success', 'deleted_count' => $result]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Error deleting future group bookings']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Group ID not found']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid booking ID']);
    }

    wp_die();
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
                    $booked_time_str .= '<small> ' . esc_html($booking->description) . '</small>';
                    if (isset($booking->course_fee) && $booking->course_fee !== '') {
    $booked_time_str .= '<small>Fee: ' . esc_html($booking->course_fee) . '</small>';
}

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
                    <!-- Description Field -->
                   <label for="description">Description:</label>
                   <textarea name="description" id="description" rows="4" style="width: 100%;" placeholder="Enter a description (optional)"></textarea>
                   
                   <!-- Course Fee Field -->
                   <label for="course_fee">Course Fee:</label>
                   <input type="number" name="course_fee" id="course_fee" style="width: 100%;" placeholder="Enter course fee" min="0" step="0.01">


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
<div><p id="selectedAmount" style="font-weight: bold;">Per Hourly Cost: Rs. 0</p>
<p id="monthlyCost" style="font-weight: bold;">Monthly Cost: Rs. 0</p></div>
<div style="margin-top:10px;">
    <label>
        <input type="checkbox" id="enableDiscount" onchange="toggleDiscountInput()"> Apply Discount
    </label>
</div>

<div id="discountSection" style="display: none; margin-top: 10px;">
    <label for="discountAmount">Discount Amount (Rs.):</label>
    <input type="number" id="discountAmount" min="0" value="0" onchange="applyDiscount()" style="width: 100px; margin-left: 5px;">
</div>

<div id="discountedCost" style="margin-top: 10px; font-weight: bold;"></div>
<input type="hidden" name="selected_slot_count" id="selected_slot_count" value="0" />
<input type="hidden" name="per_day_cost" id="per_day_cost" value="0" />
<input type="hidden" name="discount_amount_hidden" id="discount_amount_hidden" value="0" />




      

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
echo '
<style>
    /* Modal Container */
    .modal-content {
        background: #fff;
        width: 400px;
        margin: 100px auto;
        padding: 20px;
        border-radius: 8px;
        display: flex;
        flex-direction: column;
        max-height: 80vh; /* Max height to prevent too much vertical expansion */
        overflow-y: auto;  /* Enable scrolling if content exceeds max height */
    }

    /* Scrollable Content Area */
    form {
        display: flex;
        flex-direction: column;
        gap: 15px;
        flex: 1; /* Take up remaining space */
    }

    /* Ensure Save and Close buttons are at the bottom */
    form > div:last-child {
        margin-top: auto; /* Push buttons to the bottom */
    }

    /* Button Styling */
    input[type="submit"], button {
        background-color: #21759b;
        color: white;
        padding: 10px 20px;
        border: none;
        cursor: pointer;
        border-radius: 5px;
    }

    /* Ensure buttons are properly aligned and not stretched */
    button {
        width: auto;
        align-self: flex-end; /* Align Close button to the right */
    }
</style>
';






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


function booking_calendar_modal_js() { 
    ?>
    <script type="text/javascript">

function calculateMonthlyCost(startDateStr, endDateStr, selectedSlotCount) {
    const startDate = new Date(startDateStr);
    const endDate = new Date(endDateStr);
    const targetDay = startDate.getDay();
    const monthBreakdown = {};
    const tempDate = new Date(startDate);

    while (tempDate <= endDate) {
        if (tempDate.getDay() === targetDay) {
            const monthKey = `${tempDate.toLocaleString('default', { month: 'long' })} ${tempDate.getFullYear()}`;
            if (!monthBreakdown[monthKey]) {
                monthBreakdown[monthKey] = 0;
            }
            monthBreakdown[monthKey]++;
        }
        tempDate.setDate(tempDate.getDate() + 1);
    }

    window.lastMonthBreakdown = monthBreakdown;
    window.lastSelectedSlotCount = selectedSlotCount;

    const discountAmount = parseFloat(document.getElementById("discountAmount")?.value) || 0;
    const baseRate = 3000;
    const perSlotCost = baseRate - discountAmount;

    let breakdownHtml = "";
    let total = 0;

    for (const [month, weekCount] of Object.entries(monthBreakdown)) {
        const cost = weekCount * selectedSlotCount * perSlotCost;
        total += cost;
        breakdownHtml += `<div>${month}: Rs. ${cost.toLocaleString()}</div>`;
    }

    window.lastMonthlyTotal = total;
    window.lastPerSlotCost = perSlotCost;

    breakdownHtml += `<strong>Total Monthly Cost: Rs. ${total.toLocaleString()}</strong>`;
    document.getElementById("monthlyCost").innerHTML = breakdownHtml;

    if (document.getElementById("enableDiscount")?.checked) {
        applyDiscount();
    } else {
        document.getElementById("discountedCost").innerHTML = '';
    }
}


function toggleDiscountInput() {
    const isChecked = document.getElementById("enableDiscount").checked;
    document.getElementById("discountSection").style.display = isChecked ? "block" : "none";
    if (!isChecked) {
        document.getElementById("discountedCost").innerHTML = '';
    } else {
        applyDiscount();
    }
}

function applyDiscount() {
    const discountAmount = parseFloat(document.getElementById("discountAmount").value) || 0;
    const baseRate = 3000;
    const perSlotCost = baseRate - discountAmount;

    if (!window.lastMonthBreakdown || window.lastSelectedSlotCount === undefined) {
        document.getElementById("discountedCost").innerHTML = '';
        document.getElementById("discount_amount_hidden").value = 0;
        return;
    }

    if (discountAmount < 0 || discountAmount > baseRate) {
        document.getElementById("discountedCost").innerHTML = "Invalid discount amount.";
        document.getElementById("discount_amount_hidden").value = 0;
        return;
    }

    let discountedHtml = "<strong>Discounted Monthly Cost:</strong><br>";
    let discountedTotal = 0;

    for (const [month, weekCount] of Object.entries(window.lastMonthBreakdown)) {
        const reduced = weekCount * window.lastSelectedSlotCount * perSlotCost;
        discountedHtml += `<div>${month}: Rs. ${reduced.toLocaleString()} (Discounted)</div>`;
        discountedTotal += reduced;
    }

    discountedHtml += `<strong>Total Discounted Cost: Rs. ${discountedTotal.toLocaleString()}</strong>`;
    document.getElementById("discountedCost").innerHTML = discountedHtml;

    document.getElementById("discount_amount_hidden").value = discountAmount;

    // Update per-day cost as well
    updatePerDayCostDisplay(perSlotCost);
}


function updateStartEndTime() {
    const allSlots = Array.from(document.querySelectorAll('input[name="time_slot[]"], input[name="selected_slots[]"]'));
    const checkedSlots = allSlots.filter(cb => cb.checked);

    if (checkedSlots.length === 0) {
        document.getElementById("start_time").value = '';
        document.getElementById("end_time").value = '';
        document.getElementById("selectedAmount").textContent = `Per Hourly Cost: Rs. 0`;
        document.getElementById("monthlyCost").textContent = `Monthly Cost: Rs. 0`;
        return;
    }

    const slotValues = allSlots.map(cb => cb.value);
    const checkedValues = checkedSlots.map(cb => cb.value);
    const firstCheckedIndex = slotValues.indexOf(checkedValues[0]);
    const lastCheckedIndex = slotValues.indexOf(checkedValues[checkedValues.length - 1]);

    for (let i = firstCheckedIndex; i <= lastCheckedIndex; i++) {
        allSlots[i].checked = true;
    }

    const updatedCheckedSlots = allSlots.slice(firstCheckedIndex, lastCheckedIndex + 1);
    const times = updatedCheckedSlots.map(cb => {
        const [start, end] = cb.value.split(" - ");
        return { start, end };
    });

    document.getElementById("start_time").value = times[0].start;
    document.getElementById("end_time").value = times[times.length - 1].end;

    const discountAmount = parseFloat(document.getElementById("discountAmount")?.value) || 0;
    const perSlotCost = 3000 - discountAmount;
    const totalAmount = updatedCheckedSlots.length * perSlotCost;

    document.getElementById("selectedAmount").textContent = `Per Hourly Cost: Rs. ${totalAmount}`;
    document.getElementById('selected_slot_count').value = updatedCheckedSlots.length;
    document.getElementById('per_day_cost').value = totalAmount;

    const selectedSlotCount = updatedCheckedSlots.length;
    const startDateStr = document.getElementById("start_date").value;
    const endDateStr = document.getElementById("end_date")?.value;

    if (startDateStr && endDateStr) {
        calculateMonthlyCost(startDateStr, endDateStr, selectedSlotCount);
    } else {
        document.getElementById("monthlyCost").textContent = `Monthly Cost: Rs. 0`;
    }
}

function updatePerDayCostDisplay(perSlotCost) {
    const selectedSlotCount = parseInt(document.getElementById('selected_slot_count')?.value || 0);
    const total = selectedSlotCount * perSlotCost;
    document.getElementById("selectedAmount").textContent = `Per Hourly Cost: Rs. ${total}`;
    document.getElementById('per_day_cost').value = total;
}


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
        for (let hour = 8; hour < 19; hour++) {
            let start = `${hour.toString().padStart(2, '0')}:00`;
            let end = `${(hour + 1).toString().padStart(2, '0')}:00`;
            let slot = `${start} - ${end}`;

            let slotElement = document.createElement("div");
            slotElement.style.margin = "5px 0";

            let checkbox = document.createElement("input");
            checkbox.type = "checkbox";
            checkbox.name = "time_slot[]";
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


function closeBookingModal() {
    document.getElementById('bookingModal').style.display = 'none';
}

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







