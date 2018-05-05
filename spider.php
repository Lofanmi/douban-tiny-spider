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

function clean($text)
{
    $search = ['  ', '   ', "\r", "\n", "\t"];
    return str_replace($search, '', $text);
}

function cut_string($str, $star_str, $end_str, $ignore = true)
{
    $start = 0;
    $s_pos = stripos($str, $star_str);
    if (false === $s_pos) {
        return '';
    }
    $n_str = substr($str, $s_pos + strlen($star_str));
    $e_pos = stripos($n_str, $end_str);

    if (!$e_pos) {
        return '';
    }
    $e_str = substr($n_str, $start, $e_pos);
    if (!$ignore) {
        $e_str = $star_str . $e_str;
    }
    return $e_str;
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
        curl_close($ch);
        return;
    }
    if (isset($map[$u]) || strpos($u, '/icon') !== false) {
        return null;
    }
    println("    -> {$u}");
    sleep(1);
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
    // curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_URL, $u);
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $data = curl_exec($ch);
    if (curl_getinfo($ch, CURLINFO_HTTP_CODE) === 302) {
        // 跳到登录页面
        println('!!!! 爬虫已被封禁, 操作停止');
        return 302;
    }
    return $data;
}

function group_name($url)
{
    return cut_string($url, 'group/', '/');
}

function topic_id($url)
{
    $url = rtrim($url, '/') . '/';
    return cut_string($url, 'topic/', '/');
}

