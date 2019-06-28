# frame
一个追求使用简便、高并发、易用的 PHP 框架，由一个个 PHP 组件组成，通过不同的搭配方式，可以产生不同的应用架构，详细文档请访问 http://php-frame.cn

# 框架由来

之前使用过很多的框架，都或多或少的在做企业应用或者快速开发时缺乏点什么，我对一个现代框架的需求有：

* 不能有太差的执行性能
* 具有做分布式服务的能力
* 可以提高交付效率
* 开发者很难犯错

基于这几个理想，我在开发这个框架的时候遵循了以下原则：

* 框架层面的逻辑因为会趋于稳定且维护频率不高，减少逻辑层次，去掉对象树，直接换为 function，领域层为了提高交付效率、简化开发继续使用 OOP
* 考虑 PHP-FPM 单进程顺序执行的模型，路由等匹配类的框架环节由 “遍历注册 -> 遍历匹配” 的模式简化为 “遍历匹配”
* 提供了有效分离 “逻辑” 与 “持久化” 的执行时间的 “工作单元”，配套的 “ID 生成器”，用以提高数据库的使用效率，也拥有了数据库分布式的基础
* 用闭包来实现成对逻辑以约束研发在同一个作用域层面写成对的逻辑，如事务开始及提交的函数 db_transaction、工作单元开始及提交的函数 unit_of_work
* 阅读效率的强迫症优化，要求本项目内的变量类名，全部使用小写与下划线的方式来保持英文词语拥有稳定间隔（空格 or 下划线）
* 快捷的开发辅助工具，如快捷生成 entity、dao、migration 工具 entity:make

# 目录结果及文件说明
```
frame  
├── cache (缓存类功能文件目录)  
│   ├── demo.php  
│   ├── memcache.php (memcache 缓存)  
│   └── redis.php (redis 缓存)  
├── database (数据库类功能文件目录)  
│   ├── demo.php  
│   └── mysql.php (mysql 数据库)  
├── dialogue (对话功能文件目录)  
├── queue (队列类功能文件目录)  
│   ├── demo.php  
│   └── beanstalk.php (beanstalk 队列)  
├── http (http 入口类功能文件目录)  
│   ├── php_fpm  
│   │   ├── application.php (web 能力)  
│   │   ├── distributed_client.php (分布式服务 client 能力)  
│   │   └── distributed_service.php (分布式服务 service 能力)  
│   └── swoole  
│        └── application.php (web 能力)  
├── lock (锁类功能文件目录)  
│   ├── demo.php  
│   └── cache.php (使用缓存实现锁)  
├── storage (非 SQL 类存储功能文件目录)  
│   ├── demo.php  
│   └── mongodb.php (mongodb 实现)  
├── command.php (命令行能力)  
├── entity.php (ORM 能力)  
├── function.php (辅助函数)  
├── otherwise.php (断言能力)  
└── unitofwork.php (工作单元能力)  
```
# 已搭配好的架构

[小API架构 api_frame](https://github.com/smarty-kiki/api_frame)  
[小MVC架构 mvc_frame](https://github.com/smarty-kiki/mvc_frame)  
  
[分布式架构应用层框架 distributed_api_frame](https://github.com/smarty-kiki/distributed_api_frame)  
[分布式架构应用层框架 distributed_mvc_frame](https://github.com/smarty-kiki/distributed_mvc_frame)  
[分布式架构应用层框架 distributed_cli_frame](https://github.com/smarty-kiki/distributed_cli_frame)  
[分布式架构服务层框架 distributed_service_frame](https://github.com/smarty-kiki/distributed_service_frame)  
  
分布式框架需组合应用层与服务层来使用
