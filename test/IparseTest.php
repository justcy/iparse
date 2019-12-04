<?php
use PHPUnit\Framework\TestCase;

class IparseTest extends TestCase
{
	public function testIp2Region(){
		$ipParse = new iparse\Ip2Region();
		$result = $ipParse->btreeSearch('101.105.35.57');
		echo json_encode($result);
//		$this->assertEquals(true, is_array($result));
//		$this->assertEquals('成都市', $result['area']);
//		$this->assertEquals('中国', $result['country']);
//		$this->assertEquals('四川省', $result['province']);
//		$this->assertEquals('电信', $result['isp']);
	}
}
