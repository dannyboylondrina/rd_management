<?php
/**
 * User Class
 * 
 * This class handles user-related operations
 */

require_once __DIR__ . '/BaseModel.php';

class User extends BaseModel {
    // Table name
    protected $table_name = 'users';
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * Validate user data
     * 
     * @param array $data User data
     * @param int|null $id User ID for update operations
     * @return bool Validation result
     */
    protected function validate($data, $id = null) {
        $this->errors = [];
        
        // Username validation
        if (isset($data['username'])) {
            if (empty($data['username'])) {
                $this->errors[] = "Username is required";
            } elseif (strlen($data['username']) < 3) {
                $this->errors[] = "Username must be at least 3 characters";
            } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $data['username'])) {
                $this->errors[] = "Username can only contain letters, numbers, and underscores";
            } else {
                // Check if username is unique
                $query = "SELECT id FROM " . $this->table_name . " WHERE username = :username";
                if ($id) {
                    $query .= " AND id != :id";
                }
                
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':username', $data['username']);
                
                if ($id) {
                    $stmt->bindParam(':id', $id);
                }
                
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $this->errors[] = "Username already exists";
                }
            }
        }
        
        // Email validation
        if (isset($data['email'])) {
            if (empty($data['email'])) {
                $this->errors[] = "Email is required";
            } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $this->errors[] = "Invalid email format";
            } else {
                // Check if email is unique
                $query = "SELECT id FROM " . $this->table_name . " WHERE email = :email";
                if ($id) {
                    $query .= " AND id != :id";
                }
                
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':email', $data['email']);
                
                if ($id) {
                    $stmt->bindParam(':id', $id);
                }
                
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $this->errors[] = "Email already exists";
                }
            }
        }
        
        // Password validation for new users or password updates
        if (isset($data['password']) && (!$id || !empty($data['password']))) {
            if (empty($data['password'])) {
                $this->errors[] = "Password is required";
            } elseif (strlen($data['password']) < 6) {
                $this->errors[] = "Password must be at least 6 characters";
            }
        }
        
        // First name validation
        if (isset($data['first_name']) && empty($data['first_name'])) {
            $this->errors[] = "First name is required";
        }
        
        // Last name validation
        if (isset($data['last_name']) && empty($data['last_name'])) {
            $this->errors[] = "Last name is required";
        }
        
        return empty($this->errors);
    }
    
    /**
     * Create a new user
     * 
     * @param array $data User data
     * @return int|false ID of the new user or false on failure
     */
    public function create($data) {
        // Hash password
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        return parent::create($data);
    }
    
    /**
     * Update an existing user
     * 
     * @param int $id User ID
     * @param array $data User data
     * @return bool Success or failure
     */
    public function update($id, $data) {
        // Hash password if provided
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        } elseif (isset($data['password']) && empty($data['password'])) {
            // If password is empty, remove it from the data array
            unset($data['password']);
        }
        
        return parent::update($id, $data);
    }
    
    /**
     * Authenticate a user
     * 
     * @param string $username Username
     * @param string $password Password
     * @return array|false User data or false on failure
     */
    public function authenticate($username, $password) {
        // Normalize input
        $input = trim($username);

        // Try fetch by username or email (case-insensitive)
        $query = "SELECT * FROM " . $this->table_name . " WHERE username = :u OR email = :u LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':u', $input);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        // Minimal auth logging (no sensitive data)
        error_log('[Auth] Lookup for input="' . $input . '" foundUser=' . ($user ? '1' : '0'));

        if ($user) {
            // Verify bcrypt/argon hash
            if (!empty($user['password']) && password_verify($password, $user['password'])) {
                error_log('[Auth] password_verify success for userId=' . $user['id']);
                return $user;
            }

            // Legacy plaintext support: if stored password is not hashed and matches, auto-upgrade hash
            if (!self::isPasswordHashed($user['password']) && hash_equals((string)$user['password'], (string)$password)) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $upd = $this->conn->prepare("UPDATE " . $this->table_name . " SET password = :p WHERE id = :id");
                $upd->bindParam(':p', $newHash);
                $upd->bindParam(':id', $user['id']);
                $upd->execute();
                error_log('[Auth] Upgraded legacy password hash for userId=' . $user['id']);
                // Refresh user row with new hash
                $user['password'] = $newHash;
                return $user;
            }
        }

        return false;
    }

    /**
     * Determine if a stored password string looks like a secure hash
     */
    private static function isPasswordHashed($stored) {
        if (!is_string($stored) || $stored === '') return false;
        // Common PHP password_hash prefixes: $2y$ (bcrypt), $argon2i$, $argon2id$
        return (strpos($stored, '$2y$') === 0) || (strpos($stored, '$argon2') === 0);
    }
    
    /**
     * Get user by username
     * 
     * @param string $username Username
     * @return array|false User data or false if not found
     */
    public function getByUsername($username) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE username = :username LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get user by email
     * 
     * @param string $email Email
     * @return array|false User data or false if not found
     */
    public function getByEmail($email) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get users by department
     * 
     * @param int $departmentId Department ID
     * @return array Array of users
     */
    public function getByDepartment($departmentId) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE department_id = :department_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':department_id', $departmentId);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get users by role
     * 
     * @param int $roleId Role ID
     * @return array Array of users
     */
    public function getByRole($roleId) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE role_id = :role_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':role_id', $roleId);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Change user password
     * 
     * @param int $id User ID
     * @param string $currentPassword Current password
     * @param string $newPassword New password
     * @return bool Success or failure
     */
    public function changePassword($id, $currentPassword, $newPassword) {
        // Get user
        $user = $this->getById($id);
        
        if (!$user) {
            $this->errors[] = "User not found";
            return false;
        }
        
        // Verify current password
        if (!password_verify($currentPassword, $user['password'])) {
            $this->errors[] = "Current password is incorrect";
            return false;
        }
        
        // Validate new password
        if (strlen($newPassword) < 6) {
            $this->errors[] = "New password must be at least 6 characters";
            return false;
        }
        
        // Update password
        $data = [
            'password' => password_hash($newPassword, PASSWORD_DEFAULT)
        ];
        
        $query = "UPDATE " . $this->table_name . " SET password = :password WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':password', $data['password']);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            return true;
        }
        
        $this->errors[] = "Database error: " . implode(', ', $stmt->errorInfo());
        return false;
    }

    /**
     * Admin-set a user's password (no current password required)
     * Also ensures the user is active if desired.
     */
    public function adminSetPassword($id, $newPassword, $activate = true) {
        if (strlen($newPassword) < 6) {
            $this->errors[] = "New password must be at least 6 characters";
            return false;
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $query = "UPDATE " . $this->table_name . " SET password = :password" . ($activate ? ", is_active = 1" : "") . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':password', $hash);
        $stmt->bindParam(':id', $id);
        if ($stmt->execute()) {
            return true;
        }
        $this->errors[] = "Database error: " . implode(', ', $stmt->errorInfo());
        return false;
    }
}
?>