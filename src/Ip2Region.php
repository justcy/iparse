<?php
/**
 * ip2region php seacher client class
 *
 * @author  justcy<justxcy@gmail.com>
 * @date    2015-10-29
*/

namespace iparse;

defined('INDEX_BLOCK_LENGTH')   or define('INDEX_BLOCK_LENGTH',  12);
defined('TOTAL_HEADER_LENGTH')  or define('TOTAL_HEADER_LENGTH', 8192);

class Ip2Region
{
    /**
     * db file handler
    */
    private $dbFileHandler = NULL;

    /**
     * header block info
    */
    private $HeaderSip    = NULL;
    private $HeaderPtr    = NULL;
    private $headerLen  = 0;

    /**
     * super block index info
    */
    private $firstIndexPtr = 0;
    private $lastIndexPtr  = 0;
    private $totalBlocks   = 0;

    /**
     * for memory mode only
     *  the original db binary string
    */
    private $dbBinStr = NULL;
    private $dbFile = NULL;
    
    /**
     * construct method
     *
     * @param   ip2regionFile
    */
    public function __construct( $ip2regionFile = null)
    {
        $this->dbFile = $this->dbFile = is_null($ip2regionFile) ? __DIR__ . '/../data/ip2region.db' : $ip2regionFile;
    }

    /**
     * all the db binary string will be loaded into memory
     * then search the memory only and this will a lot faster than disk base search
     * @Note:
     * invoke it once before put it to public invoke could make it thread safe
     *
     * @param   $ip
    */
    public function memorySearch($ip)
    {
        //check and load the binary string for the first time
        if ( $this->dbBinStr == NULL ) {
            $this->dbBinStr = file_get_contents($this->dbFile);
            if ( $this->dbBinStr == false ) {
                throw new Exception("Fail to open the db file {$this->dbFile}");
            }

            $this->firstIndexPtr = Tools::getLong($this->dbBinStr, 0);
            $this->lastIndexPtr  = Tools::getLong($this->dbBinStr, 4);
            $this->totalBlocks   = ($this->lastIndexPtr-$this->firstIndexPtr)/INDEX_BLOCK_LENGTH + 1;
        }

        if ( is_string($ip) ) $ip = Tools::safeIp2long($ip);

        //binary search to define the data
        $l = 0;
        $h = $this->totalBlocks;
        $dataPtr = 0;
        while ( $l <= $h ) {
            $m = (($l + $h) >> 1);
            $p = $this->firstIndexPtr + $m * INDEX_BLOCK_LENGTH;
            $sip = Tools::getLong($this->dbBinStr, $p);
            if ( $ip < $sip ) {
                $h = $m - 1;
            } else {
                $eip = Tools::getLong($this->dbBinStr, $p + 4);
                if ( $ip > $eip ) {
                    $l = $m + 1;
                } else {
                    $dataPtr = Tools::getLong($this->dbBinStr, $p + 8);
                    break;
                }
            }
        }

        //not matched just stop it here
        if ( $dataPtr == 0 ) return NULL;

        //get the data
        $dataLen = (($dataPtr >> 24) & 0xFF);
        $dataPtr = ($dataPtr & 0x00FFFFFF);
    
        $regionStr = explode('|',substr($this->dbBinStr, $dataPtr + 4, $dataLen - 4));
        return [
            'city_id'  => Tools::getLong($this->dbBinStr, $dataPtr),
            'country'  => $regionStr[0],
            'region'   => $regionStr[1],
            'province' => $regionStr[2],
            'area'     => $regionStr[3],
            'isp'      => $regionStr[4],
        ];
    }

    /**
     * get the data block through the specified ip address or long ip numeric with binary search algorithm
     *
     * @param    ip
     * @return    mixed Array or NULL for any error
    */
    public function binarySearch( $ip )
    {
        //check and conver the ip address
        if ( is_string($ip) ) $ip = Tools::safeIp2long($ip);
        if ( $this->totalBlocks == 0 ) {
            //check and open the original db file
            if ( $this->dbFileHandler == NULL ) {
                $this->dbFileHandler = fopen($this->dbFile, 'r');
                if ( $this->dbFileHandler == false ) {
                    throw new Exception("Fail to open the db file {$this->dbFile}");
                }
            }

            fseek($this->dbFileHandler, 0);
            $superBlock = fread($this->dbFileHandler, 8);

            $this->firstIndexPtr = Tools::getLong($superBlock, 0);
            $this->lastIndexPtr  = Tools::getLong($superBlock, 4);
            $this->totalBlocks   = ($this->lastIndexPtr-$this->firstIndexPtr)/INDEX_BLOCK_LENGTH + 1;
        }

        //binary search to define the data
        $l = 0;
        $h = $this->totalBlocks;
        $dataPtr = 0;
        while ( $l <= $h ) {
            $m = (($l + $h) >> 1);
            $p = $m * INDEX_BLOCK_LENGTH;

            fseek($this->dbFileHandler, $this->firstIndexPtr + $p);
            $buffer = fread($this->dbFileHandler, INDEX_BLOCK_LENGTH);
            $sip    = Tools::getLong($buffer, 0);
            if ( $ip < $sip ) {
                $h = $m - 1;
            } else {
                $eip = Tools::getLong($buffer, 4);
                if ( $ip > $eip ) {
                    $l = $m + 1;
                } else {
                    $dataPtr = Tools::getLong($buffer, 8);
                    break;
                }
            }
        }

        //not matched just stop it here
        if ( $dataPtr == 0 ) return NULL;


        //get the data
        $dataLen = (($dataPtr >> 24) & 0xFF);
        $dataPtr = ($dataPtr & 0x00FFFFFF);

        fseek($this->dbFileHandler, $dataPtr);
        $data = fread($this->dbFileHandler, $dataLen);

        return array(
            'city_id' => Tools::getLong($data, 0),
            'region'  => substr($data, 4)
        );
    
        $regionStr = explode('|',substr($data, 4));
        return [
            'city_id'  => Tools::getLong($data, 0),
            'country'  => $regionStr[0],
            'region'   => $regionStr[1],
            'province' => $regionStr[2],
            'area'     => $regionStr[3],
            'isp'      => $regionStr[4],
        ];
    }

