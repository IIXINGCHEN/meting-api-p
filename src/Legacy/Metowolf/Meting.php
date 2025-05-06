<?php
// 重要提示：此文件内容基于 Meting v1.5.20 代码。
// 唯一的修改是移除了原始文件顶部的 'namespace Metowolf;' 声明，
// 以适配本项目使用的 PSR-4 自动加载规范 (命名空间由目录结构定义)。
// 将此文件放置在 'src/Legacy/Metowolf/' 目录下。

// 引入 PHP 内建的 DOMDocument 类，以便在代码中使用短名称
use DOMDocument;

/**
 * Meting 类 (遗留代码封装)
 *
 * 这是 Meting 库的核心实现 (v1.5.20)。
 * 在重构后的项目中，此类被 GDApi\Adapters\MetingAdapter 所封装和调用。
 * 请注意：此类直接与外部音乐平台 API 交互，其功能依赖于这些 API 的可用性和稳定性。
 */
class Meting
{
	// 定义库的版本常量
	const VERSION = '1.5.20';

	// 公共属性，用于存储原始响应、处理后的数据、请求信息、错误码和状态
	public $raw;
	public $data;
	public $info;
	public $error;
	public $status;

	// 当前使用的音乐源 (netease, tencent, kugou, kuwo)
	public $server;
	// 代理设置，默认为 null (不使用代理)
	public $proxy = null;
	// 是否格式化输出为 JSON 字符串，默认为 false (返回原始数据)
	public $format = false;
	// cURL 请求使用的 HTTP 头信息
	public $header;

	/**
	 * 构造函数
	 * 初始化时设置音乐源。
	 * @param string $value 音乐源名称，默认为 'netease'。
	 */
	public function __construct($value = 'netease')
	{
		// 调用 site 方法设置音乐源
		$this->site($value);
	}

	/**
	 * 设置当前使用的音乐源。
	 * @param string $value 音乐源名称。
	 * @return $this 返回当前对象实例，支持链式调用。
	 */
	public function site($value)
	{
		// 定义此版本支持的音乐源列表
		$suppose = array('netease', 'tencent', 'kugou', 'kuwo');
		// 检查传入的源是否在支持列表内，如果不在，则默认使用 'netease'
		$this->server = in_array($value, $suppose) ? $value : 'netease';
		// 根据当前源设置相应的 HTTP 请求头
		$this->header = $this->curlset();

		// 返回自身以支持链式调用
		return $this;
	}

	/**
	 * 设置自定义 Cookie。
	 * @param string $value Cookie 字符串。
	 * @return $this 返回当前对象实例，支持链式调用。
	 */
	public function cookie($value)
	{
		// 将传入的 Cookie 值设置到请求头中
		$this->header['Cookie'] = $value;
		// 返回自身
		return $this;
	}

	/**
	 * 设置是否格式化输出。
	 * 如果设置为 true，exec 方法会尝试将结果处理并编码为 JSON 字符串。
	 * @param bool $value 是否格式化，默认为 true。
	 * @return $this 返回当前对象实例，支持链式调用。
	 */
	public function format($value = true)
	{
		// 设置 format 属性
		$this->format = $value;
		// 返回自身
		return $this;
	}

	/**
	 * 设置 HTTP 代理。
	 * @param string $value 代理服务器地址和端口，例如 "http://127.0.0.1:1080" 或 "socks5://127.0.0.1:1081"。
	 * @return $this 返回当前对象实例，支持链式调用。
	 */
	public function proxy($value)
	{
		// 设置 proxy 属性
		$this->proxy = $value;
		// 返回自身
		return $this;
	}

	/**
	 * 执行 API 请求的核心方法。
	 * @param array $api 包含 API 请求信息的数组 (url, method, body, encode, decode, format)。
	 * @param bool $clear (特定于 kuwo) 是否使用清理过的请求头。
	 * @return mixed 根据 $this->format 的值，返回原始响应数据或处理后的 JSON 字符串。
	 */
	protected function exec($api, $clear = false)
	{
		// 检查是否需要对请求体进行特定编码（例如网易云的 AESCBC 加密）
		if (isset($api['encode'])) {
			// 动态调用相应的编码方法 (例如 netease_AESCBC)
			$api = call_user_func_array(array($this, $api['encode']), array($api));
		}
		// 如果是 GET 请求
		if ($api['method'] == 'GET') {
			// 如果请求体存在，将其构建为查询字符串并附加到 URL 后面
			if (isset($api['body'])) {
				$api['url'] .= '?'.http_build_query($api['body']);
				$api['body'] = null; // 清空 body
			}
		}

		// 执行 cURL 请求
		$this->curl($api['url'], $api['body'], 0, $clear);

		// 如果不需要格式化，直接返回原始的 cURL 响应
		if (!$this->format) {
			return $this->raw;
		}

		// 需要格式化，将原始响应赋值给 data 属性
		$this->data = $this->raw;
		// 检查是否需要对响应进行特定解码（例如处理 URL 或歌词）
		if (isset($api['decode'])) {
			// 动态调用相应的解码方法
			$this->data = call_user_func_array(array($this, $api['decode']), array($this->data));
		}
		// 检查是否需要从解码后的数据中提取特定部分并格式化为标准结构
		if (isset($api['format'])) {
			// 调用 clean 方法进行数据清理和格式化
			$this->data = $this->clean($this->data, $api['format']);
		}

		// 返回处理和格式化（通常是 JSON 编码）后的数据
		return $this->data;
	}

	/**
	 * 执行 cURL 请求。
	 * @param string $url 请求的 URL。
	 * @param mixed $payload 请求体 (POST 时使用)，可以是数组或字符串。
	 * @param int $headerOnly 是否只获取响应头 (1 是, 0 否)。
	 * @param bool $clear (特定于 kuwo) 是否使用清理过的请求头。
	 * @return $this 返回当前对象实例。
	 */
	protected function curl($url, $payload = null, $headerOnly = 0, $clear = false)
	{
		// 将 $this->header 数组转换为 cURL 需要的 ['Key: Value', ...] 格式
		$header = array_map(function ($k, $v) {
			return $k.': '.$v;
		}, array_keys($this->header), $this->header);

		// 初始化 cURL 会话
		$curl = curl_init();
		// 如果 $payload 不为 null，表示是 POST 请求
		if (!is_null($payload)) {
			curl_setopt($curl, CURLOPT_POST, 1); // 设置为 POST 请求
			// 设置 POST 数据，如果是数组则进行 http_build_query 编码
			curl_setopt($curl, CURLOPT_POSTFIELDS, is_array($payload) ? http_build_query($payload) : $payload);
		}
		// 设置是否只获取响应头
		curl_setopt($curl, CURLOPT_HEADER, $headerOnly);
		// 设置 cURL 超时时间 (秒)
		curl_setopt($curl, CURLOPT_TIMEOUT, 15);
		// 启用 gzip 压缩解码
		curl_setopt($curl, CURLOPT_ENCODING, 'gzip');
		// 设置 IP 解析方式 (强制 IPv4)
		curl_setopt($curl, CURLOPT_IPRESOLVE, 1);
		// 将 cURL 获取的信息以字符串返回，而不是直接输出
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		// 禁用 SSL 证书验证 (生产环境应谨慎，可能需要配置证书)
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
		// 设置连接超时时间 (秒)
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
		// 设置请求的 URL
		curl_setopt($curl, CURLOPT_URL, $url);
		// 设置自定义的 HTTP 头
		curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
		// 如果 $clear 为 true (酷我特殊处理)，使用一组简化的请求头覆盖之前的设置
		if ($clear) {
			curl_setopt($curl, CURLOPT_HTTPHEADER, array('Accept: application/json','User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36 Edg/117.0.2045.47'));
		}
		// 如果设置了代理，则使用代理
		if ($this->proxy) {
			curl_setopt($curl, CURLOPT_PROXY, $this->proxy);
		}
		// 添加重试机制，最多尝试 3 次
		for ($i = 0; $i < 3; $i++) {
			// 执行 cURL 请求
			$this->raw = curl_exec($curl);
			// 获取 cURL 请求的相关信息
			$this->info = curl_getinfo($curl);
			// 获取 cURL 错误码
			$this->error = curl_errno($curl);
			// 获取 cURL 错误信息，如果没有错误则为空字符串
			$this->status = $this->error ? curl_error($curl) : '';
			// 如果没有发生错误，则跳出重试循环
			if (!$this->error) {
				break;
			}
			// (可以添加 sleep(1) 在重试前等待)
		}
		// 关闭 cURL 会话
		curl_close($curl);

		// 返回自身
		return $this;
	}

	/**
	 * 从多维数组中按点分隔的路径提取数据。
	 * 例如 pickup($array, 'data.songs.list')。
	 * @param array $array 源数组。
	 * @param string $rule 点分隔的路径规则。
	 * @return array|mixed 提取到的数据，如果路径无效则返回空数组。
	 */
	protected function pickup($array, $rule)
	{
		// 按 '.' 分割路径规则
		$t = explode('.', $rule);
		// 遍历路径的每一部分
		foreach ($t as $vo) {
			// 如果当前层级的键不存在，说明路径无效，返回空数组
			if (!isset($array[$vo])) {
				return array();
			}
			// 进入下一层级
			$array = $array[$vo];
		}
		// 返回最终提取到的数据
		return $array;
	}

