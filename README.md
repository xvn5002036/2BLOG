# 2BLOG
A Simplized Wordpress Blog Theme Design &amp; Developed from 2broear.com by 2BROEAR Released &amp; openSourced in 2022.

__Preview Site__ ：http://2blog.2broear.com

## 主题简介
历经半年之久的周期，2BLOG也算是可以开启测试了，这里将作为主题开源后的发布、更新、备份用途。目前尚处测试阶段，未上传至 Wordpress 主题。

2BLOG在前静态主题的外观及功能基础之上做了部分修改及更新，其中主要更新内容包括：
- 页面/首页文章置顶
- 多级富文本、元数据导航
- 自定义 RSS、SITEMAP 内容
- 自定义搜索内容、列表样式
- 新增漫游影视及资源下载页面子级
- 部分页面支持 wordpress 与 leancloud 数据切换
- 重写前端逻辑，移除主要 jquery 依赖

当然了，最重要的还是集成了 __Leancloud 与 Wordpress 之间的数据同步__ ，这个主要是因为之前静态博客使用的是 __valine__ 评论系统（其实之前很少使用 leancloud 数据储存），然后我自己改了很多东西，所以在 wordpress 中仍做了数据切换，顺带更新了之前尚未同步数据到 leancloud 的页面。

> 在wordpress中除“公告”外所有数据均以文章形式发布，通过后端函数调用数据，而使用leancloud数据的页面将通过 __lbms__ 后台进行数据上传、修改及删除等操作，再通过xhr异步前端调用json数据

## 主题文档
简单说明一下，本人非专业的wordpress开发者，这款主题也是职业之余开发完成的，其中的部分页面功能并不能保证适用所有人（之前还鸽了HEXO的主题开发，主要是文档太难找了）做成cms主题主要是因为之前静态博客内容多了之后不好管理的问题（后来甚至做了个markdown的编辑器，不过没用就是了），开源也是因为之前得到了部分博友的认可，都有意向使用这个主题，那么正巧公司的框架去年也搬到wp了所以整个开发流程是相对顺利的，所有功能在主题之间都能通用这一点还是很友好的。 __以下分为wp及lbms两方面来简单介绍下__

### Wordpress 后台

wordpress 后台设置分为 5 个版面，每个版面对应不同的设置选项：

1.  __基本信息__ 
2.  __通用控制__ 
3.  __页面设置__ 
4.  __侧栏设置__ 
5.  __页尾控制__ 

首先 __基本信息__ 只中有 5 个选项，可以修改个人昵称（注意非博客名称）、头像及卡片背景图，包括全站的描述及关键词（单页分类的关键词及各项配置需在 __文章->分类__ 中单独配置）

而在 __通用控制__ 中所拥有控制选项是整个主题最多也是最复杂的，主要包含 __主题颜色__ 、 __LOGO__ 、 __公告__ 、 __元导航__ 、 __面包屑导航__ 、 __Gravatar头像__ 、 __sitemap__ 、 __rss feed__ 、 __搜索结果/样式__ 、 __暗黑模式__ 、 __主题颜色__ 、 __Leancloud数据切换__ 、 __第三方 valine 评论__ 、 __评论提醒（邮件、微信）__ 、 __站点CDN__ ...

> 启用 leancloud 设置需要到 leancloud.cn 控制台 __创建应用__ ，然后在 __设置->域名绑定__ 中设置 __API 访问域名__ （二级域名国内需要备案），之后再将博客域名添加到 __设置->安全中心__ 的 __Web 安全域名__ 中，之后在数据储存中创建名称为 __*分类模板名称__ （如 __category-weblog.php__ 模板名称为 __“news”__ ）的 Class 后即可在 wp 后台将已创建的 appid/appkey/serverurl（二级域名）填入保存即可。 __第三方评论 Valine__ 的配置流程大同小异，可参考资料 [Valine](https://valine.js.org)


> 开启 __评论微信提醒__ 功能后需要 __注册企业微信__ 登录完善信息后在 __应用管理->自建__ 中 __创建应用__ ，创建应用完成后，在 __我的企业__ 选项卡中获取 __企业ID__ ，之后在 __应用管理->自建__ 中找到刚刚创建的应用，点进去可找到 __AgentId__ 和 __Secret__ 。自此，企业应用配置完成，将 企业id、AgentId、Secret 填入后台对应值即可。注意：开启后需使用微信扫描企业微信中 __我的企业->微信插件__ 栏目中的 __邀请关注__ 栏目二维码后才能收到通知！ __微信推送消息类型中的  _模板卡片_  仅能在 企业微信 中收到信息__

_其他选项（如rss/sitemap等）只需要对应设置选项下方的提示操作即可。_

## 部分差异化引发的问题
这款主题和官方在模板设计上有些许不同，通过 wp 默认主题模板文件不难看出一款 wordpress 主题在导航上是通过页面来进行导航的。但是，我之前一直都是用的分类进行页面导航，使用分类页面无法调用评论而百思不得其解的时候到处瞎逛论坛的时候才发现 __wp 根本不支持通过分类调用评论，__ 这也就是说之前写的那套定制导航的逻辑全都不都糊了，因为主题 __部分页面在调用页面数据的同时需要调用页面评论__ ，这个就很尴尬了，而且通过分类来导航很难控制页面层级关系，再三犹豫期间又跑去写了一个“页面”导航，结果差强人意， __最后还是选择了使用分类作为页面导航__ ，同时在解决调用页面评论这方面的方案则是分类 __固定链接的 url 重写__ ，该方案在伪静态下工作的很好。

不过还要解决之前静态主题的一个层级问题，需要部分分类别名为“/”实现略过父级访问子级链接，那么这个 wordpress 不支持这个操作，所以只能通过 $wpdb 来强制写入 __（这里就涉及了分类与页面直接的操作数据互相同步）__ ，好在问题目前是得以解决，然而这一连串的问题如果通过页面来做导航就完全不存在了。
