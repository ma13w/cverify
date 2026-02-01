<?php
require_once __DIR__ . '/../bootstrap.php';

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => $_POST['name'] ?? '',
        'email' => $_POST['email'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'organization' => $_POST['organization'] ?? '',
        'address' => $_POST['address'] ?? '',
        'city' => $_POST['city'] ?? '',
        'postcode' => $_POST['postcode'] ?? '',
        'country' => $_POST['country'] ?? '',
    ];

    try {
        $ola->createContact($data);
        $success = "Contact created successfully.";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

include get_template('header');
?>

<h2>Create Contact Profile</h2>
<p class="text-muted">This contact info will be used for domain registration WHOIS data.</p>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success">
        <?= htmlspecialchars($success) ?>
        <p class="mt-2"><a href="../domains/search.php" class="btn btn-sm btn-success">Go to Domain Search</a></p>
    </div>
<?php endif; ?>

<form method="post" action="">
    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">Full Name</label>
            <input type="text" class="form-control" name="name" required>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" name="email" required>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">Phone</label>
            <input type="text" class="form-control" name="phone" placeholder="+1 555 000 000" required>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">Organization (Optional)</label>
            <input type="text" class="form-control" name="organization">
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label">Address</label>
        <input type="text" class="form-control" name="address" required>
    </div>
    <div class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label">City</label>
            <input type="text" class="form-control" name="city" required>
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">Postcode</label>
            <input type="text" class="form-control" name="postcode" required>
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">Country (2-letter ISO)</label>
            <input type="text" class="form-control" name="country" placeholder="US" maxlength="2" required>
        </div>
    </div>
    
    <button type="submit" class="btn btn-primary">Create Contact</button>
</form>

<?php include get_template('footer'); ?>
