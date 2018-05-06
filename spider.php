<?php

// ln -s /home/spider/ /home/wwwroot/default/spider

if (count($argv) !== 2) {
    println('usage:', false);
    println('    天河租房: php spider.php tianhezufang', false);
    println('    越秀租房: php spider.php yuexiuzufang', false);
    println('    海珠租房: php spider.php haizhuzufang', false);
    println('    番禺租房: php spider.php panyuzufang', false);
    println('    (小组名): php spider.php group_name', false);
    println('url:', false);
    println('    天河租房: http://hostname/tianhezufang.html', false);
    println('    越秀租房: http://hostname/yuexiuzufang.html', false);
    println('    海珠租房: http://hostname/haizhuzufang.html', false);
    println('    番禺租房: http://hostname/panyuzufang.html', false);
    println('    (小组名): http://hostname/group_name.html', false);
    die();
}

try {
    $groupName = $argv[1];
    $util      = 2;
    $spider    = new Spider($groupName, $util);
    $spider->run();
} catch (BlockException $e) {
    println("!!!! " . $e->getMessage());
}

// -----------------------------------------------------------------------------
// -- 类库
// -----------------------------------------------------------------------------

/**
 * 封禁异常
 */
class BlockException extends Exception
{}

/**
 * 爬虫类
 */
class Spider
{
    protected $groupName;
    protected $util;

    protected $step = 25;

    protected $ch;

    protected $done = [];

    protected $skips = [
        '已出租', '已经租', '已租',
        // '找舍友', '招舍友', '求舍友',
        // '找室友', '招室友', '求室友',
        // '求合租', '找合租', '招合租',
        // '求租',
    ];

    protected $defaultHeaders = [
        'Connection: keep-alive',
        'Cache-Control: max-age=0',
        'Upgrade-Insecure-Requests: 1',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
        'DNT: 1',
        'Accept-Encoding: gzip',
        'Accept-Language: zh-CN,zh;q=0.9',
        'Referer: https://www.douban.com/group/yuexiuzufang/discussion/',
    ];

    protected $minute;
    protected $bid;
    protected $userAgent;

    protected $sleep = 1;

    public function __construct($groupName, $util)
    {
        date_default_timezone_set('PRC');
        $this->groupName = $groupName;
        $this->util      = $util;
        // cURL
        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_ENCODING, 'gzip');
        curl_setopt($this->ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
        // curl_setopt($this->ch, CURLOPT_HEADER, 1);
        $this->minute    = date('i');
        $this->bid       = str_rand(11);
        $this->userAgent = rand_user_agent();
    }

    public static function testBlock()
    {
        try {
            $groupName = $argv[1];
            $util      = 1;
            $spider    = new static($groupName, $util);
            $spider->run();
        } catch (BlockException $e) {
            println("!!!! " . $e->getMessage());
            var_dump(curl_getinfo($this->ch));
        }
    }

    public function run()
    {
        // 开始时间
        $time = time();
        // 开始位置
        $start = 0;
        // 计数器
        $number = 1;
        // 循环爬取
        while (1) {
            $u = "https://www.douban.com/group/{$this->groupName}/discussion?start={$start}";
            // 跳过空响应
            if (empty($c = $this->getUrl($u))) {
                $start += $this->step;
                continue;
            }

            // 第一次爬取的时候初始化 HTML 文件
            if ($start === 0) {
                $this->initHtml($c);
            }

            // 更新下一次爬取的位置
            $start += $this->step;

            // 提取 HTML 内容
            $c = str_cut($c, '<table class="olt">', '</table>');
            // 所有话题
            if (!preg_match_all('/href="(.+?)" title="(.+?)"/i', $c, $matches)) {
                continue;
            }

            // 逐话题遍历: 提取具体内容并下载图片, 最终输出到 HTML 文件
            foreach ($matches[2] as $i => $title) {
                // 爬够数量就不爬了
                if ($number > $this->util) {
                    break 2;
                }
                $topicUrl = $matches[1][$i];

                // 判断是否已经爬过了
                if (isset($this->done[$topicUrl])) {
                    // 打印一条已经爬取的日志
                    $this->getUrl($topicUrl);
                    continue;
                }

                // 打印当前正在爬取的话题
                println(sprintf('%05d: %s %s', $number, $title, $topicUrl));

                // 获取话题详情
                if (empty($detail = $this->detail($topicUrl, $title, $number))) {
                    continue;
                }

                // 输出到 HTML 文件中
                $this->appendHtml($detail, $u);

                // 递增计数器
                $number++;
            }
        }
        // 填充 HTML 结束标签
        $this->finishHtml();
        // 计算运行时间
        $time = time() - $time;
        println("抓取结束 用时 {$time} 秒");
    }

