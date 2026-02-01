<?php
require_once __DIR__ . '/../bootstrap.php';

$results = null;
$search = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['domain'])) {
    $search = trim($_POST['domain']);
    // Append .cv if not present
    if (!str_ends_with($search, '.cv')) {
        $search .= '.cv';
    }

    try {
        $checkData = $ola->checkDomain([$search], 'all');
        $results = $checkData['data'] ?? [];
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

include get_template('header');
?>

<h2>Search for a .cv Domain</h2>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post" action="" class="mb-5">
    <div class="input-group mb-3">
        <input type="text" name="domain" class="form-control form-control-lg" placeholder="example.cv" value="<?= htmlspecialchars($search) ?>" required>
        <button class="btn btn-primary btn-lg" type="submit">Search</button>
    </div>
</form>

<?php if ($results): ?>
    <div class="card">
        <div class="card-header">Search Results</div>
        <ul class="list-group list-group-flush">
            <?php foreach ($results as $domain => $info): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1"><?= htmlspecialchars($domain) ?></h5>
                        <?php if ($info['available']): ?>
                            <span class="badge bg-success">Available</span>
                            <div class="text-muted small">
                                Registration Fee: <?= $info['registration_fee'] ?? '?' ?> <?= $info['currency'] ?? '' ?><br>
                                Renewal Fee: <?= $info['renewal_fee'] ?? '?' ?> <?= $info['currency'] ?? '' ?>
                            </div>
                        <?php else: ?>
                            <span class="badge bg-danger">Unavailable</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($info['available']): ?>
                        <a href="buy.php?domain=<?= urlencode($domain) ?>" class="btn btn-success">Buy Now</a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php include get_template('footer'); ?>
