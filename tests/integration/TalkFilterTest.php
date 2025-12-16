<?php

namespace ChaosPagerEventInfos\Tests\Integration;

use ChaosPagerEventInfos\TalkFilter;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for TalkFilter
 *
 * Tests filtering by large rooms.
 */
class TalkFilterTest extends TestCase
{
    /**
     * Test filtering large rooms
     */
    public function testFilterLargeRooms(): void
    {
        $talks = [
            ['id' => '1', 'title' => 'Talk 1', 'room' => 'One'],
            ['id' => '2', 'title' => 'Talk 2', 'room' => 'Ground'],
            ['id' => '3', 'title' => 'Talk 3', 'room' => 'Zero'],
            ['id' => '4', 'title' => 'Talk 4', 'room' => 'Fuse'],
            ['id' => '5', 'title' => 'Talk 5', 'room' => 'Small Room'],
            ['id' => '6', 'title' => 'Talk 6', 'room' => 'Another Room'],
        ];

        $filtered = TalkFilter::filterLargeRooms($talks);

        $this->assertCount(4, $filtered);
        $this->assertEquals('One', $filtered[0]['room']);
        $this->assertEquals('Ground', $filtered[1]['room']);
        $this->assertEquals('Zero', $filtered[2]['room']);
        $this->assertEquals('Fuse', $filtered[3]['room']);
    }

    /**
     * Test isLargeRoom with valid rooms
     */
    public function testIsLargeRoomValid(): void
    {
        $this->assertTrue(TalkFilter::isLargeRoom(['room' => 'One']));
        $this->assertTrue(TalkFilter::isLargeRoom(['room' => 'Ground']));
        $this->assertTrue(TalkFilter::isLargeRoom(['room' => 'Zero']));
        $this->assertTrue(TalkFilter::isLargeRoom(['room' => 'Fuse']));
    }

    /**
     * Test isLargeRoom with invalid rooms
     */
    public function testIsLargeRoomInvalid(): void
    {
        $this->assertFalse(TalkFilter::isLargeRoom(['room' => 'Small Room']));
        $this->assertFalse(TalkFilter::isLargeRoom(['room' => 'Another Room']));
        $this->assertFalse(TalkFilter::isLargeRoom(['room' => '']));
        $this->assertFalse(TalkFilter::isLargeRoom([]));
    }

    /**
     * Test isLargeRoom with missing room field
     */
    public function testIsLargeRoomMissingRoom(): void
    {
        $this->assertFalse(TalkFilter::isLargeRoom(['id' => '1', 'title' => 'Talk']));
    }

    /**
     * Test isLargeRoom with empty room
     */
    public function testIsLargeRoomEmptyRoom(): void
    {
        $this->assertFalse(TalkFilter::isLargeRoom(['room' => '']));
        $this->assertFalse(TalkFilter::isLargeRoom(['room' => null]));
    }

    /**
     * Test getLargeRooms returns correct list
     */
    public function testGetLargeRooms(): void
    {
        $largeRooms = TalkFilter::getLargeRooms();

        $this->assertIsArray($largeRooms);
        $this->assertCount(4, $largeRooms);
        $this->assertContains('One', $largeRooms);
        $this->assertContains('Ground', $largeRooms);
        $this->assertContains('Zero', $largeRooms);
        $this->assertContains('Fuse', $largeRooms);
    }

    /**
     * Test filtering with empty array
     */
    public function testFilterLargeRoomsEmpty(): void
    {
        $filtered = TalkFilter::filterLargeRooms([]);
        $this->assertIsArray($filtered);
        $this->assertCount(0, $filtered);
    }

    /**
     * Test filtering with no large rooms
     */
    public function testFilterLargeRoomsNoLargeRooms(): void
    {
        $talks = [
            ['id' => '1', 'title' => 'Talk 1', 'room' => 'Small Room'],
            ['id' => '2', 'title' => 'Talk 2', 'room' => 'Another Room'],
        ];

        $filtered = TalkFilter::filterLargeRooms($talks);
        $this->assertCount(0, $filtered);
    }
}
