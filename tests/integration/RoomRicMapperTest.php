<?php

namespace ChaosPagerEventInfos\Tests\Integration;

use PHPUnit\Framework\TestCase;
use ChaosPagerEventInfos\RoomRicMapper;
use ChaosPagerEventInfos\Config;

/**
 * Integration tests for RoomRicMapper
 */
class RoomRicMapperTest extends TestCase
{
    protected function setUp(): void
    {
        // Ensure Config is loaded
        if (!Config::has('TEST_MODE')) {
            Config::load(__DIR__ . '/../../.env.test');
        }
    }

    /**
     * Test getRicForRoom() returns correct RIC for each room
     */
    public function testGetRicForRoomReturnsCorrectRic(): void
    {
        // Test Zero room
        $ric = RoomRicMapper::getRicForRoom('Zero');
        $this->assertNotNull($ric);
        $this->assertIsInt($ric);
        $this->assertGreaterThan(0, $ric);

        // Test One room
        $ric = RoomRicMapper::getRicForRoom('One');
        $this->assertNotNull($ric);
        $this->assertIsInt($ric);
        $this->assertGreaterThan(0, $ric);

        // Test Ground room
        $ric = RoomRicMapper::getRicForRoom('Ground');
        $this->assertNotNull($ric);
        $this->assertIsInt($ric);
        $this->assertGreaterThan(0, $ric);

        // Test Fuse room
        $ric = RoomRicMapper::getRicForRoom('Fuse');
        $this->assertNotNull($ric);
        $this->assertIsInt($ric);
        $this->assertGreaterThan(0, $ric);
    }

    /**
     * Test getRicForRoom() returns correct default RIC values
     */
    public function testGetRicForRoomReturnsDefaultValues(): void
    {
        // These tests verify default values are used when config is not set
        // Default values: Zero→1140, One→1141, Ground→1142, Fuse→1143
        
        $zeroRic = RoomRicMapper::getRicForRoom('Zero');
        $oneRic = RoomRicMapper::getRicForRoom('One');
        $groundRic = RoomRicMapper::getRicForRoom('Ground');
        $fuseRic = RoomRicMapper::getRicForRoom('Fuse');

        // Verify all RICs are different
        $this->assertNotEquals($zeroRic, $oneRic);
        $this->assertNotEquals($zeroRic, $groundRic);
        $this->assertNotEquals($zeroRic, $fuseRic);
        $this->assertNotEquals($oneRic, $groundRic);
        $this->assertNotEquals($oneRic, $fuseRic);
        $this->assertNotEquals($groundRic, $fuseRic);
    }

    /**
     * Test getRicForRoom() returns null for invalid rooms
     */
    public function testGetRicForRoomReturnsNullForInvalidRooms(): void
    {
        // Test invalid room names
        $this->assertNull(RoomRicMapper::getRicForRoom('InvalidRoom'));
        $this->assertNull(RoomRicMapper::getRicForRoom(''));
        $this->assertNull(RoomRicMapper::getRicForRoom('SmallRoom'));
        $this->assertNull(RoomRicMapper::getRicForRoom('one')); // case-sensitive
    }

    /**
     * Test getAllRoomsRic() returns correct RIC
     */
    public function testGetAllRoomsRicReturnsCorrectRic(): void
    {
        // Reset config to ensure default values are used
        Config::reset();
        
        $ric = RoomRicMapper::getAllRoomsRic();
        $this->assertIsInt($ric);
        $this->assertEquals(1150, $ric); // Default value
        $this->assertGreaterThan(0, $ric);
    }

    /**
     * Test isValidRoom() returns true for valid rooms
     */
    public function testIsValidRoomReturnsTrueForValidRooms(): void
    {
        $this->assertTrue(RoomRicMapper::isValidRoom('Zero'));
        $this->assertTrue(RoomRicMapper::isValidRoom('One'));
        $this->assertTrue(RoomRicMapper::isValidRoom('Ground'));
        $this->assertTrue(RoomRicMapper::isValidRoom('Fuse'));
    }

    /**
     * Test isValidRoom() returns false for invalid rooms
     */
    public function testIsValidRoomReturnsFalseForInvalidRooms(): void
    {
        $this->assertFalse(RoomRicMapper::isValidRoom('InvalidRoom'));
        $this->assertFalse(RoomRicMapper::isValidRoom(''));
        $this->assertFalse(RoomRicMapper::isValidRoom('SmallRoom'));
        $this->assertFalse(RoomRicMapper::isValidRoom('one')); // case-sensitive
        $this->assertFalse(RoomRicMapper::isValidRoom('ZERO')); // case-sensitive
    }

    /**
     * Test configuration from .env file
     */
    public function testConfigurationFromEnv(): void
    {
        // Reset config to allow reloading
        Config::reset();
        
        // Create temporary .env file with custom RIC values
        $testEnvFile = sys_get_temp_dir() . '/test-env-' . uniqid() . '.env';
        file_put_contents($testEnvFile, "ROOM_RIC_ZERO=2000\nROOM_RIC_ONE=2001\nROOM_RIC_GROUND=2002\nROOM_RIC_FUSE=2003\nROOM_RIC_ALL_ROOMS=2004\n");
        
        // Load config
        Config::load($testEnvFile);
        
        // Test that configured values are used
        $this->assertEquals(2000, RoomRicMapper::getRicForRoom('Zero'));
        $this->assertEquals(2001, RoomRicMapper::getRicForRoom('One'));
        $this->assertEquals(2002, RoomRicMapper::getRicForRoom('Ground'));
        $this->assertEquals(2003, RoomRicMapper::getRicForRoom('Fuse'));
        $this->assertEquals(2004, RoomRicMapper::getAllRoomsRic());
        
        // Cleanup
        @unlink($testEnvFile);
    }

    /**
     * Test default values when configuration is missing
     */
    public function testDefaultValuesWhenConfigurationMissing(): void
    {
        // Reset config to allow reloading
        Config::reset();
        
        // Create temporary .env file without room RIC configuration
        $testEnvFile = sys_get_temp_dir() . '/test-env-' . uniqid() . '.env';
        file_put_contents($testEnvFile, "LOG_FILE=/tmp/test.log\n");
        
        // Load config
        Config::load($testEnvFile);
        
        // Test that default values are used
        $this->assertEquals(1140, RoomRicMapper::getRicForRoom('Zero'));
        $this->assertEquals(1141, RoomRicMapper::getRicForRoom('One'));
        $this->assertEquals(1142, RoomRicMapper::getRicForRoom('Ground'));
        $this->assertEquals(1143, RoomRicMapper::getRicForRoom('Fuse'));
        $this->assertEquals(1150, RoomRicMapper::getAllRoomsRic());
        
        // Cleanup
        @unlink($testEnvFile);
    }
}