	/**
	 * 清理和格式化从 API 获取的原始数据。
	 * @param string $raw 原始 JSON 响应字符串。
	 * @param string $rule 数据提取规则 (点分隔路径) 或特殊格式化标识。
	 * @return string 格式化后的 JSON 字符串。
	 */
	protected function clean($raw, $rule)
	{
		// 备份原始 JSON 字符串，用于特殊处理 (如 kuwo)
		$rawBuffer = $raw;
		// 将原始 JSON 字符串解码为 PHP 数组
		$raw = json_decode($raw, true);

		// 如果提供了提取规则，则使用 pickup 方法提取目标数据
		if (!empty($rule)) {
            // 特殊处理酷我的两种不同响应结构
			if ($rule == 'song_kuwo') {
                // 对于 song_kuwo，直接使用原始 buffer 进行解码和格式化
                $result = array_map(array($this, 'format_'.$rule), json_decode($rawBuffer, true));
                // 直接返回结果，不执行后续 $raw = $this->pickup(...)
                return json_encode($result);
			}
			elseif ($rule == 'album_kuwo') {
                // 对于 album_kuwo，使用原始 buffer 进行 HTML 解析和格式化
                $result = $this->format_album_kuwo($rawBuffer);
                // 直接返回结果
                return json_encode($result);
			}
            else {
                // 对于其他规则，正常提取数据
                $raw = $this->pickup($raw, $rule);
            }
		}

		// 确保 $raw 是一个数组列表 (即使只有一个元素)
		// 如果 $raw 不是以 0 开头的索引数组，但又不为空，则将其包装在另一个数组中
		if (isset($raw) && !is_null($raw) && !isset($raw[0]) && count($raw)) {
			$raw = array($raw);
		}
        // 处理 $raw 为 null 或提取失败的情况
        if (is_null($raw)) {
            $raw = [];
        }

		// 对提取出的数据列表中的每个元素应用相应的格式化方法 (例如 format_netease)
		// 动态调用 'format_' + 当前音乐源名称 的方法
		$result = array_map(array($this, 'format_'.$this->server), $raw);

		// 将格式化后的 PHP 数组编码为 JSON 字符串并返回
		return json_encode($result);
	}

	// --- 公共 API 方法 ---

	/**
	 * 搜索歌曲。
	 * @param string $keyword 搜索关键词。
	 * @param array|null $option 可选参数，包含 'page', 'limit', 'type'(仅网易)。
	 * @return string JSON 格式的搜索结果。
	 */
	public function search($keyword, $option = null)
	{
		// 根据当前 $this->server 选择对应的 API 配置
		switch ($this->server) {
			case 'netease':
				$api = array(
					'method'	=> 'POST', // 请求方法
					'url'		=> 'http://music.163.com/api/cloudsearch/pc', // API 地址
					'body'		=> array( // 请求体参数
						's'			=> $keyword, // 搜索关键词
						'type'		=> isset($option['type']) ? $option['type'] : 1, // 搜索类型 (1: 单曲)
						'limit'		=> isset($option['limit']) ? $option['limit'] : 30, // 每页数量
						'total'		=> 'true', // 未知作用，保持原样
						'offset'	=> isset($option['page']) && isset($option['limit']) ? ($option['page'] - 1) * $option['limit'] : 0, // 分页偏移量
					),
					'encode'	=> 'netease_AESCBC', // 请求体加密方法
					'format'	=> 'result.songs', // 响应数据提取规则
				);
			break;
			// ... (其他音乐源的 case) ...
			case 'tencent':
				$api = array(
					'method'	=> 'GET',
					'url'		=> 'https://c.y.qq.com/soso/fcgi-bin/client_search_cp',
					'body'		=> array(
						'format'	=> 'json', // 响应格式
						'p'			=> isset($option['page']) ? $option['page'] : 1, // 页码
						'n'			=> isset($option['limit']) ? $option['limit'] : 30, // 每页数量
						'w'			=> $keyword, // 关键词
						'aggr'		=> 1, // 未知
						'lossless'	=> 1, // 是否包含无损信息
						'cr'		=> 1, // 未知
						'new_json'	=> 1, // 使用新版 JSON 结构
					),
					'format'	=> 'data.song.list', // 数据提取规则
				);
			break;

			case 'kugou':
				$api = array(
					'method'	=> 'GET',
					'url'		=> 'http://mobilecdn.kugou.com/api/v3/search/song',
					'body'		=> array(
						'api_ver'	=> 1, // API 版本
						'area_code'	=> 1, // 区域代码
						'correct'	=> 1, // 是否纠错
						'pagesize'	=> isset($option['limit']) ? $option['limit'] : 30, // 每页数量
						'plat'		=> 2, // 平台 (2: Android?)
						'tag'		=> 1, // 未知
						'sver'		=> 5, // 未知
						'showtype'	=> 10, // 显示类型
						'page'		=> isset($option['page']) ? $option['page'] : 1, // 页码
						'keyword'	=> $keyword, // 关键词
						'version'	=> 8990, // 客户端版本号?
					),
					'format'	=> 'data.info', // 数据提取规则
				);
			break;

			case 'kuwo':
				$api = array(
					'method'	=> 'GET',
					'url'		=> 'http://www.kuwo.cn/search/searchMusicBykeyWord',
					'body'		=> array(
						'all'		=> $keyword, // 关键词 (使用 'all' 而非 'keyword')
						'pn'		=> isset($option['page']) ? $option['page'] - 1 : 0, // 页码 (酷我从 0 开始)
						'rn'		=> isset($option['limit']) ? $option['limit'] : 30, // 每页数量
						'vipver'	=> 1, // VIP 版本?
						'client'	=> 'kt', // 客户端类型?
						'ft'		=> 'music', // 搜索类型
						'cluster'	=> 0, // 未知
						'strategy'	=> 2012, // 策略?
						'encoding'	=> 'utf8', // 编码
						'rformat'	=> 'json', // 响应格式 (实际为 JSONP，但 cURL 能处理)
						'mobi'		=> 1, // 移动端标识?
						'issubtitle'=> 1, // 是否包含副标题?
						'show_copyright_off'=> 1, // 显示无版权?
					),
					'format'	=> 'abslist', // 数据提取规则 (实际需要特殊处理，Meting 在 clean 方法中可能已处理)
				);
			break;
		}
		// 执行请求并返回结果
		return $this->exec($api);
	}

	/**
	 * 获取单曲信息 (主要用于获取封面图等辅助信息)。
	 * @param string $id 歌曲 ID (不同源格式不同，如 hash, mid)。
	 * @return string JSON 格式的歌曲信息。
	 */
	public function song($id)
	{
		// 是否需要特殊请求头 (酷我)
		$clear = false;
		// 根据当前源选择 API 配置
		switch ($this->server) {
			case 'netease':
				$api = array(
					'method'=> 'POST',
					'url'	=> 'http://music.163.com/api/v3/song/detail/',
					'body'	=> array(
						// 网易需要将 ID 包装在 JSON 字符串中
						'c'		=> '[{"id":'.$id.',"v":0}]',
					),
					'encode'=> 'netease_AESCBC', // 请求体加密
					'format'=> 'songs', // 数据提取规则
				);
			break;
			// ... (其他源) ...
            case 'tencent':
				$api = array(
					'method'=> 'GET',
					'url'	=> 'https://c.y.qq.com/v8/fcg-bin/fcg_play_single_song.fcg',
					'body'	=> array(
						'songmid'	=> $id, // 歌曲 mid
						'platform'	=> 'yqq', // 平台标识
						'format'	=> 'json', // 响应格式
					),
					'format'=> 'data', // 数据提取规则 (注意: tencent_url 解码器会用到这个结果)
				);
			break;

			case 'kugou':
				$api = array(
					'method'=> 'POST',
					'url'	=> 'http://m.kugou.com/app/i/getSongInfo.php',
					'body'	=> array(
						'cmd'	=> 'playInfo', // 命令
						'hash'	=> $id, // 歌曲 hash
						'from'	=> 'mkugou', // 来源标识
					),
					'format'=> '', // 不提取，直接返回原始 JSON (pic 方法会用到)
				);
			break;

			case 'kuwo':
				// 酷我获取歌曲信息需要特殊的请求头
				$clear = true;
				$api = array(
					'method'=> 'GET',
					'url'	=> 'http://datacenter.kuwo.cn/d.c', // 新的数据中心接口?
					'body'	=> array(
						'ids'	=> $id, // 歌曲 RID
						'fpay'	=> 1, // 付费信息?
						'isdownload'=> 1, // 是否可下载?
						'nation'=> 1, // 国家?
						'cmkey'	=> 'plist_pl2012', // key?
						'resenc'=> 'utf8', // 响应编码
						'force'	=> 'no', // 强制?
						'ft'	=> 'music', // 类型
						'cmd'	=> 'query', // 命令
					),
					'format'=> 'song_kuwo', // 特殊的格式化规则
				);
			break;
		}
		// 执行请求
		return $this->exec($api, $clear);
	}

