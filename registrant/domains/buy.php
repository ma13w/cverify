<?php
require_once __DIR__ . '/../bootstrap.php';

$domain = $_GET['domain'] ?? '';
$error = null;
$success = null;

// Fetch contacts for the dropdown
try {
    $contactsResponse = $ola->getContacts(1, 100);
    $contacts = $contactsResponse['data'] ?? [];
} catch (Exception $e) {
    // If no contacts found yet or API error, just continue with empty list
    $contacts = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $domainName = $_POST['domain_name'] ?? '';
    // Determine if we are using existing or creating new contact
    $contactMode = $_POST['contact_mode'] ?? 'existing';
    $registrantId = null;

    try {
        if ($contactMode === 'new') {
            // Create new contact first
            $contactData = [
                'name' => $_POST['new_name'] ?? '',
                'email' => $_POST['new_email'] ?? '',
                'phone' => $_POST['new_phone'] ?? '',
                'organization' => !empty($_POST['new_organization']) ? $_POST['new_organization'] : null,
                'address' => $_POST['new_address'] ?? '',
                'city' => $_POST['new_city'] ?? '',
                'postcode' => $_POST['new_postcode'] ?? '',
                'country' => $_POST['new_country'] ?? '',
            ];
            
            // Validate required fields crudely
            if (empty($contactData['name']) || empty($contactData['email']) || empty($contactData['phone']) || 
                empty($contactData['address']) || empty($contactData['city']) || empty($contactData['postcode']) || empty($contactData['country'])) {
                throw new Exception("All contact fields (except Organization) are required.");
            }

            $newContactResponse = $ola->createContact($contactData);
            $registrantId = $newContactResponse['data']['id'] ?? null;
            
            if (!$registrantId) {
                throw new Exception("Failed to retrieve ID for new contact.");
            }

        } else {
            // Use existing
            $registrantId = $_POST['registrant_id'] ?? '';
        }

        if (empty($domainName) || empty($registrantId)) {
            throw new Exception("Domain name and Registrant are required.");
        }

        $payload = [
            'name' => $domainName,
            'registrant' => $registrantId,
        ];
        
        // Nameservers are omitted to use defaults as requested

        $response = $ola->registerDomain($payload);
        $success = "Domain registered successfully! ID: " . ($response['data']['id'] ?? 'Unknown');

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

include get_template('header');
?>

<h2>Register Domain</h2>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success">
        <?= htmlspecialchars($success) ?>
        <p class="mt-2"><a href="manage.php" class="btn btn-sm btn-success">Manage Domains</a></p>
    </div>
<?php else: ?>

    <form method="post" action="" id="purchaseForm">
        <div class="mb-4">
            <label for="domain_name" class="form-label fs-5">Domain Name</label>
            <input type="text" class="form-control form-control-lg" id="domain_name" name="domain_name" value="<?= htmlspecialchars($domain) ?>" readonly required>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                Registrant Identity
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs mb-3" id="contactTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="existing-tab" data-bs-toggle="tab" data-bs-target="#existing-tab-pane" type="button" role="tab" aria-controls="existing-tab-pane" aria-selected="true" onclick="setMode('existing')">Select Existing Contact</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="new-tab" data-bs-toggle="tab" data-bs-target="#new-tab-pane" type="button" role="tab" aria-controls="new-tab-pane" aria-selected="false" onclick="setMode('new')">Create New Contact</button>
                    </li>
                </ul>
                
                <input type="hidden" name="contact_mode" id="contact_mode" value="existing">

                <div class="tab-content" id="contactTabContent">
                    <!-- Existing Contact Tab -->
                    <div class="tab-pane fade show active" id="existing-tab-pane" role="tabpanel" aria-labelledby="existing-tab" tabindex="0">
                        <?php if (empty($contacts)): ?>
                            <p class="text-warning">No existing contacts found. Please proceed to "Create New Contact".</p>
                        <?php else: ?>
                            <div class="mb-3">
                                <label for="registrant_id" class="form-label">Select Contact</label>
                                <select class="form-select" id="registrant_id" name="registrant_id">
                                    <option value="">Select a contact...</option>
                                    <?php foreach ($contacts as $contact): ?>
                                        <option value="<?= htmlspecialchars($contact['id']) ?>">
                                            <?= htmlspecialchars($contact['name']) ?> (<?= htmlspecialchars($contact['organization'] ?? 'Personal') ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- New Contact Tab -->
                    <div class="tab-pane fade" id="new-tab-pane" role="tabpanel" aria-labelledby="new-tab" tabindex="0">
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" name="new_name">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="new_email">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="new_phone" placeholder="+1 555 000 000">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label">Organization (Optional)</label>
                                <input type="text" class="form-control" name="new_organization">
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Address</label>
                            <input type="text" class="form-control" name="new_address">
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-2">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control" name="new_city">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Postcode</label>
                                <input type="text" class="form-control" name="new_postcode">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Country (2-letter ISO)</label>
                                <input type="text" class="form-control" name="new_country" placeholder="US" maxlength="2">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-lg w-100">Complete Registration</button>
    </form>
    
    <script>
        function setMode(mode) {
            document.getElementById('contact_mode').value = mode;
        }
    </script>
<?php endif; ?>

<?php include get_template('footer'); ?>
