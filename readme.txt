## TeamOneHFCM

TeamOneHFCM 是霆万技术团队基于WordPress 框架开发的插件。

### 介绍

***

TeamOneHFCM 的页眉页脚代码管理器可在WordPress 框架中将代码片段添加到固定位置或可选文章，可随意将代码放到指定位置，切换更换主题不会丢失代码片段，拥有即开即用，随时可关的特点。

针对了 WordPress 框架，查询数据量大，冗余数据多等情况，集成增加了RDS缓存链接层，大大减少了对数据库的压力。

### 功能模块

- 基本功能使用

- 数据批量处理工具

- redis 缓存链路设置
- 日志控制设置

### 好处

- 永远不必担心通过添加代码无意中破坏您的网站
- 避免无意中将片段放在错误的位置
- 不再需要十几个或更多的插件来添加一个小代码片段——插件越少越好！
- 切换或更改主题时永远不会丢失您的代码片段
- 确切了解哪些片段正在您的网站上加载、显示位置以及添加者
- 使用短代码手动将代码放在任何地方
- 自带注释标记片段以便于参考
- 插件自带记录用户添加和最后编辑代码段的时间
- 该插件集成了RDS缓存链接层的设置与使用，大大减少了WordPress框架的数据库压力。

### 用处

- 在任何地方和任何帖子/页面上添加无限数量的脚本和样式
- 管理脚本加载的帖子或页面
- 支持自定义帖子类型
- 支持仅在特定帖子或页面或最新帖子上加载的能力
- 控制脚本在页面上的确切加载位置
- 脚本可以控制在桌面或移动设备上加载，启用或禁用一个或另一个

### 插件相关选项

### 脚本代码类型

- HTML
- CSS
- Javascript

### 页面显示选项

- Site wide on every post / page
- Specific Posts
- Specific Pages
- Specific Categories (Archive & Posts)
- Specific Post Types (Archive & Posts)
- Specific Tags (Archive & Posts)
- Home Page
- Search Page
- Archive Page
- Latest Posts
- Shortcode Only

### 分类列表

- 满足筛选分类条件下是否符合脚本显示需求

### 位置选项

- Header
- Footer
- Before Content
- After Content

### 设备选项

- Show on All Devices
- Only Desktop
- Only Mobile Devices

### 激活状态

- Active
- Inactive

### 测试用例redis配置

注：默认读取配置文件配置数据。

```
在wp-config.php文件中增加如下配置：

```
//team-one-hfcm redis 配置

define('HFCM_REDIS_CLIENT', 'pecl'); # 指定用于与Redis通信的客户端, pecl 即 The PHP Extension Community Library

define('HFCM_REDIS_SCHEME', 'tcp'); # 指定用于与Redis实例进行通信的协议
define('HFCM_REDIS_HOST', '127.0.0.1'); # Redis服务器的IP或主机名

define('HFCM_REDIS_PORT', 'xxx'); # Redis端口

define('HFCM_REDIS_DATABASE', '0'); # 接受用于使用该SELECT命令自动选择逻辑数据库的数值

define('HFCM_REDIS_PASSWORD', 'xxx'); # Redis密码

define('HFCM_CACHE_KEY_SALT', 'wp_'); # 设置所有缓存键的前缀（Wordpress多站点模式下使用）

define('HFCM_REDIS_MAXTTL', '86400');

define('HFCM_LOG', true);//team-one-hfcm 是否开启日志
```
```