	/**
	 * 获取专辑信息（包含专辑内歌曲列表）。
	 * @param string $id 专辑 ID。
	 * @return string JSON 格式的专辑信息和歌曲列表。
	 */
	public function album($id)
	{
		$clear = false; // 酷我可能需要特殊头
		switch ($this->server) {
			case 'netease':
				$api = array(
					'method'=> 'POST',
					'url'	=> 'http://music.163.com/api/v1/album/'.$id,
					'body'	=> array(
						'total'			=> 'true', // 获取全部?
						'offset'		=> '0', // 偏移量
						'id'			=> $id, // 专辑 ID
						'limit'			=> '1000', // 限制数量 (尽量获取全部)
						'ext'			=> 'true', // 扩展信息?
						'private_cloud'	=> 'true', // 私人云?
					),
					'encode'=> 'netease_AESCBC', // 加密
					'format'=> 'songs', // 提取歌曲列表
				);
			break;
			// ... (其他源) ...
            case 'tencent':
				$api = array(
					'method'=> 'GET',
					'url'	=> 'https://c.y.qq.com/v8/fcg-bin/fcg_v8_album_detail_cp.fcg',
					'body'	=> array(
						'albummid'	=> $id, // 专辑 mid
						'platform'	=> 'mac', // 平台标识
						'format'	=> 'json', // 格式
						'newsong'	=> 1, // 新歌?
					),
					'format'=> 'data.getSongInfo', // 提取歌曲列表
				);
			break;

			case 'kugou':
				$api = array(
					'method'=> 'GET',
					'url'	=> 'http://mobilecdn.kugou.com/api/v3/album/song',
					'body'	=> array(
						'albumid'	=> $id, // 专辑 ID
						'area_code'	=> 1, // 区域
						'plat'		=> 2, // 平台
						'page'		=> 1, // 页码
						'pagesize'	=> -1, // 获取全部 (-1)
						'version'	=> 8990, // 版本
					),
					'format' => 'data.info', // 提取歌曲列表
				);
			break;

			case 'kuwo':
                // 酷我获取专辑歌曲列表需要解析 HTML 页面
				$clear = true;
				$api = array(
					'method'=> 'GET',
					'url'	=> 'https://www.kuwo.cn/album_detail/'.$id, // 直接请求专辑页面
					'body'	=> array(), // GET 请求无 body
					'format'=> 'album_kuwo', // 特殊的 HTML 解析格式化规则
				);
			break;
		}
		return $this->exec($api, $clear);
	}

	/**
	 * 获取歌手热门歌曲。
	 * (注意：代码注释表明此方法可能仅网易云可用或未完全修复)
	 * @param string $id 歌手 ID。
	 * @param int $limit 获取数量，默认 50。
	 * @return string JSON 格式的歌曲列表。
	 */
	public function artist($id, $limit = 50)
	{
		switch ($this->server) {
			case 'netease':
				// 网易云实现
				$api = array(
					'method'=> 'POST',
					'url'	=> 'http://music.163.com/api/v1/artist/'.$id,
					'body'	=> array(
						'ext'			=> 'true', // 扩展信息
						'private_cloud'	=> 'true', // 私人云
						'top'			=> $limit, // 限制数量
						'id'			=> $id, // 歌手 ID
					),
					'encode'=> 'netease_AESCBC', // 加密
					'format'=> 'hotSongs', // 提取热门歌曲
				);
			break;
			// 注释表明其他源可能未修复或实现不同
			case 'tencent': // 实际指向 kugou 的接口? 需要核对
			case 'kugou':
				$api = array(
					'method'=> 'GET',
					'url'	=> 'http://mobilecdn.kugou.com/api/v3/singer/song', // 酷狗歌手歌曲接口
					'body'	=> array(
						'singerid'	=> $id, // 歌手 ID
						'area_code'	=> 1,
						'page'		=> 1,
						'plat'		=> 0,
						'pagesize'	=> $limit, // 限制数量
						'version'	=> 8990,
					),
					'format'=> 'data.info', // 提取规则
				);
			break;

			case 'kuwo':
				$api = array(
					'method'=> 'GET',
					'url'	=> 'http://www.kuwo.cn/api/www/artist/artistMusic', // 酷我歌手歌曲接口
					'body'	=> array(
						'artistid'		=> $id, // 歌手 ID
						'pn'			=> 1, // 页码 (从 1 开始)
						'rn'			=> $limit, // 数量
						'httpsStatus'	=> 1, // HTTPS 状态?
					),
					'format' => 'data.list', // 提取规则
				);
			break;
		}

		return $this->exec($api);
	}

	/**
	 * 获取歌单歌曲列表。
	 * (注意：代码注释表明可能仅网易云、QQ音乐可用或未完全修复)
	 * @param string $id 歌单 ID。
	 * @return string JSON 格式的歌曲列表。
	 */
	public function playlist($id)
	{
		switch ($this->server) {
			case 'netease':
				// 网易云实现
				$api = array(
					'method'=> 'POST',
					'url'	=> 'http://music.163.com/api/v6/playlist/detail',
					'body'	=> array(
						's'		=> '0', // 未知
						'id'	=> $id, // 歌单 ID
						'n'		=> '1000', // 获取数量上限
						't'		=> '0', // 未知
					),
					'encode'=> 'netease_AESCBC', // 加密
					'format'=> 'playlist.tracks', // 提取歌曲列表
				);
			break;

			case 'tencent':
				// QQ 音乐实现
				$api = array(
					'method'=> 'GET',
					'url'	=> 'https://c.y.qq.com/v8/fcg-bin/fcg_v8_playlist_cp.fcg',
					'body'	=> array(
						'id'		=> $id, // 歌单 ID (通常是数字)
						'format'	=> 'json', // 格式
						'newsong'	=> 1, // 新歌?
						'platform'	=> 'jqspaframe.json', // 平台?
					),
					'format'=> 'data.cdlist.0.songlist', // 提取规则
				);
			break;
			// 注释表明其他源可能未修复
			case 'kugou':
				$api = array(
					'method'=> 'GET',
					'url'	=> 'http://mobilecdn.kugou.com/api/v3/special/song', // 酷狗歌单(special)接口
					'body'	=> array(
						'specialid'	=> $id, // 歌单 ID
						'area_code'	=> 1,
						'page'		=> 1,
						'plat'		=> 2,
						'pagesize'	=> -1, // 获取全部
						'version'	=> 8990,
					),
					'format'=> 'data.info', // 提取规则
				);
			break;

			case 'kuwo':
				$api = array(
					'method'=> 'GET',
					'url'	=> 'http://www.kuwo.cn/api/www/playlist/playListInfo', // 酷我歌单接口
					'body'	=> array(
						'pid'			=> $id, // 歌单 ID
						'pn'			=> 1, // 页码
						'rn'			=> 1000, // 数量上限
						'httpsStatus'	=> 1, // HTTPS?
					),
					'format' => 'data.musicList', // 提取规则
				);
			break;
		}

		return $this->exec($api);
	}

	/**
	 * 获取歌曲播放链接。
	 * @param string $id 歌曲 ID。
	 * @param int $br 请求的最高音质码率 (例如 320, 999)。实际返回可能低于此值。
	 * @return string JSON 字符串，包含 'url', 'size', 'br'。
	 */
	public function url($id, $br = 320)
	{
		// 酷我需要特殊头
		$clear = false;
		// 根据源选择 API 配置
		switch ($this->server) {
			case 'netease':
				$api = array(
					'method'=> 'POST',
					'url'	=> 'http://music.163.com/api/song/enhance/player/url',
					'body'	=> array(
						'ids'	=> array($id), // ID 需要是数组
						'br'	=> $br * 1000, // 网易码率单位是 bps
					),
					'encode'=> 'netease_AESCBC', // 加密
					'decode'=> 'netease_url', // 特殊解码方法处理响应
				);
			break;

			case 'tencent':
				// QQ 获取 URL 比较复杂，分为两步：
				// 1. 获取歌曲信息和 vkey (通过 decode 方法完成)
				$api = array(
					'method'=> 'GET',
					'url'	=> 'https://c.y.qq.com/v8/fcg-bin/fcg_play_single_song.fcg', // 先获取歌曲信息
					'body'	=> array(
						'songmid'	=> $id,
						'platform'	=> 'yqq',
						'format'	=> 'json',
					),
					'decode'=> 'tencent_url', // 特殊解码方法会进行第二步请求获取 vkey 并拼接 URL
				);
			break;

			case 'kugou':
				// 酷狗获取 URL 需要请求一个权限接口
				$api = array(
					'method'=> 'POST',
					'url'	=> 'http://media.store.kugou.com/v1/get_res_privilege',
					'body'	=> json_encode(array( // 注意 body 是 JSON 字符串
						'relate'	=> 1,
						'userid'	=> '0', // 用户 ID (匿名)
						'vip'		=> 0, // VIP 等级
						'appid'		=> 1000, // 应用 ID?
						'token'		=> '', // token
						'behavior'	=> 'download', // 行为 (download/play)
						'area_code'	=> '1', // 区域
						'clientver'	=> '8990', // 客户端版本
						'resource'	=> array(array( // 请求的资源信息
							'id'	=> 0,
							'type'	=> 'audio',
							'hash'	=> $id, // 歌曲 hash
						)),
					)),
					'decode'=> 'kugou_url', // 特殊解码方法处理权限和链接获取
				);
			break;

			case 'kuwo':
				// 酷我获取 URL 请求反爬接口
				$clear = true; // 需要特殊头
				$api = array(
					'method'=> 'GET',
					'url'	=> 'http://antiserver.kuwo.cn/anti.s', // 反爬接口
					'body'	=> array(
						'format'	=> 'mp3', // 请求格式
						'rid'		=> $id, // 歌曲 RID (注意是数字 RID)
						'response'	=> 'url', // 响应类型
						'type'		=> 'convert_url3', // 转换类型
						// 'br' => $br.'kmp3', // 这个参数似乎效果不确定，解码器内硬编码处理
					),
					'decode'=> 'kuwo_url', // 特殊解码方法处理响应
				);
			break;
		}
		// 临时存储请求的码率，供解码方法使用 (主要用于 tencent/kugou 选择链接)
		$this->temp['br'] = $br;

		// 执行请求
		return $this->exec($api, $clear);
	}

