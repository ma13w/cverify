<?php
require_once __DIR__ . '/bootstrap.php';

include get_template('header');
?>

<div class="row align-items-md-stretch">
    <div class="col-md-6">
        <div class="h-100 p-5 text-white bg-dark rounded-3">
            <h2>Find your .cv Identity</h2>
            <p>Search and register your perfect .cv domain name today. Secure your brand on the CV network.</p>
            <a href="domains/search.php" class="btn btn-outline-light" type="button">Check Availability</a>
        </div>
    </div>
    <div class="col-md-6">
        <div class="h-100 p-5 bg-light border rounded-3">
            <h2>Manage Portfolio</h2>
            <p>Manage your DNS zones, records, and contact information for all your registered domains.</p>
            <a href="domains/manage.php" class="btn btn-outline-secondary" type="button">Manage Domains</a>
        </div>
    </div>
</div>

<?php include get_template('footer'); ?>