    /**
     * get the data block associated with the specified ip with b-tree search algorithm
     * @Note: not thread safe
     *
     * @param   ip
     * @return  Mixed Array for NULL for any error
    */
    public function btreeSearch( $ip )
    {
        if ( is_string($ip) ) $ip = Tools::safeIp2long($ip);

        //check and load the header
        if ( $this->HeaderSip == NULL ) {
            //check and open the original db file
            if ( $this->dbFileHandler == NULL ) {
                $this->dbFileHandler = fopen($this->dbFile, 'r');
                if ( $this->dbFileHandler == false ) {
                    throw new Exception("Fail to open the db file {$this->dbFile}");
                }
            }

            fseek($this->dbFileHandler, 8);
            $buffer = fread($this->dbFileHandler, TOTAL_HEADER_LENGTH);
            
            //fill the header
            $idx = 0;
            $this->HeaderSip = array();
            $this->HeaderPtr = array();
            for ( $i = 0; $i < TOTAL_HEADER_LENGTH; $i += 8 ) {
                $startIp = Tools::getLong($buffer, $i);
                $dataPtr = Tools::getLong($buffer, $i + 4);
                if ( $dataPtr == 0 ) break;

                $this->HeaderSip[] = $startIp;
                $this->HeaderPtr[] = $dataPtr;
                $idx++;
            }

            $this->headerLen = $idx;
        }
        
        //1. define the index block with the binary search
        $l = 0; $h = $this->headerLen; $sptr = 0; $eptr = 0;
        while ( $l <= $h ) {
            $m = (($l + $h) >> 1);
            
            //perfetc matched, just return it
            if ( $ip == $this->HeaderSip[$m] ) {
                if ( $m > 0 ) {
                    $sptr = $this->HeaderPtr[$m-1];
                    $eptr = $this->HeaderPtr[$m  ];
                } else {
                    $sptr = $this->HeaderPtr[$m ];
                    $eptr = $this->HeaderPtr[$m+1];
                }
                
                break;
            }
            
            //less then the middle value
            if ( $ip < $this->HeaderSip[$m] ) {
                if ( $m == 0 ) {
                    $sptr = $this->HeaderPtr[$m  ];
                    $eptr = $this->HeaderPtr[$m+1];
                    break;
                } else if ( $ip > $this->HeaderSip[$m-1] ) {
                    $sptr = $this->HeaderPtr[$m-1];
                    $eptr = $this->HeaderPtr[$m  ];
                    break;
                }
                $h = $m - 1;
            } else {
                if ( $m == $this->headerLen - 1 ) {
                    $sptr = $this->HeaderPtr[$m-1];
                    $eptr = $this->HeaderPtr[$m  ];
                    break;
                } else if ( $ip <= $this->HeaderSip[$m+1] ) {
                    $sptr = $this->HeaderPtr[$m  ];
                    $eptr = $this->HeaderPtr[$m+1];
                    break;
                }
                $l = $m + 1;
            }
        }
        
        //match nothing just stop it
        if ( $sptr == 0 ) return NULL;
        
        //2. search the index blocks to define the data
        $blockLen = $eptr - $sptr;
        fseek($this->dbFileHandler, $sptr);
        $index = fread($this->dbFileHandler, $blockLen + INDEX_BLOCK_LENGTH);
        
        $dataPtr = 0;
        $l = 0; $h = $blockLen / INDEX_BLOCK_LENGTH;
        while ( $l <= $h ) {
            $m = (($l + $h) >> 1);
            $p = (int)($m * INDEX_BLOCK_LENGTH);
            $sip = Tools::getLong($index, $p);
            if ( $ip < $sip ) {
                $h = $m - 1;
            } else {
                $eip = Tools::getLong($index, $p + 4);
                if ( $ip > $eip ) {
                    $l = $m + 1;
                } else {
                    $dataPtr = Tools::getLong($index, $p + 8);
                    break;
                }
            }
        }
        
        //not matched
        if ( $dataPtr == 0 ) return NULL;
        
        //3. get the data
        $dataLen = (($dataPtr >> 24) & 0xFF);
        $dataPtr = ($dataPtr & 0x00FFFFFF);
        
        fseek($this->dbFileHandler, $dataPtr);
        $data = fread($this->dbFileHandler, $dataLen);

        $regionStr = explode('|',substr($data, 4));
        return [
            'city_id'  => Tools::getLong($data, 0),
            'country'  => $regionStr[0],
            'region'   => $regionStr[1],
            'province' => $regionStr[2],
            'area'     => $regionStr[3],
            'isp'      => $regionStr[4],
        ];
    }
    /**
     * destruct method, resource destroy
    */
    public function __destruct()
    {
        if ( $this->dbFileHandler != NULL ) {
            fclose($this->dbFileHandler);
        }

        $this->dbBinStr  = NULL;
        $this->HeaderSip = NULL;
        $this->HeaderPtr = NULL;
    }
}
