<?php
require ('../vendor/autoload.php');
use philwc\JsonDB;
use philwc\JsonTable;

//header('Content-Type:application/json; charset=utf-8');

/* Configs */
$config = json_decode(file_get_contents('../config/config.json'), true);

/* Password */
if (!isset($_GET['passwd']) || $_GET['passwd'] !== config('passwd')) {
    error('禁止访问');
}

/* Data */
$members = json_decode(file_get_contents('../config/members.json'), true);
$pdo = new PDO("mysql:dbname=class-duty-dispatcher", "root", "root");
$fpdo = new FluentPDO($pdo);
$recordsForm = $fpdo->from('records');
$records = $recordsForm->fetchAll();

/* Actions */
$acts = [
    'RandTable' => function () {
        $date = $_GET['date'] ?? null;
        $doNum = intval($_GET['doNum'] ?? 1);
        
        if (!is_numeric($date) || !is_numeric($doNum) || $doNum <= 0) {
            error('参数不正确');
        }
    
        // 初始化 Day
        $day = [];
        foreach (config('areas') as $areaName => $area) {
            $day[$areaName] = [];
        }
    
        $hadUse = [];
        $whileTime = 0; // while 次数
        while (true) {
            $canUseMembers = getAllMemberByRecordCount($whileTime); // 可用成员
            if (empty($canUseMembers)) break; // 没人可选了，跳出循环
    
            shuffle($canUseMembers); // 打乱可用成员顺序
            
            foreach ($canUseMembers as $name) {
                if (isset($hadUse[$name]))
                    continue;
                
                $nextArea = getMemberNextArea($name);
                if (!is_null($nextArea)) {
                    if (count($day[$nextArea]) >= config('areas')[$nextArea]['memberNum'])
                        continue;
    
                    $day[$nextArea][] = $name;
                    $hadUse[$name] = true;
                } else {
                    foreach (config('areas') as $areaName => $area) {
                        if (count($day[$areaName]) >= $area['memberNum'])
                            continue;
    
                        $day[$areaName][] = $name;
                        $hadUse[$name] = true;
                        break;
                    }
                }
            }
        
            // 是否还有 null 的
            break;
            $hasNull = false;
            foreach ($day as $areaName => $members) {
                if (empty($members) || count($members) < config('areas')[$areaName]['memberNum']) {
                    $hasNull = true;
                    break;
                }
            }
            if (!$hasNull) break; // 若没有 null 的了，跳出循环
            
            $whileTime++;
        }
        
        // 记录
        echo '<table border="1">';
        foreach ($day as $areaName => $members) {
            foreach ($members as $name) {
                echo '<tr><td>'.$name.'</td><td>'.config('areas')[$areaName]['label'].'</td></tr>';
                for ($i = 0; $i < $doNum; $i++) {
                    addRecord($name, $date, $areaName);
                }
            }
        }
        echo '</table>';
        
        var_dump($day);
    },
    
    'GetMemberRecords' => function () {
        $memberRecords = [];
        foreach (getMembers() as $name) {
            $curtMemberRecords =  getAllRecordsByName($name);
            $memberRecords[$name] = $curtMemberRecords;
        }
        success([
            'records' => $memberRecords,
            'recordCounts' => getRecordCounts()
        ]);
    },
];

$actName = $_GET['act'];
isset($acts[$actName])
    ? $acts[$actName]()
    : error('我是谁？我要干什么？现在几点？我在哪里？');

/* Common Functions */
function success($data) {
    echo json_encode(['data' => $data], JSON_UNESCAPED_UNICODE);
    die();
}

function error($msg) {
    echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
    die();
}

function config($key = null) {
    global $config;
    if (!is_null($key))
        return $config[$key];
    else
        return $config;
}

function getFpdo() {
    global $fpdo;
    return $fpdo;
}

function getMembers() {
    global $members;
    return $members;
}

function getRecords() {
    global $records;
    return $records;
}

function getRecordsForm() {
    global $recordsForm;
    return $recordsForm;
}

// 新增一条记录
function addRecord($name, $date, $area) {
    return getFpdo()->insertInto('records', [
        'name' => $name,
        'date' => $date,
        'area' => $area
    ])->execute();
}

// 获取全部扫地记录 By name
function getAllRecordsByName($name) {
    $records = [];
    
    foreach (getRecords() as $item) {
        if ($item['name'] == $name) {
            $records[] = $item;
        }
    }
    return $records;
}

// 获取最新的一个扫地记录 By name
function getLatestOneRecordByName($name) {
    $records = getAllRecordsByName($name);
    if (empty($records)) {
        return null;
    }
    
    $according = [];
    foreach($records as $key => $item){
        $according[] = $item['date'];
    }
    array_multisort($according, SORT_DESC, $records);
    
    return $records[0];
}

// 获取所有成员的扫地次数
function getRecordCounts() {
    $recordCounts = [];
    foreach (getMembers() as $name) {
        $curtMemberRecords =  getAllRecordsByName($name);
        $recordCounts[$name] = count($curtMemberRecords);
    }
    
    return $recordCounts;
}

// 获取扫地次数最多的成员，$x 从 0 到 +∞（从 扫地次数最"少"的人 到 扫地次数最"多"的人）
function getAllMemberByRecordCount($x = 0) {
    $stageList = [];
    
    foreach (getRecordCounts() as $name => $num) {
        if (!in_array($num, $stageList))
            $stageList[] = $num;
    }
    
    sort($stageList);
    
    if (!isset($stageList[$x]))
        return [];
    
    $need = $stageList[$x];
    
    $members = [];
    foreach (getRecordCounts() as $name => $num) {
        if ($num == $need)
            $members[] = $name;
    }
    
    return $members;
}

// 获取成员的下一个扫地区域
function getMemberNextArea($name) {
    $areas = config('areas');
    $areaNames = array_keys($areas);
    $record =  getLatestOneRecordByName($name);
    if (empty($record)) {
        return null;
    }
    $curt = array_search($record['area'], $areaNames);
    $next = ($curt+1 >= count($areaNames)) ? 0 : $curt+1; // 0==从头开始
    
    return $areaNames[$next];
}

// 获取所有下次要扫某个区域的成员
function getAllMemberNextArea($areaName, $members) {
    $arr = [];
    foreach ($members as $name) {
        $can = getMemberNextArea($name);
        if (is_null($can) || $can == $areaName) {
            $arr[] = $name;
        }
    }
    
    return $arr;
}