    protected function initHtml($resp)
    {
        $topic = trim(str_cut($resp, '<title>', '</title>'));
        file_put_contents("{$this->groupName}.html", str_init_html($topic));
    }

    protected function appendHtml($d)
    {
        if (!is_file($filename = "{$this->groupName}.html")) {
            return;
        }
        $imgs = '';
        foreach ($d['imgs_save'] as $img) {
            $imgs .= "<img class='lazy' data-original='{$img}'>";
        }
        $html = str_detail_html($d, $imgs);
        file_put_contents($filename, $html, FILE_APPEND);
    }

    protected function finishHtml()
    {
        if (is_file($filename = "{$this->groupName}.html")) {
            file_put_contents($filename, "\n</body>\n</html>", FILE_APPEND);
        }
    }

    protected function detail($topicUrl, $title, $number)
    {
        if ($this->skip($title)) {
            println('    -> 跳过: 标题出现关键词');
            return;
        }

        // 跳过空响应
        if (empty($resp = $this->getUrl($topicUrl))) {
            println('    -> 获取话题内容失败');
            return;
        }

        // 话题信息
        $result = [];

        // HTML
        $result['text_raw'] = str_cut($resp, '<div id="link-report" class="">', '<div id="link-report_group">');

        // 纯文本
        $text           = str_replace("\n", "<br>", strip_tags($result['text_raw']));
        $text           = trim($text, '<br><br>');
        $result['text'] = str_clean($text);
        if (empty($result['text'])) {
            println('    -> 截取后话题内容为空');
            return;
        }
        if ($this->skip($result['text'])) {
            println('    -> 跳过: 内容出现关键词');
            return;
        }

        // 原文链接
        $result['topic_url'] = $topicUrl;

        // 标题
        $result['title'] = $title;

        // 计数
        $result['number'] = $number;

        // 时间
        $result['time'] = str_cut($resp, '<span class="color-green">', '</span>');

        // 发帖人
        $result['user'] = str_cut($resp, '<span class="from">来自: ', '</span>');
        $result['user'] = str_replace('href', "target='_blank' href", $result['user']);

        // 图片原始链接列表
        $result['imgs_url'] = [];

        // 本地图片列表
        $result['imgs_save'] = [];

        // 查找图片并下载到本地
        if (preg_match_all('/img[^s]+src="(.+?)"/i', $result['text_raw'], $matches)) {
            $result['imgs_url'] = $matches[1];
            foreach ($result['imgs_url'] as $imgUrl) {
                if ($filename = $this->saveimg($imgUrl, topic_id($topicUrl))) {
                    $result['imgs_save'][] = $filename;
                }
            }
        }

        // 返回话题信息
        return $result;
    }

