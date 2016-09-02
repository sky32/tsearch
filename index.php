<?php
/**
 * Created by PhpStorm.
 * User: Sky
 * Date: 2016/9/1
 * Time: 下午6:24
 */
error_reporting(E_ERROR);
ini_set('display_errors', 0);
require_once 'FileSystemCache.php';

// referer校验
$allowHost   = [
    'ttttbuy.com',
    'taosearch.cn',
];
$refererInfo = parse_url($_SERVER['HTTP_REFERER']);
if (!in_array($refererInfo['host'], $allowHost)) {
    echo 'access Forbidden';
    exit(0);
}
// 设置cache目录
FileSystemCache::$cacheDir = './cache';
// 读取参数
$pageNum  = ($_GET['pn'] ?: 1) * 44;
$keyword  = $_GET['keyword'] ?: '';
$callback = $_GET['callback'] ?: '';
// 请求cache
$keyStr  = md5($keyword . $pageNum);
$key     = FileSystemCache::generateCacheKey($keyStr);
$outJson = FileSystemCache::retrieve($key);
if ($outJson !== false) {
    header('Content-type:text/json');
    echo $outJson;
    exit(0);
}
// 请求淘宝参数
$hostsArr = [
    '140.205.134.25',
    '140.205.164.47',
    '106.11.15.99',
    '140.205.230.49',
    '106.11.14.99',
    '140.205.172.65',
];
$hosts    = $hostsArr[array_rand($hostsArr)];
$url      = 'https://' . $hosts . '/search?data-key=s&data-value=' . $pageNum . '&ajax=true&ie=utf8&search_type=item&sort=sale-desc&q=' . $keyword . '&_input_charset=utf-8&callback=' . $callback;
$cookie   = '_tb_token_=F0MgpntSANW3; cna=FhZPEBpcT0oCAdy1q0qHv8VD; _med=dw:1440&dh:900&pw:2880&ph:1800&ist:0; v=0; cookie2=183b42357901840d49fecbd9bb94ae54; t=1f9bd58fbe66f9f629db13c7bd453b13; mt=ci%3D-1_0; l=AlRUAMjhCYaPVWjG2ldHBzqwpJjG3XiX; isg=AsbGrfSh7Rkkorn9yEgHWdKMF7qgZArhiOZ6N7DvwunEs2fNGLd58fUB_ViF; hng=CN%7Czh-cn%7CCNY; thw=cn';
$ip       = getRandIp();
$header   = [
    'host:s.taobao.com',
    'client-ip:' . $ip,
    'x-forwarded-for:' . $ip,
];
$referer  = 'https://taobao.com/' . $ip;
// 请求
$body   = request($url, $header, $referer, $cookie);
// 处理结果
$body   = preg_replace('/.+?({.+}).+/', '$1', $body);
$result = json_decode($body, true);
$itemList = $result['mods']['itemlist']['data']['auctions'];
$outList  = [];
if (!empty($itemList)) {
    foreach ($itemList as $item) {
        $outList[] = [
            'nid'         => $item['nid'],
            'raw_title'   => $item['raw_title'],
            'pic_url'     => $item['pic_url'],
            'detail_url'  => $item['detail_url'],
            'view_sales'  => $item['view_sales'],
            'user_id'     => $item['user_id'],
            'nick'        => $item['nick'],
            'comment_url' => $item['comment_url'],
        ];
    }
}
$outJson = '';
if(!empty($outList)){
    $outJson = $callback . '(' . json_encode($outList) . ');';
    FileSystemCache::store($key, $outJson, 3600);
}
header('Content-type:text/json');
echo $outJson;
exit(0);
function getRandIp()
{
    $ipAdd = [];
    for ($i = 0; $i <= 3; $i++) {
        $ipAdd[] = mt_rand(1, 255);
    }
    return implode('.', $ipAdd);
}

function request($url, $header, $referer, $cookie)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_REFERER, $referer);
    curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    $body = curl_exec($ch);
    curl_close($ch);
    return $body;
}