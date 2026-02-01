<?php
require_once __DIR__ . '/../bootstrap.php';

$contacts = [];
$error = null;

try {
    $response = $ola->getContacts(1, 100);
    $contacts = $response['data'] ?? [];
} catch (Exception $e) {
    if (strpos($e->getMessage(), '404') === false) { // Ignore if no contacts found/endpoints behavior
         $error = $e->getMessage();
    }
}

include get_template('header');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>My Contacts</h2>
    <a href="create.php" class="btn btn-primary">Create New Contact</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Organization</th>
                <th>City/Country</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($contacts)): ?>
                <tr><td colspan="5" class="text-center">No contacts found.</td></tr>
            <?php else: ?>
                <?php foreach ($contacts as $contact): ?>
                    <tr>
                        <td><?= htmlspecialchars($contact['name']) ?></td>
                        <td><?= htmlspecialchars($contact['email']) ?></td>
                        <td><?= htmlspecialchars($contact['organization'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($contact['city']) ?>, <?= htmlspecialchars($contact['country']) ?></td>
                        <td><?= htmlspecialchars(date('Y-m-d', strtotime($contact['created_at'] ?? 'now'))) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include get_template('footer'); ?>