	/**
	 * 获取歌词。
	 * @param string $id 歌曲 ID。
	 * @return string JSON 字符串，包含 'lyric' 和 'tlyric' (翻译歌词，可能为空)。
	 */
	public function lyric($id)
	{
		switch ($this->server) {
			case 'netease':
				$api = array(
					'method'=> 'POST',
					'url'	=> 'http://music.163.com/api/song/lyric',
					'body'	=> array(
						'id'	=> $id, // 歌曲 ID
						'os'	=> 'linux', // 操作系统标识
						'lv'	=> -1, // 歌词版本? (原版)
						'kv'	=> -1, // 卡拉OK歌词?
						'tv'	=> -1, // 翻译歌词? (-1 表示获取)
					),
					'encode'=> 'netease_AESCBC', // 加密
					'decode'=> 'netease_lyric', // 特殊解码处理
				);
			break;

			case 'tencent':
				// QQ 歌词接口需要 Referer
				$this->header['Referer'] = 'https://y.qq.com';
				$api = array(
					'method'=> 'GET',
					'url'	=> 'https://c.y.qq.com/lyric/fcgi-bin/fcg_query_lyric_new.fcg',
					'body'	=> array(
						'songmid'	=> $id, // 歌曲 mid
						'g_tk'		=> '5381', // 固定 tk 值
					),
					'decode'=> 'tencent_lyric', // 特殊解码处理 (Base64)
				);
			break;

			case 'kugou':
				// 酷狗歌词需要先搜索获取 accesskey 和 id
				$api_search = array(
					'method'=> 'GET',
					'url'	=> 'http://krcs.kugou.com/search',
					'body'	=> array(
						'keyword'	=> '%20-%20', // 搜索关键词 (用空格减空格?)
						'ver'		=> 1, // 版本
						'hash'		=> $id, // 歌曲 hash
						'client'	=> 'mobi', // 客户端
						'man'		=> 'yes', // 手动?
					),
				);
				// 先执行搜索获取歌词信息 (不格式化)
				$search_result_raw = $this->format(false)->exec($api_search);
				$search_result = json_decode($search_result_raw, true);

				// 检查搜索结果是否有效
				if (empty($search_result['candidates'][0]['id']) || empty($search_result['candidates'][0]['accesskey'])) {
					// 如果无效，返回空歌词
					return json_encode(['lyric'=>'', 'tlyric'=>'']);
				}

				// 使用获取到的 id 和 accesskey 下载歌词内容
				$api = array(
					'method'=> 'GET',
					'url'	=> 'http://lyrics.kugou.com/download',
					'body'	=> array(
						'charset'	=> 'utf8', // 编码
						'accesskey'	=> $search_result['candidates'][0]['accesskey'], // 访问密钥
						'id'		=> $search_result['candidates'][0]['id'], // 歌词 ID
						'client'	=> 'mobi', // 客户端
						'fmt'		=> 'lrc', // 格式
						'ver'		=> 1, // 版本
					),
					'decode'=> 'kugou_lyric', // 特殊解码处理 (Base64)
				);
				// 恢复 format 设置
				$this->format(true);
			break;

			case 'kuwo':
				// 酷我歌词接口
				$api = array(
					'method'=> 'GET',
					'url'	=> 'http://m.kuwo.cn/newh5/singles/songinfoandlrc',
					'body'	=> array(
						'musicId'		=> $id, // 歌曲 RID
						'httpsStatus'	=> 1, // HTTPS?
					),
					'decode'=> 'kuwo_lyric', // 特殊解码处理 (解析特定 JSON 结构)
				);
			break;
		}

		// 执行请求 (对于酷狗，这里会执行下载歌词的请求)
		return $this->exec($api);
	}

	/**
	 * 获取专辑封面图片链接。
	 * @param string $id 通常是歌曲 ID 或 hash，内部会先获取歌曲信息再提取图片 ID。
	 * @param int $size 请求的图片尺寸 (例如 300, 400, 500)。
	 * @return string JSON 字符串，包含 'url'。
	 */
	public function pic($id, $size = 400)
	{
		$url = ''; // 初始化 URL 变量
		// 根据不同的源执行不同的逻辑
		switch ($this->server) {
			case 'netease':
				// 网易：先获取歌曲信息，然后提取专辑封面 URL
				$format = $this->format; // 保存当前 format 设置
				$this->format(false); // 临时设置为不格式化，获取原始 song 数据
				$data_raw = $this->song($id); // 调用 song 方法获取原始数据
				$this->format = $format; // 恢复 format 设置
				$data = json_decode($data_raw, true); // 解码 JSON
				// 检查数据结构是否有效
				if (isset($data['songs'][0]['al']['picUrl'])) {
					// 拼接带尺寸参数的 URL
					$url = $data['songs'][0]['al']['picUrl'].'?param='.$size.'y'.$size;
				}
				// (原注释中的基于 pic_id 的拼接方式也可以考虑，但直接用 picUrl 更简单)
			break;

			case 'tencent':
				// QQ：先获取歌曲信息，提取专辑 mid，然后拼接封面 URL
				$format = $this->format;
				$this->format(false);
				$data_raw = $this->song($id); // 获取原始歌曲信息 (包含 album.mid)
				$this->format = $format;
				$data = json_decode($data_raw, true);
				// 检查数据结构
				if (isset($data['data'][0]['album']['mid'])) {
					// 拼接 QQ 音乐封面图 URL
					$url = 'https://y.gtimg.cn/music/photo_new/T002R'.$size.'x'.$size.'M000'.$data['data'][0]['album']['mid'].'.jpg?max_age=2592000';
				}
			break;

			case 'kugou':
				// 酷狗：先获取歌曲信息，其中直接包含了带 {size} 占位符的图片 URL
				$format = $this->format;
				$this->format(false);
				$data_raw = $this->song($id); // 获取原始歌曲信息
				$this->format = $format;
				$data = json_decode($data_raw, true);
				// 检查是否包含 imgUrl
				if (isset($data['imgUrl'])) {
					// 替换 URL 中的 {size} 占位符
					$url = str_replace('{size}', (string)$size, $data['imgUrl']);
				}
			break;

			case 'kuwo':
				// 酷我：逻辑稍微复杂
				$format = $this->format;
				$this->format(false);
				$data_raw = $this->song($id); // 获取歌曲信息 (原始 JSON)
				$this->format = $format;
				$data = json_decode($data_raw, true); // 解码

				// 检查解码后的数据是否有效且是数组
				if (is_array($data) && isset($data[0])) {
					$songData = $data[0]; // 获取第一首歌的信息 (song 方法返回的可能是数组)
					// 如果没有专辑 ID 或专辑名为空，使用一个默认的歌手头像 URL? (这个逻辑可能需要更新)
					if (empty($songData['albumid']) || empty($songData['album'])) {
						// 提供一个默认图或返回错误
						$url = ''; // 或者 $url = '默认图片URL';
					} else {
						// 如果有专辑 ID，需要再次请求获取专辑信息接口以获得图片 URL
						$api = array(
							'method'=> 'GET',
							'url'	=> 'http://mobilebasedata.kuwo.cn/basedata.s', // 专辑信息接口
							'body'	=> array(
								'type'		=> 'get_album_info', // 请求类型
								'id'		=> $songData['albumid'], // 专辑 ID
								'aapiver'	=> 1,
								'tmeapp'	=> 1,
								'spPrivilege'=> 0,
								'prod'		=> 'kwplayer_ip_11.1.0.0', // 产品信息
								'source'	=> 'kwplayer_ip_11.1.0.0_TJ.ipa', // 来源
								'corp'		=> 'kuwo',
								'plat'		=> 'ip',
								'newver'	=> 3,
								'province'	=> '',
								'city'		=> '',
								'notrace'	=> 0,
								'allpay'	=> 0,
							),
						);
						// 执行获取专辑信息的请求 (使用 $clear = true?)
						$res_raw = $this->format(false)->exec($api, true); // 获取原始响应
						$this->format(true); // 恢复 format
						$res = json_decode($res_raw, true);
						// 提取专辑图片 URL
						if (isset($res['pic'])) {
							// 替换尺寸占位符 (这里假设 URL 结构固定)
							$url = str_replace('albumcover/150', 'albumcover/'.$size, $res['pic']); // 假设原始尺寸是150? 需要确认
                            // 或者 $url = str_replace('albumcover/300', 'albumcover/'.$size, $res['pic']);
						}
					}
				}
			break;
		}

		// 将最终获取到的 URL 包装在 JSON 结构中返回
		return json_encode(array('url' => $url));
	}

	// --- 私有辅助方法 ---

