<?php
    session_start();

    if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in']) {
        header('Location: index.php');
        exit();
    }

    require_once __DIR__ . '/app/Db.php';
    require_once __DIR__ . '/app/Helper.php';
    require_once __DIR__ . '/app/Accounts.php';

    $error_message = '';
    $username_value = '';

    // Get error message and username from session if they exist
    if (isset($_SESSION['login_error'])) {
        $error_message = $_SESSION['login_error'];
        unset($_SESSION['login_error']);
    }

    if (isset($_SESSION['login_username'])) {
        $username_value = $_SESSION['login_username'];
        unset($_SESSION['login_username']);
    }

    $accounts = new Accounts();
    $accounts->handleRequest();
?>

<!DOCTYPE html>
<html lang="en">
<title>Login</title>
<?php require_once __DIR__ . '/components/head.php'; ?>

<body style="background-color: #FAFAFA;">
    <main>
        <div class="container">
            <section
                class="section register min-vh-100 d-flex flex-column align-items-center justify-content-center py-4">
                <div class="container">
                    <div class="row justify-content-center">
                        <div class="col-lg-4 col-md-6 d-flex flex-column align-items-center justify-content-center">

                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="pt-4 pb-2">
                                        <h5 class="card-title text-center text-black pb-0 fs-4">Login to Your Account
                                        </h5>
                                        <p class="text-center small text-black">Enter your username & password to login
                                        </p>
                                    </div>

                                    <?php if (!empty($error_message)): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                        <?php echo htmlspecialchars($error_message); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                    <?php endif; ?>

                                    <form class="row g-3 needs-validation" method="POST" action="" novalidate>
                                        <div class="col-12">
                                            <label for="username" class="form-label text-black">Username</label>
                                            <div class="input-group has-validation">
                                                <input type="text" name="username" class="form-control shadow-none"
                                                    id="username" value="<?php echo htmlspecialchars($username_value); ?>" required>
                                                <div class="invalid-feedback text-black">Please enter your username.
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <label for="password" class="form-label text-black">Password</label>
                                            <div class="input-group has-validation">
                                                <input type="password" name="password" class="form-control shadow-none"
                                                    id="password" required>
                                                <button class="btn btn-outline-secondary text-white bg-black border-0"
                                                    type="button" id="togglePassword">
                                                    <i class="bi bi-eye" id="eyeIcon"></i>
                                                </button>
                                                <div class="invalid-feedback text-black">Please enter your password!
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <button class="btn btn-primary bg-black border-0 w-100"
                                                type="submit">Login</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <?php require_once __DIR__ . '/components/footer.php'; ?>

    <script>
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('bi-eye');
                eyeIcon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('bi-eye-slash');
                eyeIcon.classList.add('bi-eye');
            }
        });
    </script>
</body>

</html>
