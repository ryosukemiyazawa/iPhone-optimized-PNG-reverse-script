<?php

include_once 'ZlibDecompress/ZlibDecompress.php';

class Peperzaken_Ios_DecodeImage {

	private $_imagePath;
	
	public function __construct($path = null)
	{
		if ($path !== null) {
			$this->setSource($path);
		}
	}
	
	public function setSource ($path) 
	{
		$this->_imagePath = realpath($path);
	}
	
	public function decode ($outPath = null)
	{
		// Open the file
		$fh = fopen($this->_imagePath, 'rb');
		
		// Get the header
		$headerData = fread ($fh, 8);

		// Split the header
		$header = unpack ("C1highbit/A3signature/C2lineendings/C1eof/C1eol", $headerData);

		// check if it's a PNG image
		if (! is_array ($header) && ! $header['highbit'] == 0x89 && ! $header['signature'] == "PNG") {
		    return false;
		}

		$chunks = array();
		$isIphoneCompressed = false;

		while (! feof($fh)) {
		    $data = fread ($fh, 8);
		    if (strlen($data) > 0) { // Fix for empty parts        
		        // Unpack the chunk
		        $chunk = unpack ("N1length/A4type", $data); // get the type and length of the chunk
		        $data = @fread ($fh, $chunk['length']); // can be 0...
	       		$dataCrc = fread ($fh, 4); // get the crc
		        $crc = unpack ("N1crc", $dataCrc);
		        $chunk['crc'] = $crc['crc'];
        
		        // This chunk is first when it's a iPhone compressed image
		        if ($chunk['type'] == 'CgBI') {
		            $isIphoneCompressed = true;
        		}
        
		        // Extract the header if needed
        		if($chunk['type'] == 'IHDR' && $isIphoneCompressed) {
		            $width = unpack('N*', substr($data, 0, 4));  
        		    $height = unpack('N*', substr($data, 4, 4));
		            $width = $width[1];
        		    $height = $height[1];
		        }
        		
        		// Extract and mutate the data chunk if needed (can be multiple)
		        if ($chunk['type'] == 'IDAT' && $isIphoneCompressed) {    
    		        $bufSize = $height * $width * 4 + $height; 
            		$orgData = $data;
		            $uncompressed = @gzuncompress($orgData, $bufSize); // Supress the warnign if it can't be extracted
            
        		    // Try extracting via the amazing ZlibDecompress class, because it probably misses the gzip header, footer and crc parts.
		            if ($uncompressed == false) {
                		$zlib = new \ZlibDecompress();
		                $uncompressed = $zlib->inflate($orgData); // we assume it works, this might need some work 
        		    }
		            
		            // Let swap some colors
		            $newData = '';
		            for ($y=0; $y < $height; $y++) {
        		        $i = strlen($newData); // setting the offset

        		        //skip for multi IDAT chunks
        		        if($i >= strlen($uncompressed)){
        		        	break;
        		        }

                		$newData .= $uncompressed[$i]; // inject the first pixel, don't know why...
		                for ($x=0; $x < $width; $x++) {
        		            $i = strlen($newData); // setting the offset
                		    // Now we need to swap the BGRA to RGBA
		                    $newData .= $uncompressed[$i+2]; // Place the Red pixel
        		            $newData .= $uncompressed[$i+1]; // Place the Green pixel
                		    $newData .= $uncompressed[$i+0]; // Place the Blue pixel
		                    $newData .= $uncompressed[$i+3]; // Place the Aplha byte
        		        }
            		}
		            
		            /*
        		    $data = gzcompress($newData, 8);
		            $chunk['length'] = strlen($data);
        		    $chunk['crc'] = crc32($chunk['type'] . $data);
        		    */

        		    //compress data after marge
        		    $data = $newData;
		        }
        		$chunk['data'] = $data;
        
		        // Add the chunk to the chunks array so we can rebuild the thing
        		$chunks[] = $chunk;
		    }
		}

		//merge IDAT chunk
		$idat_chunks = [];
		foreach($chunks as $chunk){
			if($chunk["type"] == "IDAT"){
				$idat_chunks[] = $chunk;
			}
		}
		$merged_idat_chunk = [
			"type" => "IDAT",
			"data" => ""
		];
		foreach($idat_chunks as $idat){
			$merged_idat_chunk["data"] .= $idat["data"];
		}
		$merged_idat_chunk["data"] = gzcompress($merged_idat_chunk["data"], 8);
		$merged_idat_chunk["length"] = strlen($merged_idat_chunk["data"]);
		$merged_idat_chunk["crc"] = crc32($merged_idat_chunk['type'] . $merged_idat_chunk["data"]);

		//replace IDAT chunks to merged chunk
		$tmp = [];
		foreach($chunks as $chunk){
			if($chunk["type"] == "IDAT"){
				if($merged_idat_chunk){
					$tmp[] = $merged_idat_chunk;
				}
				continue;
			}

			$tmp[] = $chunk;
		}
		$chunks = $tmp;


		$out = $headerData;
		foreach ($chunks as $chunk) {
		    // rebuild the PNG image without the CgBI chunk
		    if ($chunk['type'] !== 'CgBI') {
		        $out .= pack('N', $chunk['length']);
        		$out .= $chunk['type'];
		        $out .= $chunk['data'];
        		$out .= pack('N', $chunk['crc']);
		    }
		}
		if ($outPath !== null) {
			return file_put_contents($outPath, $out);
		}
		return $out;
	}

}