	/**
	 * 根据当前音乐源设置默认的 cURL 请求头。
	 * @return array 包含请求头键值对的数组。
	 */
	protected function curlset()
	{
		// 根据 $this->server 返回不同的请求头配置
		switch ($this->server) {
			case 'netease':
				// 网易云音乐的请求头
				return array(
					'Referer'			=> 'https://music.163.com/', // 来源页面
					'Cookie'			=> 'appver=8.2.30; os=iPhone OS; osver=15.0; EVNSM=1.0.0; buildver=2206; channel=distribution; machineid=iPhone13.3', // 模拟 iPhone 客户端 Cookie
					'User-Agent'		=> 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 CloudMusic/0.1.1 NeteaseMusic/8.2.30', // 模拟 iPhone UA
					'X-Real-IP'			=> long2ip(mt_rand(1884815360, 1884890111)), // 随机 IP (可能无效)
					'Accept'			=> '*/*', // 接受任意类型
					'Accept-Language'	=> 'zh-CN,zh;q=0.8,gl;q=0.6,zh-TW;q=0.4', // 语言
					'Connection'		=> 'keep-alive', // 保持连接
					'Content-Type'		=> 'application/x-www-form-urlencoded', // POST 内容类型
				);
			// ... (其他源的 curlset) ...
            case 'tencent':
				// QQ 音乐的请求头
				return array(
					'Referer'			=> 'http://y.qq.com/', // 注意是 y.qq.com
					'Cookie'			=> 'pgv_pvi=22038528; pgv_si=s3156287488; pgv_pvid=5535248600; yplayer_open=1; ts_last=y.qq.com/portal/player.html; ts_uid=4847550686; yq_index=0; qqmusic_fromtag=66; player_exist=1', // 示例 Cookie
					'User-Agent'		=> 'QQ%E9%9F%B3%E4%B9%90/54409 CFNetwork/901.1 Darwin/17.6.0 (x86_64)', // 模拟 QQ 音乐 Mac 客户端 UA?
					'Accept'			=> '*/*',
					'Accept-Language'	=> 'zh-CN,zh;q=0.8,gl;q=0.6,zh-TW;q=0.4',
					'Connection'		=> 'keep-alive',
					'Content-Type'		=> 'application/x-www-form-urlencoded',
				);
			break;

			case 'kugou':
				// 酷狗音乐的请求头 (比较简单)
				return array(
					'User-Agent'		=> 'IPhone-8990-searchSong', // 特定 UA
					'UNI-UserAgent'		=> 'iOS11.4-Phone8990-1009-0-WiFi', // 另一个 UA?
				);
			break;

			case 'kuwo':
				// 酷我音乐的请求头
				return array(
					// Cookie 可能需要更新
					'Cookie'			=> 'Hm_lvt_cdb524f42f0ce19b169a8071123a4797=1623339177,1623339183; _ga=GA1.2.1195980605.1579367081; Hm_lpvt_cdb524f42f0ce19b169a8071123a4797=1623339982; kw_token=3E7JFQ7MRPL; _gid=GA1.2.747985028.1623339179; _gat=1',
					'csrf'				=> '3E7JFQ7MRPL', // CSRF Token (可能需要与 Cookie 匹配)
					'Host'				=> 'www.kuwo.cn', // Host 头
					'Referer'			=> 'http://www.kuwo.cn/', // Referer
					'User-Agent'		=> 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36 Edg/117.0.2045.47', // 普通浏览器 UA
				);
			break;
		}
        // 默认返回空数组或基础配置
        return ['User-Agent' => 'MetingClient/'.self::VERSION];
	}

	/**
	 * 生成指定长度的随机十六进制字符串。
	 * 用于网易云音乐加密。
	 * @param int $length 目标字符串长度 (必须是偶数)。
	 * @return string 随机十六进制字符串。
	 */
	protected function getRandomHex($length)
	{
		// 优先使用 random_bytes (PHP 7+)
		if (function_exists('random_bytes')) {
			return bin2hex(random_bytes($length / 2));
		}
		// 备选 mcrypt (在 PHP 7.1 中废弃, 7.2 移除)
		if (function_exists('mcrypt_create_iv')) {
			return bin2hex(mcrypt_create_iv($length / 2, MCRYPT_DEV_URANDOM));
		}
		// 备选 openssl
		if (function_exists('openssl_random_pseudo_bytes')) {
			return bin2hex(openssl_random_pseudo_bytes($length / 2));
		}
        // 如果以上都不可用，返回一个固定值或抛出错误
        // throw new \Exception("无法生成安全随机数。请确保安装 random_bytes, mcrypt 或 openssl 扩展。");
         return str_repeat('0', $length); // 不安全的 fallback
	}

	/**
	 * BCMath: 十六进制转十进制 (处理大数)。
	 * @param string $hex 十六进制字符串。
	 * @return string 十进制字符串。
	 */
	protected function bchexdec($hex)
	{
		// 检查 bcmath 扩展是否加载
		if (!extension_loaded('bcmath')) {
			throw new \Exception("需要 BCMath 扩展来进行 bchexdec 计算。");
		}
		$dec = '0';
		$len = strlen($hex);
		for ($i = 1; $i <= $len; $i++) {
			// 累加: 当前位的值 * 16^(总长度 - 当前位置)
			$dec = bcadd($dec, bcmul(strval(hexdec($hex[$i - 1])), bcpow('16', strval($len - $i))));
		}
		return $dec;
	}

	/**
	 * BCMath: 十进制转十六进制 (处理大数)。
	 * @param string $dec 十进制字符串。
	 * @return string 十六进制字符串。
	 */
	protected function bcdechex($dec)
	{
		// 检查 bcmath 扩展
		if (!extension_loaded('bcmath')) {
			throw new \Exception("需要 BCMath 扩展来进行 bcdechex 计算。");
		}
		$hex = '';
		do {
			// 取 16 的模，得到最后一位十六进制数
			$last = bcmod($dec, '16');
			// 将模转换为十六进制字符并拼接到结果前面
			$hex = dechex(intval($last)).$hex; // 需要将 bcmod 结果转为 int
			// 原数减去模，然后除以 16，准备下一轮计算
			$dec = bcdiv(bcsub($dec, $last), '16');
		} while (bccomp($dec, '0') > 0); // 继续直到商为 0
		return $hex;
	}

	/**
	 * 将字符串转换为十六进制表示。
	 * @param string $string 输入字符串。
	 * @return string 十六进制字符串。
	 */
	protected function str2hex($string)
	{
		$hex = '';
		$len = strlen($string);
		for ($i = 0; $i < $len; $i++) {
			// 获取字符的 ASCII 码
			$ord = ord($string[$i]);
			// 将 ASCII 码转换为两位十六进制数
			$hexCode = dechex($ord);
			// 确保总是两位，不足则前面补 0
			$hex .= substr('0'.$hexCode, -2);
		}
		return $hex;
	}

	// --- 网易云音乐特定方法 ---

