
# GD Studio 在线音乐平台 API (AI 重构版)

最后更新: 2025年5月5日

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE) **重要声明:**
* 本项目代码旨在**仅供学习和研究目的**使用。
* 项目核心功能依赖于对第三方音乐平台接口的调用，这些接口可能并非公开或随时可能变更。使用本 API 获取的任何音乐资源**严禁用于任何商业用途**。
* 用户需自行承担使用本 API 可能带来的**所有法律风险和责任**。对于任何因使用本 API 而导致的版权纠纷或法律问题，开发者（包括原始作者、GD Studio 及 AI 重构者）概不负责。
* 若您选择部署和使用此 API，请务必在显著位置注明音乐数据的来源（例如，感谢 GD 音乐台及原始音乐平台）并遵守相关平台的版权政策。
* 强烈建议不要在生产环境或公开网络中部署未经仔细评估和加固的版本。

## 描述

本项目是 GD Studio 在线音乐平台 API 的一个 AI 重构版本。原 API 基于开源项目 Meting (by metowolf) 和 MKOnlineMusicPlayer (by mengkun)，并由 GD Studio 修改。本次重构旨在应用现代 PHP 编程实践、面向对象设计和更清晰的项目结构来组织代码，同时保留其核心功能。

重构后的 API 封装了 Meting v1.5.20 的核心逻辑，提供音乐搜索、歌曲链接获取、专辑封面获取和歌词获取功能。

## 功能特性

* **音乐搜索**: 根据关键词搜索歌曲、歌手或专辑。
* **歌曲链接**: 获取指定歌曲的可播放链接及音质信息。
* **专辑封面**: 获取指定专辑或歌曲的封面图片链接。
* **歌词获取**: 获取指定歌曲的 LRC 格式歌词（可能包含翻译）。
* **多源支持 (基于 Meting v1.5.20)**:
    * 网易云音乐 (netease)
    * QQ 音乐 (tencent)
    * 酷狗音乐 (kugou)
    * 酷我音乐 (kuwo)
    * *(注意: 原 API 文档中提到的其他源在此 Meting 版本中不支持)*

## 技术栈

* **PHP**: 8.1 或更高版本
* **Composer**: 用于依赖管理和 PSR-4 自动加载
* **设计模式**: 面向对象编程 (OOP), 适配器模式 (Adapter Pattern), 依赖注入 (DI)
* **编码标准**: PSR-4 (Autoloading), PSR-12 (代码风格，结构遵循)
* **架构**: 分层结构 (入口点, HTTP 处理, 业务逻辑处理, 数据源适配)

## 项目结构

