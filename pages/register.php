<?php
require_once '../config/Database.php';
require_once '../db/classes/User.php';
require_once '../db/classes/Session.php';
require_once '../db/classes/Validator.php';

// Start session
Session::start();

// Check if user is already logged in
if (Session::isLoggedIn()) {
    header('Location: http://localhost/codehub/index.php');
    exit;
}

// Initialize variables
$username = '';
$email = '';
$password = '';
$password_confirm = '';
$error = '';

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get database connection
    $database = new Database();
    $db = $database->getConnection();
    
    // Create validator
    $validator = new Validator();
    
    // Get form data
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    // Validate form data
    $validator->required('username', $username);
    $validator->minLength('username', $username, 3);
    $validator->maxLength('username', $username, 50);
    $validator->usernameFormat('username', $username);
    
    $validator->required('email', $email);
    $validator->email('email', $email);
    $validator->maxLength('email', $email, 100);
    
    $validator->required('password', $password);
    $validator->passwordStrength('password', $password);
    
    $validator->required('password_confirm', $password_confirm, 'Password confirmation');
    $validator->passwordMatch($password, $password_confirm);
    
    // If no validation errors, check if username or email already exists
    if (!$validator->hasErrors()) {
        // Create user object
        $user = new User($db);
        
        // Check if username already exists
        if ($user->isUsernameExists($username)) {
            $validator->addError('username', 'Username already exists');
        }
        
        // Check if email already exists
        if ($user->isEmailExists($email)) {
            $validator->addError('email', 'Email already exists');
        }
    }
    
    // If still no errors, create the account
    if (!$validator->hasErrors()) {
        // Create new user
        $user = new User($db);
        $user->username = $username;
        $user->email = $email;
        $user->password = $password;
        
        // Register user
        if ($user->register()) {
            // Set success message
            Session::setFlash('success', 'Your account has been created successfully! You can now login.');
            
            // Redirect to login page
            header('Location: login.php');
            exit;
        } else {
            $error = 'An error occurred while creating your account. Please try again.';
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
    <title>Register - CodeHub</title>
</head>
<body>
    <?php require "../parts/header.php" ?>
    
    <main>
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card shadow">
                        <div class="card-header bg-dark text-white">
                            <h4 class="mb-0"><i class="fas fa-user-plus me-2"></i>Create an Account</h4>
                        </div>
                        <div class="card-body">
                            <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo $error; ?>
                            </div>
                            <?php endif; ?>
                            
                            <form action="register.php" method="post">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                                    <div class="form-text">Username can only contain letters, numbers, and underscores.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <div class="form-text">Password must be at least 8 characters and contain uppercase, lowercase and numbers.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="password_confirm" class="form-label">Confirm Password</label>
                                    <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">Register</button>
                            </form>
                        </div>
                        <div class="card-footer text-center">
                            <p class="mb-0">Already have an account? <a href="login.php">Login here</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <?php require "../parts/footer.php" ?>
</body>
</html>