function save_img($groupUrl, $topicUrl, $imgUrl)
{
    $ext = pathinfo(parse_url($imgUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
    if (!in_array($ext, ['jpg', 'png'])) {
        return;
    }
    $group = group_name($groupUrl);
    $topic = topic_id($topicUrl);
    $img   = pathinfo(parse_url($imgUrl, PHP_URL_PATH), PATHINFO_BASENAME);
    if (substr($img, 0, 2) !== 'p1') {
        return;
    }
    // if ($img === 'connect_qq.png' || $img === 'connect_sina_weibo.png' || $img==='user_normal.jpg') {
    //     return;
    // }
    if (!is_dir($dir = "{$group}/{$topic}")) {
        mkdir($dir, 0755, true);
    }
    $filename = "{$dir}/{$img}";
    if (is_file($filename) && filesize($filename) > 0) {
        return $filename;
    }
    $data = get($imgUrl);
    if (empty($data)) {
        return null;
    }
    if ($data === 302) {
        return 302;
    }
    if (file_put_contents($filename, $data, LOCK_EX)) {
        return $filename;
    }
}

function detail($groupUrl, $topicUrl, $title, $number, $skips)
{
    $resp = get($topicUrl);
    if (empty($resp)) {
        return false;
    }
    if ($resp === 302) {
        return 302;
    }
    $result              = [];
    $result['topic_url'] = $topicUrl;
    $result['title']     = $title;
    $result['number']    = $number;
    $result['user']      = cut_string($resp, '<span class="from">来自: ', '</span>');
    $result['time']      = cut_string($resp, '<span class="color-green">', '</span>');
    $result['text_raw']  = cut_string($resp, '<div id="link-report" class="">', '<div id="link-report_group">');
    $result['text']      = clean(strip_tags($result['text_raw']));
    $result['imgs']      = [];
    $result['imgs_save'] = [];
    $result['user']      = str_replace('href', "target='_blank' href", $result['user']);
    if (preg_match_all('/img[^s]+src="(.+?)"/i', $resp, $matches)) {
        $result['imgs'] = $matches[1];
        foreach ($result['imgs'] as $img) {
            $filename = save_img($groupUrl, $topicUrl, $img);
            if (empty($filename)) {
                continue;
            }
            if ($filename === 302) {
                return 302;
            }
            $result['imgs_save'][] = $filename;
        }
    }
    if (empty($result['text'])) {
        return false;
    }
    foreach ($skips as $skip) {
        if (strpos($result['text'], $skip) !== false) {
            return false;
        }
    }
    return $result;
}

function init_html($u)
{
    $resp = get($u);
    if ($resp === 302) {
        return;
    }
    $topic = trim(cut_string($resp, '<title>', '</title>'));
    $html  = <<<HTML
<!DOCTYPE html>
<html>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<style>
*{padding: 0;margin: 0; box-sizing: border-box; font-size: 14px; line-height: 24px; font-family: "Helvetica Neue", Helvetica, "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", "微软雅黑", Arial, sans-serif;}
body{background: #f1f1f4; width: 100%}
nav{font-size: 18px; text-align: center;height: 50px; line-height: 50px; color: #fff; background: #1fc7b9}
table{width: 90%; border-collapse: collapse; border: none; margin: 20px auto}
td{padding: 10px; background: #fff; vertical-align: top}
td.empty{background: #f1f1f4; padding: 20px}
a{text-decoration: none; color: #0084ff}
img{width: 100px}
strong{font-weight: normal;color: #1fc7b9}
.intro{width: 20%}
</style>
<script src="http://cdn.bootcss.com/jquery/2.2.4/jquery.js"></script>
<script src="http://cdn.bootcss.com/jquery_lazyload/1.9.7/jquery.lazyload.js"></script>
<script>
$(function() {
    $('img.lazy').lazyload({effect: 'fadeIn'});
});
</script>
<body>

<nav>{$topic}</nav>

<table>

HTML;
    $filename = group_name($u);
    file_put_contents("{$filename}.html", $html);
}

function finish_html($u)
{
    $html     = '</body></html>';
    $filename = group_name($u);
    file_put_contents("{$filename}.html", $html, FILE_APPEND);
}

function append_to_html($d, $u)
{
    $html = "<tr>\n";
    // user time
    $html .= "<td class='intro'>\n";
    $html .= "<strong>计数: {$d['number']}</strong><br>\n";
    $html .= "{$d['user']}<br>\n";
    $html .= "<strong>发布时间: {$d['time']}</strong><br>\n";
    $html .= "<a target='_blank' href='{$d['topic_url']}'>原文链接</a><br>\n";
    $html .= "<br>";
    $html .= "<strong>{$d['title']}</strong><br>\n";
    $html .= "</td>\n";
    // text
    $html .= "<td class='text'>\n";
    $html .= "{$d['text']}\n";
    $html .= "</td>\n";
    $html .= "</tr>\n";
    // imgs
    $html .= "<tr>\n";
    $html .= "<td class='imgs' colspan='2'>\n";
    foreach ($d['imgs_save'] as $img) {
        $html .= "<img class='lazy' data-original='{$img}'>\n";
    }
    $html .= "</td>\n";
    $html .= "<tr><td class='empty' colspan='2'></td></tr>\n";
    $html .= "</tr>\n\n";
    $filename = group_name($u);
    file_put_contents("{$filename}.html", $html, FILE_APPEND);
}

function run($url, $count = 1000)
{
    $map   = [];
    $skips = ['招租', '单间', '求租', '求合租', '寻', '招合租', '个人', '已租', '已出租', '找室友', '招室友', '求室友', '已经租', '找舍友', '招舍友', '求舍友'];
    $resp  = init_html($url);
    if ($resp === 302) {
        return;
    }
    $start  = -25;
    $number = 1;
    while (1) {
        $start += 25;
        $u = "{$url}{$start}";
        $c = get($u);
        if (empty($c)) {
            continue;
        }
        if ($c === 302) {
            break;
        }
        $c = cut_string($c, '<table class="olt">', '</table>');
        if (!preg_match_all('/href="(.+?)" title="(.+?)"/i', $c, $matches)) {
            continue;
        }
        foreach ($matches[2] as $i => $title) {
            if ($number > $count) {
                break 2;
            }
            foreach ($skips as $skip) {
                if (strpos($title, $skip) !== false) {
                    continue 2;
                }
            }
            $topicUrl = $matches[1][$i];
            $title    = $matches[2][$i];
            $detail   = detail($u, $topicUrl, $title, $number, $skips);
            if (empty($detail)) {
                continue;
            }
            if ($detail === 302) {
                break 2;
            }
            if (!isset($map[$detail['topic_url']])) {
                println(sprintf('%05d: %s %s', $number, $title, $topicUrl));
                append_to_html($detail, $u);
                $number++;
                $map[$detail['topic_url']] = true;
            }
        }
    }
    finish_html($url);
}

if (count($argv) !== 2) {
    println('usage:');
    println('    天河租房: php spider.php tianhezufang');
    println('    越秀租房: php spider.php yuexiuzufang');
    println('    海珠租房: php spider.php haizhuzufang');
    println('    番禺租房: php spider.php panyuzufang');
    println('    (小组名): php spider.php group_name');
    println('url:');
    println('    天河租房: http://hostname/tianhezufang.html');
    println('    越秀租房: http://hostname/yuexiuzufang.html');
    println('    海珠租房: http://hostname/haizhuzufang.html');
    println('    番禺租房: http://hostname/panyuzufang.html');
    println('    (小组名): http://hostname/group_name.html');
    die();
}

$group = $argv[1];

$url = "https://www.douban.com/group/{$group}/discussion?start=";
run($url, 1000);
get('', true);