```

project-root/
├── public/           \# Web 服务器入口目录 (Web Root)
│   └── index.php     \# 应用程序唯一入口文件
├── src/              \# PHP 源代码目录
│   ├── Adapters/       \# 数据源适配器 (封装旧代码或实现新源)
│   │   └── MetingAdapter.php \# 封装 Meting v1.5.20 的适配器
│   ├── Enums/          \# PHP 8.1 枚举类型
│   │   ├── ApiType.php     \# API 请求类型
│   │   └── MusicSource.php \# 支持的音乐源
│   ├── Handler/        \# 请求业务逻辑处理器
│   │   └── ApiHandler.php
│   ├── Http/           \# HTTP 相关辅助类
│   │   └── JsonResponse.php \# 用于发送 JSON 响应
│   ├── Interfaces/     \# 接口定义
│   │   └── MusicSourceAdapterInterface.php \# 音乐源适配器必须实现的接口
│   └── Legacy/         \# 遗留代码存放目录 (需要封装)
│       └── Metowolf/
│           └── Meting.php \# 调整后的 Meting v1.5.20 核心代码
├── vendor/           \# Composer 依赖目录 (自动生成)
├── composer.json     \# Composer 项目配置文件
└── composer.lock     \# Composer 依赖锁定文件

````

## 安装与设置

**环境要求:**

* PHP >= 8.1
* PHP 扩展: `curl`, `json`, `openssl`, `bcmath`, `mbstring`, `dom`
* Composer

**安装步骤:**

1.  **克隆仓库** (如果项目托管在 Git 仓库):
    ```bash
    git clone <your-repository-url>
    cd <project-directory>
    ```
    或者，直接将代码文件下载到您的服务器。

2.  **安装 Composer 依赖**:
    在项目根目录下运行 `composer install`。这将生成 `vendor` 目录和自动加载文件。
    ```bash
    composer install --no-dev --optimize-autoloader # 推荐用于生产环境
    ```

3.  **放置 Meting 核心代码**:
    * **这是必须的手动步骤!**
    * 获取 Meting v1.5.20 的 `Meting.php` 文件代码 (您之前提供的版本)。
    * **重要:** 编辑此文件，**移除**文件顶部的 `namespace Metowolf;` 声明（如果存在）。
    * 将修改后的文件放置在项目中的 `src/Legacy/Metowolf/Meting.php` 路径下。**没有这个文件或文件不正确，API 将无法工作。**

4.  **配置 Web 服务器**:
    * 将 Web 服务器的文档根目录 (Document Root / root) 指向本项目的 `public/` 目录。
    * 配置 URL 重写规则，将所有 API 请求都转发到 `public/index.php` 处理。

    **Apache (.htaccess 文件放在 `public/` 目录下):**
    ```apache
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} -f [OR]
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^ - [L]
    RewriteRule ^ index.php [L]
    ```

    **Nginx (在 server block 中配置):**
    ```nginx
    server {
        listen 80;
        server_name your-api-domain.com; # 替换为你的域名
        root /path/to/your/project-root/public; # 指向 public 目录
        index index.php;

        location / {
            try_files $uri $uri/ /index.php?$query_string;
        }

        location ~ \.php$ {
            try_files $uri =404;
            fastcgi_pass unix:/var/run/php/php8.1-fpm.sock; # 根据你的 PHP-FPM 配置修改
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            include fastcgi_params;
        }

        location ~ /\. {
            deny all;
        }
    }
    ```

5.  **权限设置**: 确保 Web 服务器用户对 `vendor/` (如果需要缓存或其他写入操作) 和可能的日志文件有适当的写入权限。通常 `public/` 目录下的文件只需要读取权限。

6.  **PHP 配置**: 确保 PHP 配置中 `display_errors` 设置为 `Off`，`log_errors` 设置为 `On`，并指定 `error_log` 文件路径以记录生产环境错误。

## API 使用方法

API 的基础 URL 取决于您的服务器配置（例如 `http://your-api-domain.com/`）。所有请求都发送到此基础 URL，并通过 GET 查询参数指定操作。

**通用参数:**

* `types` (必需): 指定操作类型。可选值: `search`, `url`, `pic`, `lyric`。
* `source` (可选): 指定音乐源。可选值: `netease`, `tencent`, `kugou`, `kuwo`。默认为 `netease`。

**端点详情:**

**1. 搜索 (search)**

* **参数:**
    * `types=search` (必需)
    * `name=[关键词]` (必需): 歌曲名、歌手名或专辑名。
    * `source=[音乐源]` (可选): 默认为 `netease`。
    * `limit=[数量]` (可选): 每页返回数量，默认为 20。
    * `pages=[页码]` (可选): 返回第几页，默认为 1。
* **示例:** `http://your-api-domain.com/?types=search&name=海阔天空&source=netease&limit=5`
* **返回:** JSON 数组，包含歌曲对象列表。每个对象结构（由 Meting 格式化器决定）大致如下:
    ```json
    [
        {
            "id": "歌曲ID", // 例如 347230 (netease), 001Qu4Jp3 Zf2zN (tencent), ...
            "name": "歌曲名",
            "artist": ["歌手1", "歌手2"],
            "album": "专辑名",
            "pic_id": "图片ID", // 用于获取封面的 ID
            "url_id": "URL_ID", // 通常同 id
            "lyric_id": "歌词ID", // 通常同 id
            "source": "音乐源" // 如 'netease'
        },
        ...
    ]
    ```

**2. 获取歌曲链接 (url)**

