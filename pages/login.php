<?php
require_once '../config/Database.php';
require_once '../db/classes/User.php';
require_once '../db/classes/Session.php';
require_once '../db/classes/Validator.php';

// Start session
Session::start();

// Check if user is already logged in
if (Session::isLoggedIn()) {
    header('Location: /codehub/index.php');
    exit;
}

// Initialize variables
$username = '';
$password = '';
$error = '';
$success = '';

// Check for flash messages
if (Session::hasFlash('error')) {
    $error = Session::getFlash('error');
}
if (Session::hasFlash('success')) {
    $success = Session::getFlash('success');
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get database connection
    $database = new Database();
    $db = $database->getConnection();
    
    // Create validator
    $validator = new Validator();
    
    // Get form data
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Validate form data
    $validator->required('username', $username);
    $validator->required('password', $password);
    
    // If no validation errors, attempt login
    if (!$validator->hasErrors()) {
        // Create user object
        $user = new User($db);
        
        // Attempt login
        if ($user->login($username, $password)) {
            // Set user as logged in
            Session::setUserLoggedIn($user);
            
            // Redirect to index page
            header('Location: /codehub/index.php');
            exit;
        } else {
            $error = 'Invalid username or password';
        }
    } else {
        $error = $validator->getFirstError();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once("../parts/head.php")?>
    <title>Login - CodeHub</title>
</head>
<body>
    <?php require "../parts/header.php" ?>
    
    <main>
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card shadow">
                        <div class="card-header bg-dark text-white">
                            <h4 class="mb-0"><i class="fas fa-sign-in-alt me-2"></i>Login</h4>
                        </div>
                        <div class="card-body">
                            <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo $error; ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($success): ?>
                            <div class="alert alert-success" role="alert">
                                <?php echo $success; ?>
                            </div>
                            <?php endif; ?>
                            
                            <form action="login.php" method="post">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <a href="forgot-password.php">Forgot password?</a>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Login</button>
                            </form>
                        </div>
                        <div class="card-footer text-center">
                            <p class="mb-0">Don't have an account? <a href="register.php">Register here</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <?php require "../parts/footer.php" ?>
</body>
</html>