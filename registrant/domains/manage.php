<?php
require_once __DIR__ . '/../bootstrap.php';

$domains = [];
$error = null;

try {
    $response = $ola->getDomains(1, 100);
    $domains = $response['data'] ?? [];
} catch (Exception $e) {
    $error = $e->getMessage();
}

include get_template('header');
?>

<h2>My Domains</h2>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Domain</th>
                <th>Registered At</th>
                <th>Expires At</th>
                <th>Auto Renew</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($domains)): ?>
                <tr><td colspan="5" class="text-center">No domains found.</td></tr>
            <?php else: ?>
                <?php foreach ($domains as $domain): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($domain['domain']) ?></strong></td>
                        <td><?= htmlspecialchars(date('Y-m-d', strtotime($domain['registered_at'] ?? 'now'))) ?></td>
                        <td><?= htmlspecialchars(date('Y-m-d', strtotime($domain['expires_at'] ?? 'now'))) ?></td>
                        <td>
                            <?php if (!empty($domain['auto_renew'])): ?>
                                <span class="badge bg-success">On</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Off</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="../dns/manage_zone.php?domain_id=<?= urlencode($domain['id']) ?>&domain_name=<?= urlencode($domain['domain']) ?>" class="btn btn-sm btn-info text-white">Manage DNS</a>
                            <!-- Add Renew / Update buttons if needed -->
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include get_template('footer'); ?>
