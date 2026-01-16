<?php
use PHPUnit\Framework\TestCase;

// Import the logic file we want to test
require_once __DIR__ . '/../utils/inventory_logic.php';

class InventoryTest extends TestCase {
    
    // Test 1: Verify Negative Stock is Rejected
    public function testNegativeStockThrowsException() {
        // Mock the Database Connection
        $conn = $this->createMock(mysqli::class);
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Stock cannot be negative");

        // Attempt to add stock of -5
        $data = ['part_name' => 'Test', 'quantity' => -5];
        manageInventory($conn, 'add', $data, 1);
    }

    // Test 2: Verify Part Name is Required
    public function testMissingNameThrowsException() {
        $conn = $this->createMock(mysqli::class);
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Part Name is required");

        // Attempt to add with empty name
        $data = ['part_name' => '', 'quantity' => 10];
        manageInventory($conn, 'add', $data, 1);
    }
}
?>