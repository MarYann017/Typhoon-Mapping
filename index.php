<?php
require_once __DIR__ . '/app/Helper.php';

// Check if user is authenticated
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/components/topNav.php';
require_once __DIR__ . '/components/header.php';
require_once __DIR__ . '/components/sideNav.php';
?>
<title>Dashboard</title>
<main id="main" class="main">
    <section class="section">
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="card" style="height: 80vh;">
                    <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-5">
                        <h4 class="fw-bold">Welcome to Typhoon Evacuation Mapping Platform</h4>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/components/footer.php'; ?>

</body>

</html>