    protected function saveimg($imgUrl, $topicId)
    {
        // 获取文件名
        $img = pathinfo(parse_url($imgUrl, PHP_URL_PATH), PATHINFO_BASENAME);

        // 描述里面的图片文件名都是以 p 开头的 (p1占大多数)
        // https://img3.doubanio.com/view/group_topic/large/public/p116091504.jpg
        // https://img3.doubanio.com/view/group_topic/l/public/p116436464.webp
        // https://img1.doubanio.com/view/group_topic/l/public/p44069977.webp
        if (substr($img, 0, 1) !== 'p') {
            println("    -> 忽略图片: {$imgUrl}");
            return;
        }

        // 扩展名过滤
        $ext = explode('.', $img)[1] ?? '';
        if (!in_array($ext, ['jpg', 'png', 'webp'])) {
            println("    -> 扩展名过滤: {$imgUrl}");
            return;
        }

        // 建立文件夹
        if (!is_dir($dir = "{$this->groupName}/{$topicId}")) {
            mkdir($dir, 0755, true);
        }

        // 如果图片已存在, 不重复下载
        $filename = "{$dir}/{$img}";
        if (is_file($filename) && filesize($filename) > 0) {
            println("    -> 图片已下载: {$imgUrl} {$filename}");
            return $filename;
        }

        // 下载图片
        if (empty($data = $this->getUrl($imgUrl))) {
            println("    -> 图片下载失败: {$imgUrl}");
            return;
        }

        // 保存图片, 成功返回文件名
        if (file_put_contents($filename, $data, LOCK_EX)) {
            return $filename;
        }
    }

    protected function getUserAgent()
    {
        if (date('i') !== $this->minute) {
            $this->minute    = date('i');
            $this->bid       = str_rand(11);
            $this->userAgent = rand_user_agent();
        }
        return "User-Agent: {$this->userAgent}";
    }

    protected function getCookie()
    {
        if (date('i') !== $this->minute) {
            $this->minute    = date('i');
            $this->bid       = str_rand(11);
            $this->userAgent = rand_user_agent();
        }
        // dbcl2="xxx"
        return "Cookie: bid={$this->bid}";
    }

    protected function getUrl($u)
    {
        // 不重复爬取
        if (isset($this->done[$u])) {
            println("    -- {$u}");
            return;
        }

        sleep($this->sleep);

        // 打印正在爬取的链接
        println("    >> {$u}");

        $this->done[$u] = true;

        // headers
        $headers   = $this->defaultHeaders;
        $headers[] = $this->getUserAgent();
        $headers[] = $this->getCookie();
        curl_setopt($this->ch, CURLOPT_URL, $u);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);

        // 抓取
        $data = curl_exec($this->ch);

        // 爬太快会重定向到登录页
        if (curl_getinfo($this->ch, CURLINFO_HTTP_CODE) === 302) {
            throw new BlockException("爬虫已被封禁, 操作停止. {$u}");
        }

        // 返回抓取结果
        return $data;
    }

    protected function skip($text)
    {
        foreach ($this->skips as $skip) {
            if (strpos($text, $skip) !== false) {
                return true;
            }
        }
        return false;
    }

    public function __destruct()
    {
        curl_close($this->ch);
    }
}

// -----------------------------------------------------------------------------
// -- 函数库
// -----------------------------------------------------------------------------

/**
 * 打印一行消息.
 *
 * @param  string $msg
 * @param  bool   $printDatetime
 */
function println($msg = '', $printDatetime = true)
{
    if ($printDatetime) {
        $time = date('Y-m-d H:i:s');
        echo "[{$time}] {$msg}\n";
    } else {
        echo "{$msg}\n";
    }
}

/**
 * 生成指定长度的随机字符串.
 *
 * @param  integer $len 长度
 * @return string
 */
function str_rand($len = 11)
{
    return substr(base64_encode(md5(microtime(1))), 0, $len);
}

/**
 * 删除字符串的空白字符.
 *
 * @param  string $text
 * @return string
 */
function str_clean($text)
{
    $search = ['  ', '   ', "\r", "\n", "\t"];
    return trim(str_replace($search, '', $text));
}

/**
 * 字符串截取函数.
 *
 * @param  string  $str      欲截取的字符串
 * @param  string  $start    开始字符串
 * @param  string  $end      结束字符串
 * @param  boolean $ignore   是否忽略开始字符串
 * @return string            返回截取结果
 */