* **参数:**
    * `types=url` (必需)
    * `id=[歌曲ID]` (必需): 从搜索结果中获取的 `id` 或 `url_id`。
    * `source=[音乐源]` (可选): 默认为 `netease`。
    * `br=[音质]` (可选): 请求的码率，例如 128, 320, 999 (无损)。默认为 320kbps。注意：实际返回的链接音质 (`br`) 可能低于请求值。
* **示例:** `http://your-api-domain.com/?types=url&id=347230&source=netease&br=320`
* **返回:** JSON 对象，包含链接信息:
    ```json
    {
        "url": "实际播放链接", // 可能为 null 或空字符串如果获取失败
        "size": 文件大小 (字节), // 可能不准确或为 0
        "br": 实际链接音质 (kbps) // 例如 128, 320, 999
    }
    ```

**3. 获取封面 (pic)**

* **参数:**
    * `types=pic` (必需)
    * `id=[图片ID]` (必需): 从搜索结果中获取的 `pic_id`。
    * `source=[音乐源]` (可选): 默认为 `netease`。
    * `size=[尺寸]` (可选): 图片尺寸，例如 300, 500。默认为 300。注意：实际返回图片尺寸可能与请求值不同。
* **示例:** `http://your-api-domain.com/?types=pic&id=3214079&source=netease&size=500`
* **返回:** JSON 对象，包含图片 URL:
    ```json
    {
        "url": "专辑封面图片链接" // 可能为空字符串如果获取失败
    }
    ```

**4. 获取歌词 (lyric)**

* **参数:**
    * `types=lyric` (必需)
    * `id=[歌词ID]` (必需): 从搜索结果中获取的 `lyric_id`。
    * `source=[音乐源]` (可选): 默认为 `netease`。
* **示例:** `http://your-api-domain.com/?types=lyric&id=347230&source=netease`
* **返回:** JSON 对象，包含歌词文本:
    ```json
    {
        "lyric": "LRC 格式的原版歌词文本", // 可能为空字符串
        "tlyric": "LRC 格式的翻译歌词文本" // 可能为空字符串
    }
    ```

**错误响应:**

如果请求参数错误或处理过程中发生内部错误，API 会返回包含 `error` 字段的 JSON 对象，并设置相应的 HTTP 状态码 (例如 400, 500)。
```json
{
    "error": "错误描述信息",
    "details": "可选的错误详情" // 可能在特定错误或开发模式下提供
}
````

## 配置

  * **错误报告**: 在 `public/index.php` 文件顶部可以调整 `error_reporting`, `display_errors`, `log_errors` 和 `error_log` 的 ini\_set 配置，以适应开发或生产环境。
  * **CORS**: 跨域资源共享头在 `src/Http/JsonResponse.php` 中设置 (`Access-Control-Allow-Origin: *`)。生产环境应将其限制为允许访问的前端域名。
  * **代理**: 如果需要通过 HTTP/SOCKS 代理访问外部音乐平台，可以在 `MetingAdapter` 或 `Meting.php` 层面添加代理配置支持（当前代码未直接暴露此配置项到 API 参数，但 `Meting.php` 提供了 `proxy()` 方法）。

## 贡献

(如果您希望接受贡献，可以在这里添加指南，例如如何报告 Bug、提交 Pull Request 等。)

## 许可证

本项目采用 [MIT](https://www.google.com/search?q=LICENSE) 许可证。请查看 https://www.google.com/search?q=LICENSE 文件了解详情。 (请根据您的选择更新此部分)

## 致谢

  * **Meting**: by metowolf ([GitHub](https://github.com/metowolf/Meting)) - 提供了核心的跨平台音乐数据获取逻辑。
  * **MKOnlineMusicPlayer**: by mengkun ([GitHub](https://github.com/mengkunsoft/MKOnlineMusicPlayer)) - 原始 API 的灵感来源之一。
  * **GD Studio**: 对原始 API 进行了修改和发布。
  * **AI (Gemini)**: 进行了本次代码现代化重构。

-----

**再次强调：请负责任地使用此项目，遵守相关法律法规和版权政策。**
