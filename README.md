# douban-tiny-spider

## 豆瓣租房小爬虫

豆瓣租房小爬虫, 用PHP临时撸的, 逻辑非常简单.

## 特点

- 没有数据库, 即爬即用
- 爬过的图片不会再爬一次
- 自动把租房信息输出成 HTML, 可在PC端和移动端查看.
- 每分钟换一次 User-Agent 和 Cookie
- 每爬一次睡眠 1 秒, 爬慢点不会被封
- 为了快, 代码写得特别挫, 找时间慢慢优化

## Usage

天河租房:
```
# http://hostname/tianhezufang.html
php spider.php tianhezufang
```
越秀租房:
```
# http://hostname/yuexiuzufang.html
php spider.php yuexiuzufang
```
海珠租房:
```
# http://hostname/haizhuzufang.html
php spider.php haizhuzufang
```
番禺租房:
```
# http://hostname/panyuzufang.html
php spider.php panyuzufang
```
(小组名):
```
# http://hostname/group_name.html
php spider.php group_name
```
