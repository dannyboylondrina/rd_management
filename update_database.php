<?php
/**
 * Database Schema Update Script
 * 
 * This script executes SQL statements to update the database schema.
 * It should be run once to apply the necessary changes.
 */

// Include database configuration
require_once 'config/database.php';

// Initialize database connection
$database = new Database();
$conn = $database->getConnection();

// Check if connection was successful
if (!$conn) {
    die("Database connection failed: " . $database->getError());
}

// Define SQL files to process
$sqlFiles = [
    'config/update_departments_table.sql' => [
        'table' => 'departments',
        'columns' => [
            'location' => 'VARCHAR(255)',
            'contact_email' => 'VARCHAR(100)',
            'contact_phone' => 'VARCHAR(50)'
        ]
    ],
    'config/update_resources_table.sql' => [
        'table' => 'resources',
        'columns' => [
            'unit' => 'VARCHAR(50)',
            'location' => 'VARCHAR(255)'
        ]
    ],
    'config/update_resources_is_available.sql' => [
        'table' => 'resources',
        'columns' => [
            'is_available' => 'BOOLEAN'
        ]
    ]
];

// Execute SQL statements
try {
    // Start transaction
    $conn->beginTransaction();
    
    $updatedTables = [];
    
    // Process each SQL file
    foreach ($sqlFiles as $sqlFile => $tableInfo) {
        // Read SQL file
        $sql = file_get_contents($sqlFile);
        
        if ($sql === false) {
            throw new Exception("Error reading SQL file: $sqlFile");
        }
        
        // Execute SQL
        $result = $conn->exec($sql);
        
        // Add to updated tables
        $updatedTables[] = $tableInfo;
    }
    
    // Commit transaction
    $conn->commit();
    
    // Display success message
    echo "<h1>Database Update Successful</h1>";
    echo "<p>The database schema has been updated successfully.</p>";
    
    // Display details for each updated table
    foreach ($updatedTables as $tableInfo) {
        echo "<p>The following columns have been added to the {$tableInfo['table']} table:</p>";
        echo "<ul>";
        foreach ($tableInfo['columns'] as $column => $type) {
            echo "<li>$column - $type</li>";
        }
        echo "</ul>";
    }
    
    echo "<p><a href='index.php'>Go to Dashboard</a></p>";
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollBack();
    
    echo "<h1>Database Update Failed</h1>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Please contact the system administrator.</p>";
}
?>