<?php
/*

*/

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
    ?>
    <div class="wrap">
        <h1>Student Registration</h1>
        <form method="post" action="">
            <table class="form-table">
                <tr><th><label for="student_name">Student Name</label></th><td><input name="student_name" type="text" id="student_name" class="regular-text" required></td></tr>
                <tr><th><label for="student_email">Student Email</label></th><td><input name="student_email" type="email" id="student_email" class="regular-text" required></td></tr>
                <tr><th><label for="class_selected">Class Selected</label></th><td><input name="class_selected" type="text" id="class_selected" class="regular-text" required></td></tr>
            </table>
            <?php submit_button('Register Student'); ?>
        </form>
    </div>
    <?php

    if (isset($_POST['student_name'])) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'class_students';
        $wpdb->insert(
            $table_name,
            array(
                'student_name' => sanitize_text_field($_POST['student_name']),
                'student_email' => sanitize_email($_POST['student_email']),
                'class_selected' => sanitize_text_field($_POST['class_selected']),
                'date_registered' => current_time('mysql')
            )
        );
        echo '<div class="notice notice-success is-dismissible"><p>Student Registered Successfully!</p></div>';
    }
}
?>