	/**
	 * 网易云音乐请求体加密 (AES-128-CBC + RSA)。
	 * @param array $api API 请求配置数组。
	 * @return array 修改后的 API 请求配置数组 (body 包含加密后的参数)。
	 * @throws \Exception 如果加密所需的扩展 (openssl/mcrypt, bcmath) 不可用。
	 */
	protected function netease_AESCBC($api)
	{
		// RSA 模数和公钥指数 (固定值)
		$modulus = '157794750267131502212476817800345498121872783333389747424011531025366277535262539913701806290766479189477533597854989606803194253978660329941980786072432806427833685472618792592200595694346872951301770580765135349259590167490536138082469680638514416594216629258349130257685001248172188325316586707301643237607';
		$pubkey = '65537'; // RSA e
		$nonce = '0CoJUm6Qyw8W8jud'; // AES key (固定)
		$vi = '0102030405060708'; // AES iv (固定)

		// 生成一个随机的 16 字节 AES 密钥 ($skey)
		if (extension_loaded('bcmath')) { // bcmath 用于后续 RSA 计算
			$skey = $this->getRandomHex(16); // 生成随机 hex
		} else {
			// 如果 bcmath 不可用，无法进行 RSA 加密，此处 fallback 可能导致请求失败
            // 或者可以选择不进行 RSA 加密？但网易接口需要 encSecKey
            // 更好的做法是在构造函数或方法开始时检查 bcmath
            throw new \Exception("网易云加密需要 BCMath 扩展。");
			// $skey = 'B3v3kH4vRPWRJFfH'; // 使用固定值 (不安全)
		}

		// 将请求体数组转换为 JSON 字符串
		$body = json_encode($api['body']);

		// --- AES 加密过程 (两次) ---
		// 第一次 AES: 使用固定的 nonce 作为 key 加密 body
		// 第二次 AES: 使用随机生成的 skey 作为 key 加密第一次的结果
		if (function_exists('openssl_encrypt')) {
			// 使用 openssl 进行 AES-128-CBC 加密
            // 注意：options=0 表示默认 PKCS7 padding，并且返回 base64 编码结果
			$encrypted1 = openssl_encrypt($body, 'aes-128-cbc', $nonce, OPENSSL_RAW_DATA, $vi); // 输出 raw data
            if ($encrypted1 === false) throw new \Exception("第一次 AES 加密失败 (openssl)。");
			$params = openssl_encrypt($encrypted1, 'aes-128-cbc', hex2bin($skey), OPENSSL_RAW_DATA, $vi); // 使用 hex 解码后的 skey
            if ($params === false) throw new \Exception("第二次 AES 加密失败 (openssl)。");
            $params = base64_encode($params); // 手动进行 Base64 编码
		} elseif (function_exists('mcrypt_encrypt')) {
            // 使用 mcrypt (已废弃)
            // 手动 PKCS7 padding
            $pad = 16 - (strlen($body) % 16);
            $body_padded = $body . str_repeat(chr($pad), $pad);
			$encrypted1 = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $nonce, $body_padded, MCRYPT_MODE_CBC, $vi);
            $pad1 = 16 - (strlen($encrypted1) % 16); // 再次 padding
            $encrypted1_padded = $encrypted1 . str_repeat(chr($pad1), $pad1);
			$params = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, hex2bin($skey), $encrypted1_padded, MCRYPT_MODE_CBC, $vi);
            $params = base64_encode($params);
		} else {
            throw new \Exception("需要 OpenSSL 或 Mcrypt 扩展来进行 AES 加密。");
        }

		// --- RSA 加密过程 ---
		// 使用 RSA 公钥加密随机生成的 AES 密钥 ($skey)
		// 需要 bcmath 扩展处理大数运算
        // (bcmath 检查已在前面 skey 生成处进行)
		$skey_reversed_hex = $this->str2hex(strrev(hex2bin($skey))); // 1. hex 解码 2. 字符串反转 3. 转回 hex
		$skey_dec = $this->bchexdec($skey_reversed_hex); // 4. hex 转为十进制大数
		$encSecKey_dec = bcpowmod($skey_dec, $pubkey, $modulus); // 5. RSA 加密: skey^e mod n
		$encSecKey = $this->bcdechex($encSecKey_dec); // 6. 将加密结果转回十六进制
		// 7. 左侧补 0 直到长度为 256 个字符 (128 字节)
		$encSecKey = str_pad($encSecKey, 256, '0', STR_PAD_LEFT);

		// 修改 API 配置
		$api['url'] = str_replace('/api/', '/weapi/', $api['url']); // URL 从 /api/ 改为 /weapi/
		$api['body'] = array( // 新的请求体包含加密后的 params 和 encSecKey
			'params'	=> $params,
			'encSecKey'	=> $encSecKey,
		);

		return $api; // 返回修改后的 API 配置
	}

	/**
	 * 网易云音乐特定 ID 加密 (用于图片 URL)。
	 * @param string $id 原始图片 ID。
	 * @return string 加密后的 ID 字符串。
	 */
	protected function netease_encryptId($id)
	{
		// 加密密钥 (固定)
		$magic = str_split('3go8&$8*3*3h0k(2)2');
		$song_id = str_split((string)$id); // 确保是字符串
		// 对 ID 的每个字符与 magic 中的字符进行异或操作
		for ($i = 0; $i < count($song_id); $i++) {
			$song_id[$i] = chr(ord($song_id[$i]) ^ ord($magic[$i % count($magic)]));
		}
		// 计算异或后结果的 MD5 (二进制格式)
		$md5_binary = md5(implode('', $song_id), true);
		// 进行 Base64 编码
		$result = base64_encode($md5_binary);
		// 替换 Base64 结果中的 '/' 和 '+' 为 '_' 和 '-'
		$result = str_replace(array('/', '+'), array('_', '-'), $result);
		return $result;
	}

	/**
	 * 网易云音乐 URL 响应解码器。
	 * @param string $result 原始 JSON 响应字符串。
	 * @return string 包含 'url', 'size', 'br' 的 JSON 字符串。
	 */
	protected function netease_url($result)
	{
		$data = json_decode($result, true);
		$urlInfo = ['url' => '', 'size' => 0, 'br' => -1]; // 默认值

		// 检查响应结构是否有效
		if (isset($data['data'][0])) {
            $songData = $data['data'][0];
            // 优先使用 'uf' 中的 URL (可能是更高音质或不同格式?)
            if (!empty($songData['uf']['url'])) {
                $songData['url'] = $songData['uf']['url'];
            }
            // 如果 URL 存在
			if (!empty($songData['url'])) {
				$urlInfo = array(
					'url'	=> $songData['url'], // 播放链接
					'size'	=> $songData['size'] ?? 0, // 文件大小
					'br'	=> isset($songData['br']) ? $songData['br'] / 1000 : 0, // 码率 (从 bps 转为 kbps)
				);
			}
		}
		// 返回编码后的 JSON 字符串
		return json_encode($urlInfo);
	}

	/**
	 * QQ 音乐 URL 响应解码器 (执行第二步请求)。
	 * @param string $result 第一步请求 (song) 返回的原始 JSON 响应字符串。
	 * @return string 包含 'url', 'size', 'br' 的 JSON 字符串。
	 */
	protected function tencent_url($result)
	{
		$data = json_decode($result, true);
		$urlInfo = ['url' => '', 'size' => 0, 'br' => -1]; // 默认值

		// 检查第一步响应是否有效
		if (!isset($data['data'][0]['mid']) || !isset($data['data'][0]['file']['media_mid'])) {
			return json_encode($urlInfo); // 返回空信息
		}

		$songInfo = $data['data'][0];
		$guid = (string)(mt_rand(100000000, 999999999)); // 生成随机 GUID
        $filenamePrefixMap = [ // 码率与文件前缀映射
            'F000' => 999, 'M800' => 320, 'M500' => 128,
            'C600' => 192, 'C400' => 96, 'C200' => 48, 'C100' => 24
        ];
        $fileExtMap = [ // 文件前缀与扩展名映射
            'F000' => 'flac', 'M800' => 'mp3', 'M500' => 'mp3',
            'C600' => 'm4a', 'C400' => 'm4a', 'C200' => 'm4a', 'C100' => 'm4a'
        ];
        $fileSizeKeyMap = [ // 文件前缀与大小字段键名映射
            'F000' => 'size_flac', 'M800' => 'size_320mp3', 'M500' => 'size_128mp3',
            'C600' => 'size_192aac', 'C400' => 'size_96aac', 'C200' => 'size_48aac', 'C100' => 'size_24aac'
        ];

		// 从 Cookie 中提取 uin (如果存在)
		$uin = '0';
		if(isset($this->header['Cookie'])) {
            preg_match('/uin=(\d+)/', $this->header['Cookie'], $uin_match);
            if (count($uin_match)) {
                $uin = $uin_match[1];
            }
        }

		// 构建第二步请求 (获取 vkey) 的 payload
		$payload = [
			'req_0'	=> [
				'module'=> 'vkey.GetVkeyServer',
				'method'=> 'CgiGetVkey',
				'param'	=> [
					'guid'		=> $guid,
					'songmid'	=> [],
					'filename'	=> [],
					'songtype'	=> [], // 歌曲类型，通常为 0
					'uin'		=> $uin,
					'loginflag'	=> 1,
					'platform'	=> '20', // 平台标识
				],
			],
            // (可以添加 req_1 获取 pcdn 地址，但 sip 已包含)
		];

        // 为所有可能的码率构建请求参数
        foreach($filenamePrefixMap as $prefix => $br) {
            if(isset($songInfo['file'][$fileSizeKeyMap[$prefix]]) && $songInfo['file'][$fileSizeKeyMap[$prefix]] > 0) { // 检查文件大小是否存在且大于0
                $payload['req_0']['param']['songmid'][] = $songInfo['mid'];
                $payload['req_0']['param']['filename'][] = $prefix . $songInfo['file']['media_mid'] . '.' . $fileExtMap[$prefix];
                $payload['req_0']['param']['songtype'][] = $songInfo['type'] ?? 0; // 添加歌曲类型
            }
        }

        // 如果没有任何有效的文件类型，直接返回空
        if(empty($payload['req_0']['param']['songmid'])){
            return json_encode($urlInfo);
        }

		// 配置第二步 API 请求
		$api = array(
			'method'=> 'POST', // 使用 POST 请求 vkey 接口更稳定
			'url'	=> 'https://u.y.qq.com/cgi-bin/musicu.fcg?format=json&data=' . urlencode(json_encode($payload)), // 将 payload 放在 URL 中? 通常应该在 body 中
            'body' => null, // POST body 为空，参数在 URL 里
            // 或者更标准的 POST:
            // 'url' => 'https://u.y.qq.com/cgi-bin/musicu.fcg',
            // 'body' => ['format' => 'json', 'data' => json_encode($payload)],
		);

		// 执行第二步请求 (获取 vkey)
        // 需要临时禁用 format，因为 exec 内部会再次调用此方法导致死循环
        $originalFormat = $this->format;
        $this->format = false;
		$response_raw = $this->exec($api);
        $this->format = $originalFormat; // 恢复 format

		$response = json_decode($response_raw, true);

		// 检查第二步响应是否有效
		if (!isset($response['req_0']['data']['midurlinfo']) || !isset($response['req_0']['data']['sip'][0])) {
			return json_encode($urlInfo); // 返回空信息
		}
		$vkeys = $response['req_0']['data']['midurlinfo'];
        $sip = $response['req_0']['data']['sip'][0]; // 获取 CDN 服务器地址

        // --- 选择最佳可用链接 ---
        $bestBr = -1;
        $foundUrl = false;

        // 优先选择用户请求的码率 ($this->temp['br']) 或以下
        foreach ($filenamePrefixMap as $prefix => $br) {
            // 检查此码率是否存在于 vkeys 响应中 (通过检查 filename)
            $targetFilename = $prefix . $songInfo['file']['media_mid'] . '.' . $fileExtMap[$prefix];
            $vkeyIndex = -1;
            foreach($vkeys as $index => $vkeyInfo){
                if(isset($vkeyInfo['filename']) && $vkeyInfo['filename'] === $targetFilename){
                    $vkeyIndex = $index;
                    break;
                }
            }

            // 如果找到了对应的 vkey 信息，并且该码率小于等于用户请求的码率
            if ($vkeyIndex !== -1 && $br <= $this->temp['br']) {
                $currentVkeyInfo = $vkeys[$vkeyIndex];
                // 检查 vkey 是否有效且 purl 存在
				if (!empty($currentVkeyInfo['vkey']) && !empty($currentVkeyInfo['purl'])) {
                    // 如果当前码率比已找到的更好，则更新 urlInfo
                    if ($br > $bestBr) {
                        $bestBr = $br;
                        $urlInfo = array(
                            'url'	=> $sip . $currentVkeyInfo['purl'], // 拼接最终 URL
                            'size'	=> $songInfo['file'][$fileSizeKeyMap[$prefix]] ?? 0, // 获取文件大小
                            'br'	=> $br, // 实际码率
                        );
                        $foundUrl = true;
                    }
				}
            }
        }

		// 返回最终找到的最佳 URL 信息
		return json_encode($urlInfo);
	}

	/**
	 * 酷狗音乐 URL 响应解码器。
	 * @param string $result 权限接口返回的原始 JSON 响应字符串。
	 * @return string 包含 'url', 'size', 'br' 的 JSON 字符串。
	 */
	protected function kugou_url($result)
	{
		$data = json_decode($result, true);
		$urlInfo = ['url' => '', 'size' => 0, 'br' => -1]; // 默认值

		// 检查权限接口响应是否有效
		if (!isset($data['data'][0]['relate_goods']) || !is_array($data['data'][0]['relate_goods'])) {
			return json_encode($urlInfo);
		}

		$maxBr = 0; // 用于记录找到的最佳音质

		// 遍历所有可用的商品（不同音质）
		foreach ($data['data'][0]['relate_goods'] as $vo) {
            // 检查商品信息是否完整
            if (!isset($vo['info']['bitrate']) || !isset($vo['hash'])) continue;

            $currentBr = $vo['info']['bitrate']; // 当前音质 (bps)

			// 如果当前音质小于等于用户请求的音质，并且优于已找到的最佳音质
			if ($currentBr <= $this->temp['br'] * 1000 && $currentBr > $maxBr) { // 注意比较单位是 bps
				// 请求 tracker 接口获取实际下载链接
				$api = array(
					'method'=> 'GET',
					'url'	=> 'http://trackercdn.kugou.com/i/v2/', // tracker 地址
					'body'	=> array(
						'hash'		=> $vo['hash'], // 文件 hash
						'key'		=> md5($vo['hash'].'kgcloudv2'), // 计算 key
						'pid'		=> '3', // pid?
						'behavior'	=> 'download', // 行为
						'cmd'		=> '25', // 命令
                        'filename'  => $vo['info']['fileName'] ?? ($id.'.mp3'), // 尝试传递文件名
					),
				);
                // 执行 tracker 请求 (需要临时禁用 format)
                $originalFormat = $this->format;
                $this->format = false;
				$t_raw = $this->exec($api);
                $this->format = $originalFormat;
				$t = json_decode($t_raw, true);

				// 检查 tracker 响应是否有效且包含 URL
				if (isset($t['url'][0])) { // 假设 URL 总是在数组的第一个元素
					$maxBr = $t['bitRate']; // 更新找到的最佳音质 (bps)
					$urlInfo = array(
						'url'	=> $t['url'][0], // 实际播放链接
						'size'	=> $t['fileSize'] ?? 0, // 文件大小
						'br'	=> isset($t['bitRate']) ? $t['bitRate'] / 1000 : 0, // 码率 (kbps)
					);
				}
			}
		}
		// 返回最终找到的最佳 URL 信息
		return json_encode($urlInfo);
	}

	/**
	 * 酷我音乐 URL 响应解码器。
	 * @param string $result 反爬接口返回的原始响应字符串 (非 JSON)。
	 * @return string 包含 'url', 'br' 的 JSON 字符串。
	 */
	protected function kuwo_url($result)
	{
        // 酷我的反爬接口直接返回 URL 字符串，或者错误信息
        $urlInfo = ['url' => '', 'br' => -1]; // 默认值

        // 检查返回的是否是有效的 URL
        if (filter_var($result, FILTER_VALIDATE_URL)) {
            $urlInfo = array(
                'url'	=> $result, // 直接使用返回的 URL
                // 酷我这个接口返回的链接码率通常不确定或固定为较低值 (如 128k)
                // 需要通过其他方式获取准确码率，或者提供一个估值
                'br'	=> 128, // 假设为 128 kbps
            );
        }
        // (可以添加对特定错误字符串的判断)
        // else { log error? }

		// 返回 JSON 编码的结果
		return json_encode($urlInfo);
	}

	/**
	 * 网易云音乐歌词响应解码器。
	 * @param string $result 原始 JSON 响应字符串。
	 * @return string 包含 'lyric', 'tlyric' 的 JSON 字符串。
	 */
	protected function netease_lyric($result)
	{
		$data = json_decode($result, true);
		$lyricInfo = array(
			'lyric'	=> $data['lrc']['lyric'] ?? '', // 原版歌词
			'tlyric'=> $data['tlyric']['lyric'] ?? '', // 翻译歌词 (可能不存在)
		);
		// 返回 JSON 字符串 (确保 Unicode 正常显示)
		return json_encode($lyricInfo, JSON_UNESCAPED_UNICODE);
	}

	/**
	 * QQ 音乐歌词响应解码器。
	 * @param string $result 原始 JSONP 响应字符串。
	 * @return string 包含 'lyric', 'tlyric' 的 JSON 字符串。
	 */
	protected function tencent_lyric($result)
	{
		// QQ 歌词返回的是 JSONP 格式，需要移除包裹的回调函数和括号
		$result = substr($result, 18, -1); // 移除 "MusicJsonCallback(...)"
		$data = json_decode($result, true);
		$lyricInfo = array(
			'lyric'	=> isset($data['lyric']) ? base64_decode($data['lyric']) : '', // 歌词是 Base64 编码的
			'tlyric'=> isset($data['trans']) ? base64_decode($data['trans']) : '', // 翻译歌词也是 Base64
		);
		// 返回 JSON 字符串
		return json_encode($lyricInfo, JSON_UNESCAPED_UNICODE);
	}

	/**
	 * 酷狗音乐歌词响应解码器。
	 * @param string $result 歌词下载接口返回的原始 JSON 响应字符串。
	 * @return string 包含 'lyric', 'tlyric' 的 JSON 字符串。
	 */
	protected function kugou_lyric($result)
	{
		$data = json_decode($result, true);
		$lyricInfo = array(
			'lyric'	=> isset($data['content']) ? base64_decode($data['content']) : '', // 歌词内容是 Base64 编码
			'tlyric'=> '', // 酷狗此接口似乎不直接提供翻译歌词
		);
		// 返回 JSON 字符串
		return json_encode($lyricInfo, JSON_UNESCAPED_UNICODE);
	}

	/**
	 * 酷我音乐歌词响应解码器。
	 * @param string $result 原始 JSON 响应字符串。
	 * @return string 包含 'lyric', 'tlyric' 的 JSON 字符串。
	 */
	protected function kuwo_lyric($result)
	{
		$data = json_decode($result, true);
		$lrc = ''; // 初始化 LRC 歌词字符串
		// 检查响应中是否包含歌词列表
		if (isset($data['data']['lrclist']) && is_array($data['data']['lrclist'])) {
			// 遍历歌词列表，拼接成标准 LRC 格式
			foreach($data['data']['lrclist'] as $line) {
				if(isset($line['time']) && isset($line['lineLyric'])) {
                    $time = floatval($line['time']); // 时间戳 (秒)
                    $minutes = floor($time / 60);
                    $seconds = floor($time % 60);
                    $milliseconds = round(($time - floor($time)) * 1000); // 计算毫秒
                    // 格式化时间戳 [mm:ss.xx] 或 [mm:ss.xxx]
                    $timestamp = sprintf("[%02d:%02d.%02d]", $minutes, $seconds, floor($milliseconds / 10)); // 使用 xx 格式
                    // 拼接 LRC 行
                    $lrc .= $timestamp . $line['lineLyric'] . "\n";
                }
			}
		}
		$lyricInfo = array(
			'lyric'	=> $lrc, // 拼接好的 LRC 歌词
			'tlyric'=> '', // 酷我此接口不提供翻译歌词
		);
		// 返回 JSON 字符串
		return json_encode($lyricInfo, JSON_UNESCAPED_UNICODE);
	}


	// --- 数据格式化方法 (将不同源的数据统一为标准结构) ---

	/**
	 * 格式化网易云音乐搜索/歌曲/专辑/歌单结果。
	 * @param array $data 单个歌曲信息数组。
	 * @return array 标准化后的歌曲信息数组。
	 */
	protected function format_netease($data)
	{
		$result = array(
			'id'		=> $data['id'], // 歌曲 ID
			'name'		=> $data['name'], // 歌名
			'artist'	=> array(), // 歌手列表 (数组)
			'album'		=> $data['al']['name'] ?? '', // 专辑名
			// 图片 ID: 尝试从 pic_str 获取，否则用 pic，如果 picUrl 存在则解析数字 ID
            'pic_id'    => $data['al']['pic_str'] ?? $data['al']['pic'] ?? null,
			'url_id'	=> $data['id'], // 用于获取 URL 的 ID (通常同歌曲 ID)
			'lyric_id'	=> $data['id'], // 用于获取歌词的 ID (通常同歌曲 ID)
			'source'	=> 'netease', // 来源标识
		);
        // 解析 picUrl 获取数字 ID
        if (isset($data['al']['picUrl']) && $result['pic_id'] === null) {
			preg_match('/\/(\d+)\./', $data['al']['picUrl'], $match);
            if(isset($match[1])) {
                $result['pic_id'] = $match[1];
            }
		}
        // 处理歌手信息
		if (isset($data['ar']) && is_array($data['ar'])) {
            foreach ($data['ar'] as $vo) {
                if (isset($vo['name'])) {
                    $result['artist'][] = $vo['name'];
                }
            }
        }
		return $result;
	}

	/**
	 * 格式化 QQ 音乐搜索/歌曲/专辑/歌单结果。
	 * @param array $data 单个歌曲信息数组。
	 * @return array 标准化后的歌曲信息数组。
	 */
	protected function format_tencent($data)
	{
		// 兼容 playlist 返回的 musicData 结构
		if (isset($data['musicData']) && is_array($data['musicData'])) {
			$data = $data['musicData'];
		}
		$result = array(
			'id'		=> $data['mid'] ?? $data['songmid'] ?? null, // 歌曲 mid (优先使用 mid)
			'name'		=> $data['name'] ?? $data['songname'] ?? '', // 歌名
			'artist'	=> array(), // 歌手列表
			'album'		=> isset($data['album']['title']) ? trim($data['album']['title']) : ($data['albumname'] ?? ''), // 专辑名
			'pic_id'	=> $data['album']['mid'] ?? $data['albummid'] ?? null, // 图片 ID (专辑 mid)
			'url_id'	=> $data['mid'] ?? $data['songmid'] ?? null, // URL ID (歌曲 mid)
			'lyric_id'	=> $data['mid'] ?? $data['songmid'] ?? null, // 歌词 ID (歌曲 mid)
			'source'	=> 'tencent', // 来源
		);
        // 处理歌手信息
		if (isset($data['singer']) && is_array($data['singer'])) {
            foreach ($data['singer'] as $vo) {
                if (isset($vo['name'])) {
                    $result['artist'][] = $vo['name'];
                }
            }
        }
		return $result;
	}

	/**
	 * 格式化酷狗音乐搜索/歌曲/专辑/歌单结果。
	 * @param array $data 单个歌曲信息数组。
	 * @return array 标准化后的歌曲信息数组。
	 */
	protected function format_kugou($data)
	{
		$result = array(
			// ID 使用 hash
			'id'		=> $data['hash'] ?? $data['audio_id'] ?? null, // 优先用 hash
			// 歌名通常在 filename 或 fileName 字段中，格式为 "歌手 - 歌名"
			'name'		=> isset($data['filename']) ? $data['filename'] : ($data['fileName'] ?? ''),
			'artist'	=> array(), // 歌手列表，稍后从 name 中提取
			// 专辑名
			'album'		=> $data['album_name'] ?? $data['albumname'] ?? '',
			// URL/Pic/Lyric ID 都使用 hash
			'url_id'	=> $data['hash'] ?? null,
			'pic_id'	=> $data['hash'] ?? null, // 酷狗图片获取比较特殊，但也基于 hash
			'lyric_id'	=> $data['hash'] ?? null,
			'source'	=> 'kugou', // 来源
		);
		// 从 "歌手 - 歌名" 格式中分离歌手和歌名
		if (!empty($result['name']) && strpos($result['name'], ' - ') !== false) {
            list($artist_str, $song_name) = explode(' - ', $result['name'], 2);
            $result['artist'] = array_map('trim', explode('、', $artist_str)); // 按 '、' 分割多个歌手并去除空格
            $result['name'] = trim($song_name);
        }
        // 如果分离失败，尝试从 singername 字段获取歌手
        elseif(empty($result['artist']) && !empty($data['singername'])) {
             $result['artist'] = array_map('trim', explode('、', $data['singername']));
        }

		return $result;
	}

	/**
	 * 格式化酷我音乐搜索结果 (abslist)。
	 * @param array $data 单个歌曲信息数组 (来自 abslist)。
	 * @return array 标准化后的歌曲信息数组。
	 */
	protected function format_kuwo($data)
	{
        // 从 MUSICRID 中提取数字 ID
        $rid = isset($data['MUSICRID']) ? str_replace("MUSIC_", "", $data['MUSICRID']) : null;
		$result = array(
			'id'		=> $rid, // 歌曲 RID
			'name'		=> $data['NAME'] ?? '', // 歌名
			'artist'	=> isset($data['ARTIST']) ? explode('&', $data['ARTIST']) : [], // 歌手 (按 '&' 分割)
			'album'		=> $data['ALBUM'] ?? '', // 专辑名
			'pic_id'	=> $rid, // 图片 ID (使用 RID)
			'url_id'	=> $rid, // URL ID (使用 RID)
			'lyric_id'	=> $rid, // 歌词 ID (使用 RID)
			'source'	=> 'kuwo', // 来源
		);
		return $result;
	}

	/**
	 * 格式化酷我音乐歌曲信息结果 (来自 song 方法)。
     * 注意：这个方法似乎与 format_kuwo 重复了，可能是早期版本遗留。
     * 现代的 clean 方法已改为直接调用 format_song_kuwo。
	 * @param array $data 单个歌曲信息数组。
	 * @return array 标准化后的歌曲信息数组。
	 */
	protected function format_song_kuwo($data)
	{
        // 这个方法接收的是已经解码后的数组，而不是原始 JSON 字符串
        // 假设 $data 是单个歌曲信息的关联数组
		$result = array(
			'id'		=> $data['id'] ?? null, // 歌曲 ID (通常是数字 RID)
			'name'		=> $data['name'] ?? '', // 歌名
			'artist'	=> isset($data['artist']) ? explode('&', $data['artist']) : [], // 歌手
			'album'		=> $data['album'] ?? '', // 专辑
			'pic_id'	=> $data['id'] ?? null, // Pic ID (使用 RID)
			'url_id'	=> $data['id'] ?? null, // URL ID (使用 RID)
			'lyric_id'	=> $data['id'] ?? null, // Lyric ID (使用 RID)
			'source'	=> 'kuwo', // 来源
		);
		return $result;
	}

	/**
	 * 格式化酷我音乐专辑页面 HTML 解析结果。
	 * @param string $data 包含专辑页面 HTML 的字符串。
	 * @return array 标准化后的歌曲信息数组列表。
	 */
	protected function format_album_kuwo($data)
	{
        $songList = []; // 初始化歌曲列表数组
        // 使用 DOMDocument 解析 HTML
		$dom = new DOMDocument();
		// 禁用 PHP 内建的 HTML 解析错误报告，避免干扰输出
		libxml_use_internal_errors(true);
		// 加载 HTML 字符串
		if (!$dom->loadHTML('<?xml encoding="utf-8" ?>' . $data)) { // 添加 XML 头解决编码问题
            libxml_clear_errors(); // 清除错误
            return []; // 加载失败返回空数组
        }
		// 清除可能产生的解析错误
		libxml_clear_errors();

        // 使用 XPath 查询查找包含歌曲信息的元素
        $xpath = new \DOMXPath($dom);
        // 查询所有 class 包含 'song_item' 的 li 元素
        $liNodes = $xpath->query("//li[contains(@class, 'song_item')]");

        // 遍历找到的 li 节点
        foreach ($liNodes as $li) {
            $songData = [ // 初始化当前歌曲数据
                'id' => null, 'name' => '', 'artist' => [], 'album' => '',
                'pic_id' => null, 'url_id' => null, 'lyric_id' => null,
                'source' => 'kuwo'
            ];

            // 提取歌曲名和 ID (通常在 class='song_name' 下的 a 标签)
            $nameNode = $xpath->query(".//div[contains(@class, 'song_name')]/a", $li)->item(0);
            if ($nameNode) {
                $songData['name'] = trim($nameNode->getAttribute('title'));
                $href = $nameNode->getAttribute('href'); // 形如 /play_detail/12345
                if (preg_match('/\/play_detail\/(\d+)/', $href, $matches)) {
                    $songData['id'] = $matches[1];
                    $songData['pic_id'] = $matches[1];
                    $songData['url_id'] = $matches[1];
                    $songData['lyric_id'] = $matches[1];
                }
            }

            // 提取歌手名 (通常在 class='song_artist' 下的 span 或 a 标签)
            $artistNode = $xpath->query(".//div[contains(@class, 'song_artist')]/*[@title]", $li)->item(0); // 查找带 title 属性的子元素
            if ($artistNode) {
                $artistStr = trim($artistNode->getAttribute('title'));
                $songData['artist'] = explode('&', $artistStr); // 按 '&' 分割
            }

            // 提取专辑名 (通常在 class='song_album' 下的 span 或 a 标签)
            $albumNode = $xpath->query(".//div[contains(@class, 'song_album')]/*[@title]", $li)->item(0);
            if ($albumNode) {
                $songData['album'] = trim($albumNode->getAttribute('title'));
            }

            // 如果成功提取到 ID，则将该歌曲添加到结果列表
            if ($songData['id'] !== null) {
                $songList[] = $songData;
            }
        }
		// 返回包含所有歌曲信息的数组
		return $songList;
	}
} // End of class Meting
?>
