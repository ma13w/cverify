<?php
/**
 * CSRF Protection Implementation Example
 * 
 * This file demonstrates how to implement CSRF protection
 * in CVerify forms using the Security class.
 */

require_once __DIR__ . '/src/Security.php';
use CVerify\Security;

// ============================================
// EXAMPLE 1: Form with CSRF Token
// ============================================
?>
<!DOCTYPE html>
<html>
<head>
    <title>CSRF Protection Example</title>
</head>
<body>
    <h1>Example Form with CSRF Protection</h1>
    
    <form method="POST" action="process.php">
        <!-- Regular form fields -->
        <input type="text" name="username" placeholder="Username">
        <input type="email" name="email" placeholder="Email">
        
        <!-- CSRF Token (hidden field) -->
        <input type="hidden" name="csrf_token" value="<?= Security::generateCsrfToken() ?>">
        
        <button type="submit">Submit</button>
    </form>
</body>
</html>

<?php
// ============================================
// EXAMPLE 2: Processing Form with CSRF Validation
// ============================================

// In process.php or any handler:

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token FIRST, before any processing
    Security::requireCsrfToken($_POST['csrf_token'] ?? null);
    
    // If we reach here, CSRF token is valid
    // Continue with normal processing...
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    
    // Process data...
    echo "Form processed successfully!";
}

// ============================================
// EXAMPLE 3: AJAX Request with CSRF Token
// ============================================
?>
<script>
// Get CSRF token from meta tag or data attribute
const csrfToken = '<?= Security::generateCsrfToken() ?>';

// AJAX request with CSRF token
fetch('/api/endpoint', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken // Or include in body
    },
    body: JSON.stringify({
        csrf_token: csrfToken,
        data: 'example'
    })
});
</script>

<?php
// ============================================
// EXAMPLE 4: Files that NEED CSRF Protection
// ============================================

/*
 * Add CSRF tokens to these files:
 * 
 * 1. user/dashboard.php
 *    - Form: Add new experience
 *    - Form: Request validation
 *    - Form: Delete experience
 * 
 * 2. company/dashboard.php
 *    - Form: Any approval actions
 *    - Form: Send attestations
 * 
 * 3. company/approve.php
 *    - Form: Approve validation request
 *    - Form: Reject validation request
 * 
 * 4. user/cv_manager.php
 *    - Form: Save CV changes
 * 
 * 5. user/setup.php & company/setup.php
 *    - Form: Initial setup
 *    - Form: Key generation
 */

// ============================================
// EXAMPLE 5: Implementation Template
// ============================================

// Step 1: Add to form HTML
?>
<form method="POST" action="">
    <!-- Your existing fields -->
    <input type="hidden" name="csrf_token" value="<?= Security::generateCsrfToken() ?>">
    <button type="submit" name="action" value="save">Save</button>
</form>

<?php
// Step 2: Add to form handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate CSRF token
        Security::requireCsrfToken($_POST['csrf_token'] ?? null);
        
        // Process form
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'save':
                // Your existing code...
                break;
        }
        
    } catch (RuntimeException $e) {
        // CSRF validation failed
        $error = 'Security validation failed. Please refresh and try again.';
    }
}

// ============================================
// EXAMPLE 6: AJAX with CSRF
// ============================================
?>
<script>
// For existing AJAX forms, add CSRF token
document.querySelectorAll('form[data-ajax="true"]').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        // CSRF token is already in FormData if input field exists
        
        fetch(this.action, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Handle success
            } else {
                // Handle error
                alert(data.error || 'An error occurred');
            }
        });
    });
});
</script>

<?php
// ============================================
// QUICK IMPLEMENTATION CHECKLIST
// ============================================

/*
 * [ ] 1. Add Security class to file: require_once __DIR__ . '/src/Security.php';
 * [ ] 2. Add hidden input in form: <input type="hidden" name="csrf_token" value="<?= Security::generateCsrfToken() ?>">
 * [ ] 3. Validate in POST handler: Security::requireCsrfToken($_POST['csrf_token'] ?? null);
 * [ ] 4. Test with valid token: should work normally
 * [ ] 5. Test with invalid token: should get 403 error
 * [ ] 6. Test without token: should get 403 error
 */
