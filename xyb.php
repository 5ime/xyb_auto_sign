<?php
/**
 * 
 * title: 校友邦自动化脚本
 * description: 校友邦自动签到签退健康上报
 * author: iami233
 * version: 1.0.1
 * update: 2023-3-22
 * github: https://github.com/5ime/xyb_auto_sign
 * 
 */

date_default_timezone_set('PRC');

$config = array(
    'pushToken' => '',
    'userList' => array(
        array(
            'username' => '',
            'password' => '',
            'location' => array(
                'country' => '',
                'province' => '',
                'city' => '',
                'adcode' => '',
                'address' => '',
            ),
            'ip' => '', 
            'imgurl' => '',
            'pushToken' => '',
        )
    )
);

foreach ($config['userList'] as $k => $v) {
    $pushToken = $v['pushToken'] ? $v['pushToken'] : $config['pushToken'];
    $location = array('country' => $v['location']['country'], 'province' => $v['location']['province'], 'city' => $v['location']['city']);
    $result = userLogin($v['username'], $v['password']);

    if (empty($result['sessionId'])) {
        pushWechat($pushToken, '登录失败', $result['msg']);
        return returnJsonData(201, $result['msg']);
    }
    $userInfo = getUserInfo($result['sessionId']);
    if (!$userInfo) {
        pushWechat($pushToken, '执行出错', '获取用户信息失败');
        return returnJsonData(201, '获取用户信息失败');
    }
    $v['username'] = $userInfo['loginer'];
    $v['loginerId'] = $result['loginerId'];
    $v['sessionId'] = $result['sessionId'];
    $v['schoolId'] = $userInfo['schoolId'];
    $traineeId = getTraineeId($v['sessionId']);
    if (!$traineeId) {
        return returnJsonData(201, '获取实训人ID失败');
    }
    $signInfo = getSignState($v['sessionId'], $traineeId);
    if (!$signInfo) {
        pushWechat($pushToken, '执行出错', '获取经纬度失败');
        return returnJsonData(201, '获取经纬度失败');
    }
    // if (!empty($signInfo['clockInfo']['inTime'])) {
    //     return returnJsonData(201, '已经签到过了');
    // }
    // $v['location']['address'] = $signInfo['postInfo']['address'];
    $v['location']['lng'] = $signInfo['postInfo']['lng'];
    $v['location']['lat'] = $signInfo['postInfo']['lat'];
    postHealthData($v['sessionId'], $v['imgurl']);
    $signData = postSignData($v);
    if ($signData['code'] != 200) {
        pushWechat($pushToken, '签到失败', $signData['msg']);
        return returnJsonData(201, '提交签到信息失败');
    }

    // 如果距离签到时间不足9小时，则不签退
    if (time() - strtotime($signInfo['clockInfo']['inTime']) > 32400) {
        $sign = sign($v['sessionId'], $traineeId, $v,1);
        if ($sign['code'] == 200) {
            pushWechat($pushToken, '签到成功', '签到成功');
            return returnJsonData(200, $sign['msg']);
        }
    }

    $sign = sign($v['sessionId'], $traineeId, $v);
    if ($sign['code'] == 200) {
        pushWechat($pushToken, '签到成功', '签到成功');
        return returnJsonData(200, $sign['msg']);
    }
}

function pushWechat($token, $title, $content){
    $url = 'http://www.pushplus.plus/send';
    $data = array('token' => $token, 'title' => $title, 'content' => $content);
    $result = json_decode(curl($url, $data), true);
    if ($result['code'] == 200) {
        return true;
    } else {
        return false;
    }
}

function userLogin($u, $p){
    $url = 'https://xcx.xybsyw.com/login/login.action';
    $data = array('username' => $u, 'password' => md5($p));
    $result = json_decode(curl($url, $data), true);
    if ($result['code'] == 200) {
        $sessionId = $result['data']['sessionId'];
        $loginerId = $result['data']['loginerId'];
        return array('sessionId' => $sessionId, 'loginerId' => $loginerId);
    } else {
        return array('msg' => $result['msg']);
    }
}

function getUserInfo($c){
    $url = 'https://xcx.xybsyw.com/account/LoadAccountInfo.action';
    $headers = array(
        'Content-Type: application/x-www-form-urlencoded',
        'Cookie: JSESSIONID=' . $c,
    );
    $result = json_decode(curl($url, array(), $headers), true);
    if ($result['code'] == 200) {
        return $result['data'];
    } else {
        return false;
    }
}

function getTraineeId($c){
    $url = 'https://xcx.xybsyw.com/student/clock/GetPlan!getDefault.action';
    $headers = array(
        'Content-Type: application/x-www-form-urlencoded',
        'Cookie: JSESSIONID=' . $c,
    );
    $result = json_decode(curl($url, array(), $headers), true);
    if ($result['code'] == 200) {
        return $result['data']['clockVo']['traineeId'];
    } else {
        return false;
    }
}

function getSignState($c, $traineeId){
    $url = 'https://xcx.xybsyw.com/student/clock/GetPlan!detail.action';
    $headers = array(
        'Content-Type: application/x-www-form-urlencoded',
        'Cookie: JSESSIONID=' . $c,
    );
    $data = array('traineeId' => $traineeId);
    $result = json_decode(curl($url, $data, $headers), true);
    if ($result['code'] == 200) {
        return $result['data'];
    } else {
        return false;
    }
}

