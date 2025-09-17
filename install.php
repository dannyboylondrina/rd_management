<?php
/**
 * R&D Management System - Installation Script
 * 
 * This script creates the database and tables required for the R&D Management System.
 */

// Set page title
$pageTitle = "R&D Management System - Installation";

// Initialize variables
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

// Database configuration
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'rd_management';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['step']) && $_POST['step'] == '1') {
        // Step 1: Create database
        try {
            // Connect to MySQL without selecting a database
            $conn = new PDO("mysql:host=$dbHost", $dbUser, $dbPass);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Create database
            $sql = "CREATE DATABASE IF NOT EXISTS $dbName";
            $conn->exec($sql);
            
            $success = "Database created successfully.";
            $step = 2;
        } catch(PDOException $e) {
            $error = "Database creation failed: " . $e->getMessage();
        }
    } elseif (isset($_POST['step']) && $_POST['step'] == '2') {
        // Step 2: Create tables
        try {
            // Connect to the database
            $conn = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Read SQL file
            $sql = file_get_contents('config/database.sql');
            
            // Execute SQL
            $conn->exec($sql);
            
            $success = "Tables created successfully.";
            $step = 3;
        } catch(PDOException $e) {
            $error = "Table creation failed: " . $e->getMessage();
        }
    } elseif (isset($_POST['step']) && $_POST['step'] == '3') {
        // Step 3: Create admin user
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $email = $_POST['email'] ?? '';
        $firstName = $_POST['first_name'] ?? '';
        $lastName = $_POST['last_name'] ?? '';
        
        // Validate form data
        if (empty($username) || empty($password) || empty($email) || empty($firstName) || empty($lastName)) {
            $error = "All fields are required.";
        } else {
            try {
                // Connect to the database
                $conn = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Hash password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert admin user
                $sql = "INSERT INTO users (username, password, email, first_name, last_name, role_id, department_id) 
                        VALUES (:username, :password, :email, :first_name, :last_name, 1, 1)";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':password', $hashedPassword);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':first_name', $firstName);
                $stmt->bindParam(':last_name', $lastName);
                $stmt->execute();
                
                $success = "Admin user created successfully. Installation complete!";
                $step = 4;
            } catch(PDOException $e) {
                $error = "Admin user creation failed: " . $e->getMessage();
            }
        }
    }
}

// Check database connection
$dbConnectionStatus = false;
$dbTablesStatus = false;

