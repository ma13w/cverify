<?php
require_once __DIR__ . '/../bootstrap.php';

$domainId = $_GET['domain_id'] ?? null;
$domainName = $_GET['domain_name'] ?? 'Unknown Domain';
$zoneId = $_GET['zone_id'] ?? null;

$error = null;
$success = null;
$records = [];
$zone = null;

try {
    // Resolve Zone ID if we only have Domain ID
    if ($domainId && !$zoneId) {
        $zoneResponse = $ola->getDomainZone($domainId);
        if (empty($zoneResponse['data']['id'])) {
            throw new Exception("DNS Zone not found for this domain.");
        }
        $zone = $zoneResponse['data'];
        $zoneId = $zone['id'];
    } elseif ($zoneId) {
        $zoneResponse = $ola->getZone($zoneId);
        $zone = $zoneResponse['data'];
        $domainName = $zone['name'];
    } else {
        throw new Exception("No Domain ID or Zone ID provided.");
    }

    // Handle Actions (Add/Delete)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $recordData = [
                'type' => $_POST['type'],
                'name' => $_POST['name'],
                'content' => $_POST['content'],
                'ttl' => (int)$_POST['ttl'],
            ];

            // Only add priority if it is set and not empty string (allows 0)
            if (isset($_POST['priority']) && $_POST['priority'] !== '') {
                $recordData['priority'] = (int)$_POST['priority'];
            }

            $ola->createZoneRecord($zoneId, $recordData);
            $success = "Record created successfully.";
        } elseif ($action === 'delete') {
            $recordId = $_POST['record_id'];
            $ola->deleteZoneRecord($zoneId, $recordId);
            $success = "Record deleted successfully.";
        }
    }

    // Fetch Records
    $recordsResponse = $ola->getZoneRecords($zoneId, 1, 100);
    $records = $recordsResponse['data'] ?? [];

} catch (Exception $e) {
    $error = $e->getMessage();
}

include get_template('header');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>DNS Management: <?= htmlspecialchars($domainName) ?></h2>
    <a href="../domains/manage.php" class="btn btn-outline-secondary">Back to Domains</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if ($zoneId): ?>

    <!-- Add Record Form -->
    <div class="card mb-4">
        <div class="card-header">Add New Record</div>
        <div class="card-body">
            <form method="post" action="" class="row g-3">
                <input type="hidden" name="action" value="create">
                <div class="col-md-2">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select" required>
                        <option value="A">A</option>
                        <option value="AAAA">AAAA</option>
                        <option value="CNAME">CNAME</option>
                        <option value="MX">MX</option>
                        <option value="TXT">TXT</option>
                        <option value="NS">NS</option>
                        <option value="SRV">SRV</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" placeholder="@ or subdomain" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Content</label>
                    <input type="text" name="content" class="form-control" placeholder="1.2.3.4" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">TTL</label>
                    <input type="number" name="ttl" class="form-control" value="3600" required>
                </div>
                <div class="col-md-1">
                    <label class="form-label">Priority</label>
                    <input type="number" name="priority" class="form-control" placeholder="10">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Add Record</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Records List -->
    <div class="card">
        <div class="card-header">DNS Records</div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Name</th>
                        <th>Content</th>
                        <th>TTL</th>
                        <th>Priority</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                        <tr><td colspan="6" class="text-center">No records found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($records as $record): ?>
                            <tr>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($record['type']) ?></span></td>
                                <td><?= htmlspecialchars($record['name']) ?></td>
                                <td class="text-break" style="max-width: 300px;"><?= htmlspecialchars($record['content']) ?></td>
                                <td><?= htmlspecialchars($record['ttl']) ?></td>
                                <td><?= htmlspecialchars($record['priority'] ?? '-') ?></td>
                                <td>
                                    <form method="post" action="" onsubmit="return confirm('Are you sure?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="record_id" value="<?= htmlspecialchars($record['id']) ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>

<?php include get_template('footer'); ?>
