<?php

$config = require __DIR__ . '/config.php';

$tgosPath = dirname(__DIR__) . '/raw/tgos';
if (!file_exists($tgosPath)) {
    mkdir($tgosPath, 0777, true);
}

$dataPath = dirname(__DIR__) . '/docs/data';
if (!file_exists($dataPath)) {
    mkdir($dataPath, 0777, true);
}

$fh = fopen(dirname(__DIR__) . '/raw/points.csv', 'r');
$head = false;
$keys = ['X', 'Y', 'FULL_ADDR', 'CODEBASE', 'CODE1', 'CODE2'];
$oFh = false;
while ($line = fgetcsv($fh, 2048)) {
    foreach ($line as $k => $v) {
        $line[$k] = mb_convert_encoding($v, 'utf-8', 'big5');
    }
    if (false !== $head) {
        $data = array_combine($head, $line);

        $address = "{$data['所屬縣市']}{$data['鄉鎮縣市別']}{$data['地址']}";
        $pos = strpos($address, '號');
        $address = substr($address, 0, $pos);
        if (empty($address)) {
            continue;
        }
        $address .= '號';
        $tgosFile = $tgosPath . '/' . $address . '.json';
        if (!file_exists($tgosFile)) {
            $apiUrl = $config['tgos']['url'] . '?' . http_build_query(array(
                'oAPPId' => $config['tgos']['APPID'], //應用程式識別碼(APPId)
                'oAPIKey' => $config['tgos']['APIKey'], // 應用程式介接驗證碼(APIKey)
                'oAddress' => $address, //所要查詢的門牌位置
                'oSRS' => 'EPSG:4326', //坐標系統(SRS)EPSG:4326(WGS84)國際通用, EPSG:3825 (TWD97TM119) 澎湖及金馬適用,EPSG:3826 (TWD97TM121) 台灣地區適用,EPSG:3827 (TWD67TM119) 澎湖及金馬適用,EPSG:3828 (TWD67TM121) 台灣地區適用
                'oFuzzyType' => '2', //0:最近門牌號機制,1:單雙號機制,2:[最近門牌號機制]+[單雙號機制]
                'oResultDataType' => 'JSON', //回傳的資料格式，允許傳入的代碼為：JSON、XML
                'oFuzzyBuffer' => '0', //模糊比對回傳門牌號的許可誤差範圍，輸入格式為正整數，如輸入 0 則代表不限制誤差範圍
                'oIsOnlyFullMatch' => 'false', //是否只進行完全比對，允許傳入的值為：true、false，如輸入 true ，模糊比對機制將不被使用
                'oIsSupportPast' => 'true', //是否支援舊門牌的查詢，允許傳入的值為：true、false，如輸入 true ，查詢時範圍包含舊門牌
                'oIsShowCodeBase' => 'true', //是否顯示地址的統計區相關資訊，允許傳入的值為：true、false
                'oIsLockCounty' => 'true', //是否鎖定縣市，允許傳入的值為：true、false，如輸入 true ，則代表查詢結果中的 [縣市] 要與所輸入的門牌地址中的 [縣市] 完全相同
                'oIsLockTown' => 'false', //是否鎖定鄉鎮市區，允許傳入的值為：true、false，如輸入 true ，則代表查詢結果中的 [鄉鎮市區] 要與所輸入的門牌地址中的 [鄉鎮市區] 完全相同
                'oIsLockVillage' => 'false', //是否鎖定村里，允許傳入的值為：true、false，如輸入 true ，則代表查詢結果中的 [村里] 要與所輸入的門牌地址中的 [村里] 完全相同
                'oIsLockRoadSection' => 'false', //是否鎖定路段，允許傳入的值為：true、false，如輸入 true ，則代表查詢結果中的 [路段] 要與所輸入的門牌地址中的 [路段] 完全相同
                'oIsLockLane' => 'false', //是否鎖定巷，允許傳入的值為：true、false，如輸入 true ，則代表查詢結果中的 [巷] 要與所輸入的門牌地址中的 [巷] 完全相同
                'oIsLockAlley' => 'false', //是否鎖定弄，允許傳入的值為：true、false，如輸入 true ，則代表查詢結果中的 [弄] 要與所輸入的門牌地址中的 [弄] 完全相同
                'oIsLockArea' => 'false', //是否鎖定地區，允許傳入的值為：true、fals，如輸入 true ，則代表查詢結果中的 [地區] 要與所輸入的門牌地址中的 [地區] 完全相同
                'oIsSameNumber_SubNumber' => 'true', //號之、之號是否視為相同，允許傳入的值為：true、false
                'oCanIgnoreVillage' => 'true', //找不時是否可忽略村里，允許傳入的值為：true、false
                'oCanIgnoreNeighborhood' => 'true', //找不時是否可忽略鄰，允許傳入的值為：true、false
                'oReturnMaxCount' => '0', //如為多筆時，限制回傳最大筆數，輸入格式為正整數，如輸入 0 則代表不限制回傳筆數
            ));
            $content = file_get_contents($apiUrl);
            if (!empty($content)) {
                file_put_contents($tgosFile, $content);
            }
        }
        if (file_exists($tgosFile)) {
            $content = file_get_contents($tgosFile);
            $pos = strpos($content, '{');
            $posEnd = strrpos($content, '}');
            $json = json_decode(substr($content, $pos, $posEnd - $pos + 1), true);
            if (!empty($json['AddressList'][0])) {
                foreach ($keys as $key) {
                    $data[$key] = $json['AddressList'][0][$key];
                }
                if (false !== $oFh) {
                    fputcsv($oFh, $data);
                } else {
                    $oFh = fopen($dataPath . '/points.csv', 'w');
                    fputcsv($oFh, array_keys($data));
                }
            }
        }
    } else {
        $head = $line;
    }
}