try {
    $conn = new PDO("mysql:host=$dbHost", $dbUser, $dbPass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $dbConnectionStatus = true;
    
    // Check if database exists
    $stmt = $conn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbName'");
    if ($stmt->rowCount() > 0) {
        // Connect to the database
        $conn = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
        
        // Check if tables exist
        $stmt = $conn->query("SHOW TABLES");
        $dbTablesStatus = $stmt->rowCount() > 0;
    }
} catch(PDOException $e) {
    // Connection failed
}

// Adjust step based on database status
if ($step == 1 && $dbConnectionStatus && $dbTablesStatus) {
    $step = 3; // Skip to admin user creation if database and tables already exist
} elseif ($step == 1 && $dbConnectionStatus && !$dbTablesStatus) {
    $step = 2; // Skip to table creation if database exists but tables don't
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 40px;
            padding-bottom: 40px;
        }
        .install-container {
            max-width: 700px;
            margin: 0 auto;
            padding: 15px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border: none;
        }
        .card-header {
            background-color: #007bff;
            color: white;
            text-align: center;
            border-radius: 10px 10px 0 0 !important;
            padding: 20px;
        }
        .system-logo {
            font-size: 3rem;
            margin-bottom: 10px;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
        }
        .step-indicator::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            right: 0;
            height: 2px;
            background-color: #e9ecef;
            z-index: 0;
        }
        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            position: relative;
            z-index: 1;
        }
        .step.active {
            background-color: #007bff;
            color: white;
        }
        .step.completed {
            background-color: #28a745;
            color: white;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="card">
            <div class="card-header">
                <div class="system-logo">
                    <i class="fas fa-flask"></i>
                </div>
                <h4 class="mb-0">R&D Management System - Installation</h4>
            </div>
            <div class="card-body p-4">
                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div class="step <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'completed' : ''; ?>">1</div>
                    <div class="step <?php echo $step >= 2 ? 'active' : ''; ?> <?php echo $step > 2 ? 'completed' : ''; ?>">2</div>
                    <div class="step <?php echo $step >= 3 ? 'active' : ''; ?> <?php echo $step > 3 ? 'completed' : ''; ?>">3</div>
                    <div class="step <?php echo $step >= 4 ? 'active' : ''; ?>">4</div>
                </div>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success" role="alert">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($step == 1): ?>
                    <!-- Step 1: Create Database -->
                    <h5 class="mb-4">Step 1: Create Database</h5>
                    <p>This step will create the database for the R&D Management System.</p>
                    
                    <div class="mb-4">
                        <h6>Database Configuration:</h6>
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Host
                                <span><?php echo htmlspecialchars($dbHost); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Username
                                <span><?php echo htmlspecialchars($dbUser); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Password
                                <span>********</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Database Name
                                <span><?php echo htmlspecialchars($dbName); ?></span>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="mb-4">
                        <h6>System Requirements:</h6>
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                PHP Version (>= 7.4)
                                <span class="<?php echo version_compare(PHP_VERSION, '7.4.0', '>=') ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo PHP_VERSION; ?>
                                    <?php echo version_compare(PHP_VERSION, '7.4.0', '>=') ? '<i class="fas fa-check"></i>' : '<i class="fas fa-times"></i>'; ?>
                                </span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                PDO Extension
                                <span class="<?php echo extension_loaded('pdo') ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo extension_loaded('pdo') ? 'Enabled' : 'Disabled'; ?>
                                    <?php echo extension_loaded('pdo') ? '<i class="fas fa-check"></i>' : '<i class="fas fa-times"></i>'; ?>
                                </span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                MySQL PDO Extension
                                <span class="<?php echo extension_loaded('pdo_mysql') ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo extension_loaded('pdo_mysql') ? 'Enabled' : 'Disabled'; ?>
                                    <?php echo extension_loaded('pdo_mysql') ? '<i class="fas fa-check"></i>' : '<i class="fas fa-times"></i>'; ?>
                                </span>
                            </li>
                        </ul>
                    </div>
                    
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <input type="hidden" name="step" value="1">
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary" <?php echo (!extension_loaded('pdo') || !extension_loaded('pdo_mysql')) ? 'disabled' : ''; ?>>
                                Create Database
                            </button>
                        </div>
                    </form>
                <?php elseif ($step == 2): ?>
                    <!-- Step 2: Create Tables -->
                    <h5 class="mb-4">Step 2: Create Tables</h5>
                    <p>This step will create the necessary tables in the database.</p>
                    
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <input type="hidden" name="step" value="2">
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Create Tables</button>
                        </div>
                    </form>
                <?php elseif ($step == 3): ?>
                    <!-- Step 3: Create Admin User -->
                    <h5 class="mb-4">Step 3: Create Admin User</h5>
                    <p>Create an administrator account to manage the R&D Management System.</p>
                    
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <input type="hidden" name="step" value="3">
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Create Admin User</button>
                        </div>
                    </form>
                <?php elseif ($step == 4): ?>
                    <!-- Step 4: Installation Complete -->
                    <h5 class="mb-4">Step 4: Installation Complete</h5>
                    <div class="text-center mb-4">
                        <i class="fas fa-check-circle text-success fa-5x"></i>
                    </div>
                    <p class="text-center">The R&D Management System has been successfully installed!</p>
                    <p class="text-center">You can now log in using the administrator account you created.</p>
                    
                    <div class="d-grid">
                        <a href="login.php" class="btn btn-primary">Go to Login Page</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="text-center mt-4 text-muted">
            <small>&copy; <?php echo date('Y'); ?> R&D Management System</small>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>