function postSignData($d = array()){
    $url = 'https://app.xybsyw.com/behavior/Duration.action';
    $data = array(
        'app' => 'wx_student',
        'appVersion' => '1.6.36',
        'userId' => $d['loginerId'],
        'deviceToken' => '',
        'userName' => $d['username'],
        'country' => $d['location']['country'],
        'province' => $d['location']['province'],
        'city' => $d['location']['city'],
        'deviceModel' => 'microsoft',
        'operatingSystem' => 'android',
        'operatingSystemVersion' => '11',
        'screenHeight' => '800',
        'screenWidth' => '450',
        'eventTime' => time(),
        'pageId' => '2',
        'pageName' => '成长',
        'pageUrl' => 'pages/growup/growup',
        'eventType' => 'click',
        'eventName' => 'clickSignEvent',
        'clientIP'=> getUserIP(),
        'reportSrc' => '2',
        'login' => '1',
        'netType' => 'WIFI',
        'itemID' => 'none',
        'itemType' => '其他'
    );

    $headers = array(
        'Content-Type: application/x-www-form-urlencoded',
        'Cookie: JSESSIONID=' . $d['sessionId'],
    );

    $result = json_decode(curl($url, $data, $headers), true);
    if ($result['code'] == 200) {
        return $result;
    } else {
        return false;
    }
}

function getHeaderToken($d){
    $characters = ["5", "b", "f", "A", "J", "Q", "g", "a", "l", "p", "s", "q", "H", "4", "L", "Q", "g", "1", "6", "Q", "Z", "v", "w", "b", "c", "e", "2", "2", "m", "l", "E", "g", "G", "H", "I", "r", "o", "s", "d", "5", "7", "x", "t", "J", "S", "T", "F", "v", "w", "4", "8", "9", "0", "K", "E", "3", "4", "0", "m", "r", "i", "n"];
    $indexes = range(0, count($characters) - 1);
    shuffle($indexes);
    $r = array_slice($indexes, -20);
    $s = "";
    $x = '';
    foreach ($r as $key => $index) {
        $s .= $characters[$index];
        $x .= $index;
        if ($key < count($r) - 1) {
            $x .= '_';
        }
    }
    $time = time();
    $data = array(
        'm' => md5(urlencode($d['adcode'].$d['address'].$d['clockStatus'].$d['punchInStatus'].$d['traineeId'].$time.$s)),
        't' => $time,
        's' => $x,
    );
    return $data;
}

function sign($c, $t, $d = array(), $s=2){
    $url = 'https://xcx.xybsyw.com/student/clock/Post.action';

    $data = array(
        'traineeId' => $t,
        'adcode' => $d['location']['adcode'],
        'lat' => $d['location']['lat'],
        'lng' => $d['location']['lng'],
        'address' => $d['location']['address'],
        'deviceName' => 'microsoft',
        'punchInStatus' => 1,
        'clockStatus' => $s,
    );
    $token = getHeaderToken($data);
    $headers = array(
        'Content-Type: application/x-www-form-urlencoded',
        'Cookie: JSESSIONID=' . $c,
        'v: 1.6.36',
        't: ' .$token['t'],
        's: ' .$token['s'],
        'm: ' .$token['m'],
        'n: content,deviceName,keyWord,blogBody,blogTitle,getType,responsibilities,street,text,reason,searchvalue,key,answers,leaveReason,personRemark,selfAppraisal,imgUrl,wxname,deviceId,avatarTempPath,file,file,model,brand,system,deviceId,platform,code,openId,unionid'
    );

    $result = json_decode(curl($url, $data, $headers), true);
    if ($result['code'] == 200) {
        return $result;
    } else {
        return $result['msg'];
    }
}

function getUserIP(){
    $url = 'https://xcx.xybsyw.com/behavior/Duration!getIp.action';
    $result = json_decode(curl($url), true);
    if ($result['code'] == 200) {
        return $result['data']['ip'];
    } else {
        return false;
    }
}

function postHealthData($d, $img){
    $url = 'https://xcx.xybsyw.com/student/clock/saveEpidemicSituation.action';
    $data = array('healthCodeStatus' => 0, 'locationRiskLevel' => 0, 'healthCodeImg' => $img);

    $headers = array(
        'Content-Type: application/x-www-form-urlencoded',
        'Cookie: JSESSIONID=' . $d,
    );

    $result = json_decode(curl($url, $data, $headers), true);
    if ($result['code'] == 200) {
        return $result;
    } else {
        return false;
    }
}

function returnJsonData($code,$msg)
{
    $arr['code'] = $code;
    $arr['msg'] = $msg;
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
}

function curl($url, $data = array(), $headers = array()) {
    $con = curl_init((string)$url);
    curl_setopt($con, CURLOPT_HEADER, false);
    curl_setopt($con, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
    if (isset($headers)) {
        curl_setopt($con, CURLOPT_HTTPHEADER, $headers);
    }else{
        curl_setopt($con, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: Mozilla/5.0 (Linux; Android 5.1.1; SM-N950N Build/NMF26X; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/74.0.3729.136 Mobile Safari/537.36 MMWEBID/4493 MicroMessenger/8.0.1840(0x28000036) Process/appbrand0 WeChat/arm32 Weixin NetType/WIFI Language/zh_CN ABI/arm32 MiniProgramEnv/android',
            'Referer: https://servicewechat.com/wx9f1c2e0bbc10673c/274/page-frame.html'
        ));
    }
    if (isset($data)) {
        curl_setopt($con, CURLOPT_POST, true);
        curl_setopt($con, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    curl_setopt($con, CURLOPT_TIMEOUT, 5000);
    $result = curl_exec($con);
    return $result;
}
