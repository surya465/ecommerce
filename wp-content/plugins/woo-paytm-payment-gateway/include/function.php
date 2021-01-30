<?php
function wppg_encrypt($input, $ky){
    $key   = html_entity_decode($ky);
    $iv = "@@@@&&&&####$$$$";
    $data = openssl_encrypt ( $input , "AES-128-CBC" , $key, 0, $iv );
    return $data;
}

function wppg_decrypt($crypt, $ky){
    $key   = html_entity_decode($ky);
    $iv = "@@@@&&&&####$$$$";
    $data = openssl_decrypt ( $crypt , "AES-128-CBC" , $key, 0, $iv );
    return $data;
}

function wppg_generateRandString($length){
    $random = "";
    srand((double) microtime() * 1000000);
    
    $data = "AbcDE123IJKLMN67QRSTUVWXYZ";
    $data .= "aBCdefghijklmn123opq45rs67tuv89wxyz";
    $data .= "0FGH45OP89";
    
    for ($i = 0; $i < $length; $i++) {
        $random .= substr($data, (rand() % (strlen($data))), 1);
    }
    
    return $random;
}

function wppg_checkString($value){
    $myvalue = ltrim($value);
    $myvalue = rtrim($myvalue);
    if ($myvalue == 'null')
        $myvalue = '';
    return $myvalue;
}

function wppg_getChecksumFromArray($arrayList, $key, $sort = 1){
    if($sort != 0){
        ksort($arrayList);
    }
    $str         = wppg_getArraytoStr($arrayList);
    $rand        = wppg_generateRandString(4);
    $finalString = $str . "|" . $rand;
    $hash        = hash("sha256", $finalString);
    $hashString  = $hash . $rand;
    $checksum    = wppg_encrypt($hashString, $key);
    return $checksum;
}

function wppg_verifychecksum($arrayList, $key, $checksumvalue){
    $arrayList = wppg_removeCheckSumParam($arrayList);
    ksort($arrayList);
    $str        = wppg_getArraytoStrForVerify($arrayList);
    $paytm_hash = wppg_decrypt($checksumvalue, $key);
    $salt       = substr($paytm_hash, -4);
    
    $finalString = $str . "|" . $salt;
    
    $website_hash = hash("sha256", $finalString);
    $website_hash .= $salt;
    
    $validFlag = "FALSE";
    if($website_hash == $paytm_hash){
        $validFlag = "TRUE";
    } else {
        $validFlag = "FALSE";
    }
    return $validFlag;
}

function wppg_getArraytoStr($arrayList){
	$findme   = 'REFUND';
	$findmepipe = '|';
	$paramStr = "";
	$flag = 1;	
	foreach($arrayList as $key => $value){
		$pos = strpos($value, $findme);
		$pospipe = strpos($value, $findmepipe);
		if($pos !== false || $pospipe !== false){
			continue;
		}
		
		if($flag){
			$paramStr .= wppg_checkString($value);
			$flag = 0;
		} else {
			$paramStr .= "|" . wppg_checkString($value);
		}
	}
	return $paramStr;
}

function wppg_getArraytoStrForVerify($arrayList){
	$paramStr = "";
	$flag = 1;
	foreach($arrayList as $key => $value){
		if($flag){
			$paramStr .= wppg_checkString($value);
			$flag = 0;
		} else {
			$paramStr .= "|" . wppg_checkString($value);
		}
	}
	return $paramStr;
}

function wppg_removeCheckSumParam($arrayList){
    if(isset($arrayList["CHECKSUMHASH"])){
        unset($arrayList["CHECKSUMHASH"]);
    }
    return $arrayList;
}

function wppg_get_paytm_response_url($apiURL, $requestParamList){
    $jsonResponse      = "";
    $responseParamList = array();
    $JsonData          = json_encode($requestParamList);
    $apiURL = $apiURL.'?JsonData='.urlencode($JsonData);
    $response = wp_remote_get( $apiURL);
    $responseParamList = json_decode($response['body'], true);
    return $responseParamList;
}