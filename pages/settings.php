<?php
require_once '../config/Database.php';
require_once '../db/classes/User.php';
require_once '../db/classes/Session.php';
require_once '../db/classes/Validator.php';

// Start session
Session::start();

// Check if user is logged in
if (!Session::isLoggedIn()) {
    Session::setFlash('error', 'You must be logged in to access settings');
    header('Location: login.php');
    exit;
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get user data
$user = new User($db);
$user_id = Session::get('user_id');
$userData = $user->getUserById($user_id);

// Set user properties
$user->user_id = $user_id;
$user->username = $userData['username'];
$user->email = $userData['email'];
$user->bio = $userData['bio'] ?? '';
$user->signature = $userData['signature'] ?? '';

// Initialize variables
$error = '';
$success = '';
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Create validator
    $validator = new Validator();
    
    // Get form data
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $bio = $_POST['bio'] ?? '';
    $signature = $_POST['signature'] ?? '';
    
    // Validate form data
    $validator->required('username', $username);
    $validator->minLength('username', $username, 3);
    $validator->maxLength('username', $username, 50);
    $validator->usernameFormat('username', $username);
    
    $validator->required('email', $email);
    $validator->email('email', $email);
    $validator->maxLength('email', $email, 100);
    
    // Check if username already exists (but not current user's)
    if ($username !== $user->username && $user->isUsernameExists($username)) {
        $validator->addError('username', 'Username already exists');
    }
    
    // Check if email already exists (but not current user's)
    if ($email !== $user->email && $user->isEmailExists($email)) {
        $validator->addError('email', 'Email already exists');
    }
    
    // If no validation errors, update the profile
    if (!$validator->hasErrors()) {
        $user->username = $username;
        $user->email = $email;
        $user->bio = $bio;
        $user->signature = $signature;
        
        if ($user->updateProfile()) {
            // Update session data
            Session::set('username', $username);
            Session::set('email', $email);
            
            $success = 'Profile updated successfully!';
        } else {
            $error = 'An error occurred while updating your profile. Please try again.';
        }
    } else {
        $error = $validator->getFirstError();
    }
    
    $tab = 'profile';
}

// Handle password update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    // Create validator
    $validator = new Validator();
    
    // Get form data
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate form data
    $validator->required('current_password', $current_password, 'Current password');
    $validator->required('new_password', $new_password, 'New password');
    $validator->passwordStrength('new_password', $new_password, 'New password');
    $validator->required('confirm_password', $confirm_password, 'Confirm password');
    $validator->passwordMatch($new_password, $confirm_password);
    
    // Verify current password
    if (!$validator->hasErrors() && !password_verify($current_password, $userData['password'])) {
        $validator->addError('current_password', 'Current password is incorrect');
    }
    
    // If no validation errors, update the password
    if (!$validator->hasErrors()) {
        if ($user->updatePassword($new_password)) {
            $success = 'Password updated successfully!';
        } else {
            $error = 'An error occurred while updating your password. Please try again.';
        }
    } else {
        $error = $validator->getFirstError();
    }
    
    $tab = 'password';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once("../parts/head.php")?>
    <title>Settings - CodeHub</title>
</head>
<body>
    <?php require "../parts/header.php" ?>
    
    <main>
        <div class="container py-5">
            <div class="row">
                <!-- Sidebar -->
                <div class="col-md-3">
                    <div class="card shadow mb-4">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Settings</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <a href="?tab=profile" class="list-group-item list-group-item-action <?php echo $tab === 'profile' ? 'active' : ''; ?>">
                                    <i class="fas fa-user me-2"></i>Profile
                                </a>
                                <a href="?tab=password" class="list-group-item list-group-item-action <?php echo $tab === 'password' ? 'active' : ''; ?>">
                                    <i class="fas fa-lock me-2"></i>Password
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card shadow">
                        <div class="card-body">
                            <h6 class="card-title"><i class="fas fa-info-circle me-2"></i>Note</h6>
                            <p class="card-text small">Keep your profile information up to date to help the community know you better.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Main Content -->
                <div class="col-md-9">
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
                    
                    <!-- Profile Settings -->
                    <?php if ($tab === 'profile'): ?>
                    <div class="card shadow">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="fas fa-user me-2"></i>Profile Settings</h5>
                        </div>
                        <div class="card-body">
                            <form action="settings.php?tab=profile" method="post">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user->username); ?>" required>
                                    <div class="form-text">Username can only contain letters, numbers, and underscores.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user->email); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="bio" class="form-label">Bio</label>
                                    <textarea class="form-control" id="bio" name="bio" rows="3"><?php echo htmlspecialchars($user->bio); ?></textarea>
                                    <div class="form-text">Tell the community about yourself.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="signature" class="form-label">Signature</label>
                                    <textarea class="form-control" id="signature" name="signature" rows="2"><?php echo htmlspecialchars($user->signature); ?></textarea>
                                    <div class="form-text">Your signature will appear at the bottom of your posts.</div>
                                </div>
                                <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Password Settings -->
                    <?php if ($tab === 'password'): ?>
                    <div class="card shadow">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="fas fa-lock me-2"></i>Password Settings</h5>
                        </div>
                        <div class="card-body">
                            <form action="settings.php?tab=password" method="post">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                    <div class="form-text">Password must be at least 8 characters and contain uppercase, lowercase and numbers.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                                <button type="submit" name="update_password" class="btn btn-primary">Change Password</button>
                            </form>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    
    <?php require "../parts/footer.php" ?>
</body>
</html>