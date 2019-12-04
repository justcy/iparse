<?php

use PHPUnit\Framework\TestCase;

class IpCheckTest extends TestCase
{
    public function testIpCheckSingle()
    {
        $ipCheck = new iparse\ipCheck();
        list($result, $ipItem) = $ipCheck->check('118.122.92.31', ['118.122.92.31', '118.122.93.12']);
        $this->assertEquals(true, $result);
        $this->assertEquals('118.122.92.31', $ipItem);
    }
    
    public function testIpCheckWildcard()
    {
        $ipCheck = new iparse\ipCheck();
        list($result, $ipItem) = $ipCheck->check('118.122.92.31', ['118.122.92.*', '118.122.93.*']);
        $this->assertEquals(true, $result);
        $this->assertNotEquals('118.122.93.*', $ipItem);
        $this->assertEquals('118.122.92.*', $ipItem);
    }
    
    public function testIpCheckMask()
    {
        $ipCheck = new iparse\ipCheck();
        list($result, $ipItem) = $ipCheck->check('118.122.92.31', ['118.122.92.0/24']);
        $this->assertEquals(true, $result);
        $this->assertEquals('118.122.92.0/24', $ipItem);
    }
    
    public function testIpCheckCIDR()
    {
        $ipCheck = new iparse\ipCheck();
        list($result, $ipItem) = $ipCheck->check('118.122.92.31', ['118.122.92.0/24']);
        $this->assertEquals(true, $result);
        $this->assertEquals('118.122.92.0/24', $ipItem);
    }
    
    public function testIpCheckSection()
    {
        $ipCheck = new iparse\ipCheck();
        list($result, $ipItem) = $ipCheck->check('118.122.92.31', ['118.122.92.12-118.122.92.33', '118.122.92.23-118.122.92.344']);
        $this->assertEquals(true, $result);
        $this->assertEquals('118.122.92.12-118.122.92.33', $ipItem);
    }
}