function str_cut($str, $startStr, $endStr, $ignore = true)
{
    $start    = 0;
    $startPos = stripos($str, $startStr);
    if (false === $startPos) {
        return '';
    }
    $str2   = substr($str, $startPos + strlen($startStr));
    $endPos = stripos($str2, $endStr);
    if (!$endPos) {
        return '';
    }
    $result = substr($str2, $start, $endPos);
    if (!$ignore) {
        $result = $startStr . $result;
    }
    return $result;
}

/**
 * 生成随机的 User-Agent.
 *
 * @return string
 */
function rand_user_agent()
{
    $s1     = mt_rand(20, 65);
    $s2     = mt_rand(1000, 3000);
    $s3     = mt_rand(100, 600);
    $ualist = [
        'Mozilla/5.0 (X11; Linux i686; rv:13.0) Gecko/13.0 Firefox/13.0',
        'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; WOW64; Trident/6.0)',
        'Mozilla/5.0 (IE 11.0; Windows NT 6.3; Trident/7.0; .NET4.0E; .NET4.0C; rv:11.0) like Gecko',
        'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.22 (KHTML, like Gecko) Chrome/{$s1}.0.{$s2}.{$s3} Safari/537.22',
        "Mozilla/5.0 (Windows NT 10.0; WOW64; rv:{$s1}.0) Gecko/20100101 Firefox/{$s1}.0",
        "Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/{$s1}.0.{$s2}.{$s3} Safari/537.36",
    ];
    $ua = $ualist[array_rand($ualist)];
    return $ua;
}

/**
 * 获取小组名称.
 *
 * @param  string $url 地址
 * @return string      名称
 */
function group_name($url)
{
    return str_cut($url, 'group/', '/');
}

/**
 * 获取话题ID.
 *
 * @param  string $url 地址
 * @return string      话题ID
 */
function topic_id($url)
{
    $url = rtrim($url, '/') . '/';
    return str_cut($url, 'topic/', '/');
}

/**
 * 初始化 HTML 内容.
 *
 * @param  string $group 小组名称
 * @return string
 */
function str_init_html($group)
{
    return <<<HTML
<!DOCTYPE html>
<html>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<style>
* {
    padding: 0;
    margin: 0;
    box-sizing: border-box;
    font-size: 14px;
    line-height: 24px;
    font-family: "Helvetica Neue", Helvetica, "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", "微软雅黑", Arial, sans-serif;
}
body {
    background: #f1f1f4;
    width: 100%;
}
a {
    text-decoration: none;
    color: #0084ff;
}
img {
    width: 100px;
}
nav {
    font-size: 16px;
    text-align: center;
    height: 50px;
    line-height: 50px;
    color: #fff;
    background: #1fc7b9;
}
strong {
    font-weight: normal;
    color: #1fc7b9;
}
.item {
    margin: 10px;
    padding: 10px;
    background: #fff;
}
</style>
<script src="http://cdn.bootcss.com/jquery/2.2.4/jquery.js"></script>
<script src="http://cdn.bootcss.com/jquery_lazyload/1.9.7/jquery.lazyload.js"></script>
<script>
$(function() {
    $('img.lazy').lazyload({effect: 'fadeIn'});
});
</script>
<body>

<nav>{$group}</nav>

HTML;
}

/**
 * 详情 HTML.
 *
 * @param  string $d    详情
 * @param  string $imgs img HTML
 * @return string
 */
function str_detail_html($d, $imgs)
{
    return <<<HTML

<section class="item">
    <div class="content">
        <strong>计数: {$d['number']}</strong><br>
        {$d['user']}<br>
        <strong>{$d['time']}</strong><br>
        <a target="_blank" href="{$d['topic_url']}">豆瓣原文链接</a><br>
        <br><strong>石牌桥~岗顶德欣小区挺好的二房</strong><br>
        {$d['text']}
        {$imgs}
    </div>
</section>

HTML;
}
