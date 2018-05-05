<?php

// ln -s /home/spider/ /home/wwwroot/default/spider

function println($msg = '')
{
    echo "{$msg}\n";
}

function rand_str($len = 11)
{
    return substr(base64_encode(md5(microtime(1))), 0, 11);
}

function rand_ua()
{
    $s1     = mt_rand(20, 65);
    $s2     = mt_rand(1000, 3000);
    $s3     = mt_rand(100, 600);
    $ualist = [
        "Mozilla/5.0 (Windows NT 10.0; WOW64; rv:{$s1}.0) Gecko/20100101 Firefox/{$s1}.0",
        'Mozilla/5.0 (X11; Linux i686; rv:13.0) Gecko/13.0 Firefox/13.0',
        'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; WOW64; Trident/6.0)',
        'Mozilla/5.0 (IE 11.0; Windows NT 6.3; Trident/7.0; .NET4.0E; .NET4.0C; rv:11.0) like Gecko',
        'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.22 (KHTML, like Gecko) Chrome/{$s1}.0.{$s2}.{$s3} Safari/537.22',
        "Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/{$s1}.0.{$s2}.{$s3} Safari/537.36",
    ];
    $ua = $ualist[array_rand($ualist)];
    return $ua;
}

function get_ua()
{
    static $ua = null;
    static $i  = null;
    if (is_null($ua)) {
        $ua = rand_ua();
        $i  = date('i');
    }
    if (date('i') !== $i) {
        $ua = rand_ua();
        $i  = date('i');
    }
    return 'User-Agent: ' . $ua;
}

function get_cookie()
{
    static $bid = null;
    static $i   = null;
    if (is_null($bid)) {
        $bid = rand_str(11);
        $i   = date('i');
    }
    if (date('i') !== $i) {
        $bid = rand_str(11);
        $i   = date('i');
    }
    return 'Cookie: bid=' . $bid;
}

function get($u, $finish = false)
{
    static $ch  = null;
    static $map = null;
    if (is_null($ch)) {
        $ch = curl_init();
    }
    if (is_null($map)) {
        $map = [];
    }
    if ($finish) {
        $info = curl_getinfo($ch);
        curl_close($ch);
        return $info;
    }
    if (isset($map[$u]) || strpos($u, '/icon') !== false) {
        return null;
    }
    println("    -> {$u}");
    usleep(1 * 1000 * 1000);
    $map[$u] = true;
    $headers = [
        'Connection: keep-alive',
        'Cache-Control: max-age=0',
        'Upgrade-Insecure-Requests: 1',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
        'DNT: 1',
        'Referer: https://www.douban.com/group/yuexiuzufang/discussion/',
        'Accept-Encoding: gzip',
        'Accept-Language: zh-CN,zh;q=0.9',
    ];
    $headers[] = get_ua();
    $headers[] = get_cookie();
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_URL, $u);
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $data = curl_exec($ch);
    return $data;
}

$resp = get('https://www.douban.com/group/yuexiuzufang/discussion?start=1');
var_dump($resp);

$info = get(0, true);
var_dump($info);
