<?php
    /*
     *--------------------------------------------------------------------------
     * RSS Feed Generator.
     *--------------------------------------------------------------------------
    */
    // fetch_rss
    function wp_feed_cache_transient_expire_instantly( $seconds ) {
      return 0;  // set the default feed cache recreation period
    }
    function fetch_rss_feeds($rssUrl, $rssLink, $rssMax = 1) {
        // 检查fetch_feed是否可用 // https://developer.wordpress.org/reference/functions/fetch_feed/
        if (!function_exists('fetch_feed')) {
            echo "fetch_feed 不可用，无法解析RSS feed。";
            exit;
        }
        $linkUrl = $rssLink->link_url;
        $linkAuthor = $rssLink->link_name;
        $linkAvatar = $rssLink->link_image ? $rssLink->link_image : '//cravatar.cn/avatar/?d=mp&s=50';
        // delete_transient('feed_' . md5($rssUrl));  // delete_transient immediately
        add_filter( 'wp_feed_cache_transient_lifetime' , 'wp_feed_cache_transient_expire_instantly' );
        $feed_data = fetch_feed($rssUrl);
        remove_filter( 'wp_feed_cache_transient_lifetime' , 'wp_feed_cache_transient_expire_instantly' );
        if (is_wp_error($feed_data)) {
            $error_class = new stdClass();
            $error_class->title = ''; //RSS 内容抓取失败！
            $error_class->desc = '无法获取 ta 的 rss 内容，请检查：' . $rssUrl;
            $error_class->date = '0000-00-00'; //date("Y-m-d");
            $error_class->link = 'javascript:;';
            $error_class->url = $linkUrl;
            $error_class->rss = $rssUrl;
            $error_class->author = $linkAuthor;
            $error_class->avatar = $linkAvatar;
            // array_push($output_array, $error_class);
            return $error_class;
        }
        // Figure out how many total items there are, then limit
    	$maxitems = $feed_data->get_item_quantity($rssMax); 
    	// Build an array of all the items, starting with element 0 (first element).
    	$rss_items = $feed_data->get_items(0, $maxitems);
        $recent_post = $rss_items[0];
        // 回传数据
        $output_class = new stdClass();
        $output_class->title = (string)$recent_post->get_title();
        $output_class->desc = (string)mb_substr(strip_tags($recent_post->get_description()), 0, 200);
        $output_class->link = (string)$recent_post->get_permalink();
        $output_class->date = $recent_post->get_date("Y-m-d");
        $output_class->url = $linkUrl;
        $output_class->rss = $rssUrl;
        $output_class->author = $linkAuthor;
        $output_class->avatar = $linkAvatar;
        // output child only if multiple items(output-limit depends on real-rssCount but manual-rssMax)
    	$rss_count = count($rss_items);
        if ($rss_count > 1) {
            $output_class->child = array();
            for ($i=1; $i<$rss_count; $i++) {
                $item = $rss_items[$i];
                // if (!is_callable($item->get_title)) break;
                $child_class = new stdClass();
                $child_class->title = (string)$item->get_title();
                $child_class->desc = mb_substr(strip_tags((string)$item->get_description()), 0, 200);
                $child_class->date = date('Y-m-d', strtotime((string)$item->get_date("Y-m-d")));
                $child_class->link = (string)$item->get_permalink();
                array_push($output_class->child, $child_class);
            }
        }
        return $output_class;
    }
    function parse_rss_urls($link_marks, $output_limit) {
        // $output_array required to return in function (record lastUpdate date)
        date_default_timezone_set('Asia/Shanghai');
        $output_object = new stdClass();
        $output_object->lastUpdate = date("Y-m-d H:i:s");
        $output_array = array( 0 => $output_object );
        foreach ($link_marks as $link_mark) {
            $link_rss = $link_mark->link_rss;
            // echo "$link_rss <br/>";
            // $feed_data = get_rss_feeds($link_rss, $link_mark, $output_limit);
            // $feed_data = get_rss_feeds($link_rss, $link_mark, $output_limit, true);
            // $feed_data = fetch_rss_feeds($link_rss, $link_mark, $output_limit);
            for ($i=0; $i<1; $i++) {
                $feed_data = fetch_rss_feeds($link_rss, $link_mark, $output_limit);
                if ($feed_data !== null) break; // 成功获取结果，跳出重试循环
                // sleep(1); // 等待后重试
            }
            array_push($output_array, $feed_data);
        }
        return $output_array; //json_encode($output_array);
    }
    function parse_rss_data($link_marks, $output_limit, $output_chunk = 10) {
        $marks_count = count($link_marks);
        
        if ($marks_count <= $output_chunk) {
            $feed_data = parse_rss_urls($link_marks, $output_limit);
            return json_encode($feed_data);
        }
        
        echo "request urls overflow($output_chunk/$marks_count), chucking requests..";
        // request each chunk of array step by step
        $output_arraies = array();
        $chunk_array = array_chunk($link_marks, $output_chunk);
        $chunk_count = count($chunk_array);
        // print_r($chunk_array);
        for ($i=0; $i<$chunk_count; $i++) {
            $feed_data = parse_rss_urls($chunk_array[$i], $output_limit);
            // array_push($output_arraies, $feed_data);  // original data construction($chunk_array Array list in Array)
            foreach ($feed_data as $data) {
                array_push($output_arraies, $data);  // data deconstruction of $feed_data Array
            }
            // wait a sec for next round..
            // sleep(1);
        }
        // $i = 0;
        // for (;$i<$chunk_count;) {
        //     $feed_data = parse_rss_urls($chunk_array[$i], $output_limit);
        //     if ($feed_data && is_array($feed_data)) {
        //         foreach ($feed_data as $data) {
        //             array_push($output_arraies, $data);  // data deconstruction of $feed_data Array
        //         }
        //         $i++; // jump to next round after data received.
        //     }
        // }
        return json_encode($output_arraies);
    }
    function the_rss_feeds($output_data, $order=SORT_DESC) {
        // print_r($output_data);
        $output_date = isset($output_data[0]->lastUpdate) ? $output_data[0]->lastUpdate : '0000-00-00';
        if (!$output_data || count($output_data) <= 1) {
            echo 'Empty RSS Data!! (lastUpdate: ' . $output_date . ')';
        } else {
            array_shift($output_data);  // no lastUpdate
            // 首先按日期降序排序，如果日期相同，则按标题升序排序
            array_multisort(array_map(function($item) {
                return $item->date;
            }, $output_data), $order, array_map(function($item) {
                return $item->title;
            }, $output_data), SORT_ASC, $output_data);
            // // 按标题字母升序排序（如果日期相同）
            // usort($output_data, function($a, $b) {
            //     $dateComparison = strtotime($b->date) - strtotime($a->date);
            //     if ($dateComparison == 0) {
            //         return strcmp($a->title, $b->title);
            //     }
            //     return $dateComparison;
            // });
            echo '<style>
            .feeds{margin:15px auto}
            .feeds a{font-size:medium}
            .feeds .info{margin-bottom:15px}
            .feeds .info img,
            .feeds .info i,
            .feeds .info b{vertical-align:middle}
            .feeds .info a{display:inline-block;color: initial;text-decoration: none;font-size: small;}
            .feeds .info img{width:25px;height:25px;border-radius:50%}
            .feeds .info b{margin:auto 7px auto 5px;opacity:.75}
            .feeds .pub{opacity: .75;font-style:italic}
            /*.feeds .rest .content{text-indent:2em}*/
            .feeds .rest ol{margin-left:3rem}
            .feeds details summary:hover{text-decoration:underline}
            .feeds details summary{cursor:pointer;user-select:none;-webkit-user-select:none;}</style>';
            foreach ($output_data as $data) {
    ?>
                <div class="feeds">
                    <a href="<?php echo $data->link; ?>" target="_blank"><?php echo $data->title; ?></a>
                    <p><?php echo $data->desc; ?>...</p>
                    <div class="info">
                        <a href="<?php echo $data->rss; ?>" target="_blank">
                            <img src="<?php echo $data->avatar; ?>" alt="<?php echo $data->author; ?>" />
                        </a>
                        <a href="<?php echo $data->url; ?>" target="_blank">
                            <b><?php echo $data->author; ?></b>
                        </a>
                        <i class="pub">published at <?php echo $data->date; ?></i>
                    </div>
                    <?php
                        $output_child = $data->child;
                        if (isset($output_child)) {
                            $output_limit = count($output_child);
                    ?>
                            <details class="rest">
                                <summary> 浏览其余 <?php echo $output_limit; ?> 篇文章 </summary>
                                <ol>
                                    <?php
                                        foreach ($output_child as $child) {
                                    ?>
                                            <li>
                                                <a href="<?php echo $child->link; ?>" target="_blank"><?php echo $child->title; ?></a>
                                                <p class="content"><?php echo $child->desc; ?>...</p>
                                                <p class="pub">Published at <?php echo $child->date; ?></p>
                                            </li>
                                    <?php
                                        }
                                    ?>
                                </ol>
                            </details>
                    <?php
                        }
                    ?>
                </div>
                <hr />
    <?php
            }
        }
    }
    
    // SimpleXML
    function get_rss_feeds($rssUrl, $rssLink, $rssMax = 1, $curlMulti = false) {
        // 检查 SimpleXML 扩展是否可用
        if (!extension_loaded('simplexml')) {
            echo "SimpleXML 扩展未启用，无法解析RSS feed。";
            exit;
        }
        
        // $context = stream_context_create(array(
        //     'http' => array(
        //         'method' => 'GET',
        //         'header' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36'
        //     )
        // ));
        // $response = file_get_contents($rssUrl, false, $context);
        // if ($response === false) {
        //     echo "RSS 请求失败！（$rssUrl）";
        //     return; // 或者处理错误
        // }
        if ($curlMulti) {
             // 初始化 cURL 多句柄
            $mh = curl_multi_init();
            $ch = curl_init($rssUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36');
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
            // 将 cURL 句柄添加到多句柄中
            curl_multi_add_handle($mh, $ch);
        
            // 执行多句柄中的所有 cURL 请求
            $active = null;
            do {
                $status = curl_multi_exec($mh, $active);
                curl_multi_select($mh);
            } while ($active && $status == CURLM_OK);
        
            // 获取请求结果
            $response = curl_multi_getcontent($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_multi_close($mh);
        } else {
            $ch = curl_init($rssUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36');
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            
            // for ($i=0; $i<$rssRetry; $i++) {
            //     $response = curl_exec($ch);
            //     $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            //     if ($response === false) {
            //         echo "CURL 请求失败：" . curl_error($ch) . "（$rssUrl）";
            //         curl_close($ch);
            //         return null;
            //     }
            //     if ($httpCode == 200) {
            //         break;
            //     } else {
            //         echo "HTTP 请求失败，状态码：$httpCode（$rssUrl）";
            //         sleep(1); // 等待1秒后重试
            //     }
            // }
            // curl_close($ch);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        }
        if ($response === false || $httpCode != 200) {
            echo "RSS 请求失败！code: $httpCode（$rssUrl）";
            return; // 或者处理错误
        }
        
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response);
        libxml_clear_errors();
        
        if ($xml === false) {
            echo "SimpleXML扩展无法加载RSS feed（$rssUrl）";
            return; // 或者处理错误
        }
    
        // 获取item节点并限制数量
        $classical_rss = isset($xml->channel);
        // 确定是哪种类型的feed并获取条目
        if ($classical_rss) {
            $entries = $xml->channel->item;
            $entries = array_slice($xml->xpath('/rss/channel/item'), 0, $rssMax);
        }else{
            //atom.xml
            $entries = is_array($xml->entry) ? $xml->entry : [$xml->entry];
            $entries = array_slice($entries, 0, $rssMax); // 限制加载的文章数量
        }
        $recent_post = $entries[0];
        if ($classical_rss) {
            $post_desc = $recent_post->description;
            $post_date = $recent_post->pubDate;
            $post_link = $recent_post->link;
            $post_author = $xml->channel->title;
        }else{
            // atom.xml
            $post_desc = $recent_post->content; //summary
            $post_date = $recent_post->published;
            $post_link = $recent_post->link['href'];
            $post_author = $xml->title;
        }
        // 回传数据
        $output_class = new stdClass();
        $output_class->title = (string)$recent_post->title;
        $output_class->desc = (string)mb_substr(strip_tags($post_desc), 0, 200);
        $output_class->date = date("Y-m-d", strtotime($post_date));
        $output_class->link = (string)$post_link;
        $output_class->url = $rssLink->link_url;
        $output_class->author = (string)$post_author;
        $output_class->avatar = (string)$rssLink->link_image ?? '//cravatar.cn/avatar/?d=mp&s=50';
        if ($rssMax > 1) {
            $output_class->child = array();
            for ($i=1; $i<$rssMax; $i++) {
                $item = $classical_rss ? $entries[$i] : $xml->entry[$i];  //atom adaption
                $desc = isset($item->description) ? $item->description : $item->content; //summary
                $date = isset($item->pubDate) ? $item->pubDate : $item->published;
                $link = isset($item->link['href']) ? $item->link['href'] : $item->link;
                $child_class = new stdClass();
                $child_class->title = (string)$item->title;
                $child_class->desc = mb_substr(strip_tags((string)$desc), 0, 200);
                $child_class->date = date('Y-m-d', strtotime((string)$date));
                $child_class->link = (string)$link;
                array_push($output_class->child, $child_class);
            }
        }
        return $output_class;
        // return $xml;
    }
    
    // Gutenberg editor
    load_theme_partial('/inc/wp_blocks.php');  // if(is_edit_page() || is_single()) 
    /*
     *--------------------------------------------------------------------------
     * API Plugin Setup.
     *--------------------------------------------------------------------------
    */
    
    // API接口调用验证，错误处理
    function api_illegal_auth($auth_array=array(), $auth_string=''){
        $is_illegal = false;
        foreach ($auth_array as $path){
            // echo $path.'<br/>';
            $is_illegal = strpos($path, $auth_string)!==false;
        }
        return $is_illegal;
    }
    function api_err_handle($msg='ok', $code=200, $var=false){
        $err_msg = new stdClass();
        $err_msg->code = $code;
        $code===200 ? $err_msg->msg=$msg : $err_msg->err=$msg;
        $res = json_encode($err_msg);
        if($var) {
            return $res;
        }
        print_r($res);
    }
    function api_get_resultText($res_cls_obj, $decode=false){
        $formart = $decode ? json_decode($res_cls_obj) : $res_cls_obj;
        if(isset($formart->error)){
            return $formart->error->message;
        }
        $choices = $formart->choices[0];
        return isset($choices->message) ? $choices->message->content : $choices->text;
        // if(!isset($choices->message)){
        //     return trim($choices->text);
        // }
        // print_r(preg_replace('/\n/',"", $choices->message->content));
    }
    // API调用接口，接受三个参数：调用 api 文件名、api 代理访问（使用 api.php 文件中的 curl 携带鉴权参数二次请求（速度影响），适用前端异步调用、返回请求api或返回sign签名（如开启cdn鉴权
    function get_api_refrence($api='', $xhr=false, $cdn=true, $exe=1, $pid=0){
        global $src_cdn;
        $res = 'unknown_api_refrence';
        if(!$api){
            return api_err_handle(200,$res,true);
        }
        global $post, $cdn_switch;
        $exe = $exe ? $exe : 0;
        $cdn_api = get_option('site_cdn_api');
        $pid = $pid ? $pid : (isset($post->ID) ? $post->ID : 0);
        $api_file = '/'.$api.'.php';
        $authentication = get_option('site_chatgpt_dir', 'authentication');
        $cdn_src = $cdn ? $src_cdn : custom_cdn_src(0, 1);
        $request_url = $cdn_switch&&$cdn_api ? custom_cdn_src('api', true) : $cdn_src.'/plugin/'.$authentication;
        $auth_url = $request_url.$api_file.'?pid='.$pid;
        $cdn_auth = get_option('site_chatgpt_auth');
        // 如出现访问403可能是由于CDN服务器开启了鉴权但后台面板中未填写 API Auth Sign 选项鉴权密钥（无法判断远程服务器是否开启鉴权）
        if($cdn_switch&&$cdn_api&&$cdn_auth){
            $stamp10x = time();
            $stamp16x = dechex($stamp10x);
            $auth_url = $auth_url.'&s='.md5($cdn_auth.$api_file.$stamp16x).'&t='.$stamp16x;
        }
        $res = $xhr ? $cdn_src.'/plugin/api.php?auth='.$api.'&exec='.$exe.'&pid='.$pid.'&' : $auth_url;
        // $res = $xhr ? $cdn_src.'/plugin/'.$authentication.$api_file.'?pid='.$pid : $auth_url; //||!$cdn_api
        return $res;
    }
    function get_plugin_refrence($apiFile = '', $authDir = false, $cdnPath = false) {
        if ($authDir) $authDir = get_option('site_chatgpt_dir') . '/';
        $cdnPath = $cdnPath ? custom_cdn_src('src', 1) : custom_cdn_src(0, 1);
        $plugin_path = $cdnPath . '/plugin/' . $authDir . $apiFile . '.php?';
        return $plugin_path;
    }
    /*
     *--------------------------------------------------------------------------
     * custom site query
     *--------------------------------------------------------------------------
    */
    
    function get_links_category($param = false) {
        $bookmark_categories = get_terms('link_category');
        if (!empty( $bookmark_categories ) && !is_wp_error($bookmark_categories)){
            if ($param) {
                $param_array = array();
                foreach ($bookmark_categories as $bookmark_category) {
                    array_push($param_array, $bookmark_category->$param);
                }
                return $param_array;
            }
            return $bookmark_categories;
        } else {
            echo 'No bookmark categories found.';
        }
    }
    // 返回站点标签链接
    function get_site_bookmarks($category='', $orderby='link_id', $order='ASC', $limit=-1){
        $category_by_slug = $category ? get_term_by('slug', $category, 'link_category') : false;
        $res = get_bookmarks(array(
            'orderby' => $orderby,
            'order' => $order,
            'category_name' => $category_by_slug ? $category_by_slug->name : '',
            'hide_invisible' => 0,
            'limit' => $limit,
            // 'exclude' => 60,
        ));
        return (count($res)>0 ? $res : false);
    }
    // 返回友链指定分类 html
    function get_site_links($links, $frame=false, $strict=false){
        if(!$links) return 'unreachable links provide';
        global $lazysrc, $loadimg;
        $output = '';
        foreach ($links as $link){
            $link_notes = $link->link_notes;
            $link_target = $link->link_target;
            $link_rating = $link->link_rating;
            $link_accessable = $link->link_visible=='Y';
            // if($strict && !$link_accessable){
            //     continue;
            // }
            $link_url = $link->link_url;
            $link_name = $link->link_name;
            $link_desc = $link->link_description;
            $link_descs = $link_desc ? '<span class="lowside-description"><p>'.$link_desc.'</p></span>' : '';
            $standby = 'standby';
            $status = !$link_accessable ? $standby : '';
            $sex = $link_rating==1||$link_rating==10 ? ' girl' : '';
            $ssl = $link_rating>=9 ? ' https' : '';
            $rel = $link->link_rel ? $link->link_rel : false;
            $target = !$link_target ? '_blank' : $link_target;
            $impress = $link_notes&&$link_notes!='' ? '<span class="ssl'.$ssl.'"> '.$link_notes.' </span>' : false;
            $avatar = !$link->link_image ? 'https:' . get_option('site_avatar_mirror') . 'avatar/' . md5(mt_rand().'@rand.avatar') . '?s=300' : $link->link_image;
            $lazyhold = "";
            if($lazysrc!='src'){
                $lazyhold = 'data-src="'.$avatar.'"';
                $avatar = $loadimg;
            }
            switch ($frame) {
                case 'full':
                    $avatar_statu = $status==$standby ? '<img alt="近期访问出现问题" data-err="true" draggable="false">' : '<img '.$lazyhold.' src="'.$avatar.'" alt="'.$link_name.'" draggable="false">';
                    $rel_statu = $rel ? $rel : 'friends';
                    $output .= '<div class="inbox flexboxes '.$status.$sex.'"><div class="inbox-inside flexboxes"><div class="inbox-headside flexboxes">'.$avatar_statu.'</div>'.$impress.'<a href="'.$link_url.'" class="inbox-aside" target="'.$target.'" rel="'.$rel_statu.'" title="'.$link_desc.'"><span class="lowside-title"><h4>'.$link_name.'</h4></span>'.$link_descs.'</a></div></div>';
                    break;
                case 'half':
                    $rel_statu = $rel ? $rel : 'recommends';
                    $output .= '<div class="inbox '.$status.$sex.'"><div class="inbox-inside flexboxes">'.$impress.'<a href="'.$link_url.'" class="inbox-aside" target="'.$target.'" rel="'.$rel_statu.'" title="'.$link_desc.'"><span class="lowside-title"><h4>'.$link_name.'</h4></span>'.$link_descs.'</a></div></div>'; //<em></em>
                    break;
                case 'list':
                    $rel_statu = $rel ? $rel : 'random';
                    $output .= '<li><a href="'.$link_url.'" class="'.$status.'" title="'.$link_desc.'" target="'.$target.'" rel="'.$rel_statu.'">'.$link_name.'</a></li>';
                    break;
                default:
                    $rel_statu = $status==$standby ? 'nofollow' : 'marked';
                    $output .= '<a href="'.$link_url.'" class="'.$status.'" title="'.$link_desc.'" target="'.$target.'" rel="'.$rel_statu.'">'.$link_name.'</a>'; // data-status="'.get_url_status_by_curl($link_url, 3).'"
                    break;
            }
        }
        // unset($lazysrc, $loadimg);
        return $output;
    }
    
    // search/tag page posts with styles
    function the_posts_with_styles($queryString, $rewrite_query=false){
        if(is_archive() || is_search() || check_request_param('cid')){
            global $post, $lazysrc, $loadimg, $src_cdn;
            if($rewrite_query){
                $wp_query = $rewrite_query;
            }else{
                global $wp_query;
            };
            // print_r($wp_query);
            // $current_page = max(1, get_query_var('paged'));
            $maximun_page = $wp_query -> max_num_pages;  // record $maximun_page ouside the loop
            // print_r($current_page.' / '.$maximun_page);
            $post_styles = get_option('site_search_style_switcher');
            if(have_posts()) {
                if($post_styles){
            ?>
                	<link type="text/css" rel="stylesheet" href="<?php echo $src_cdn; ?>/style/news.css?v=2" />
                    <link type="text/css" rel="stylesheet" href="<?php echo $src_cdn; ?>/style/weblog.css" />
                    <link type="text/css" rel="stylesheet" href="<?php echo $src_cdn; ?>/style/acg.css" />
                	<style>
                	    .news-inside-content h2{overflow:hidden}
                	    .win-content.main,
                	    /*.news-inside-content .news-core_area p,*/
                	    .empty_card{margin:15px auto auto;}
                	    .news-inside-content .news-core_area p{padding-left:0}
                    	.win-content{width:100%;padding:0;display:initial}
                        .win-top h5:before{content:none}
                        .win-top h5{font-size:3rem;color:var(--preset-e)}
                        .win-top h5 span:before{content:'';display:inherit;width:88%;height:36%;background-color:var(--theme-color);position:absolute;left:15px;bottom:1px;z-index:-1}
                        .win-top h5 span{position:relative;background:inherit;color:white;font-weight:bolder;max-width: 10em;overflow: hidden;text-overflow: ellipsis;display: inline-block;vertical-align:middle}
                        .win-top h5 b{font-family:var(--font-ms);font-weight:bolder;color:var(--preset-f);/*padding:0 10px;vertical-align:text-top;*/}
                        .win-content article{max-width:88%;margin-top:auto}
                        .win-content article.news-window{padding:0;margin-bottom:25px;/*border:1px solid rgb(100 100 100 / 10%);*/}
                        .win-content article .info span{margin-left:10px}
                        .win-content article .info span#slider{margin:auto}
                	    .news-window-img{max-width:15%}
                        .news-window-img a{
                            width: 100%;
                            height: 100%;
                        }
                        .news-window-img img{
                            object-fit: cover;
                            min-height: 123px;
                        }
                	    .rcmd-boxes{width:19%;display:inline-block;vertical-align:middle}
                	    .empty_card h1{max-width: 88%;overflow: hidden;text-overflow: ellipsis;display: block;margin: 25px auto;}
                	    .rcmd-boxes .info .inbox{max-width:none;margin: 5px}
                	    .main h2{font-weight: 600;font-size:1.25rem};
                        #core-info p{padding:0}
                        @media screen and (max-width:760px){
                            .win-content article{
                                width: 100%;
                            }
                            .rcmd-boxes{width:49%!important}
                        }
                        .main h2{margin-bottom: 0}
                	</style>
            <?php
                }
                while (have_posts()): the_post();
                    $postimg = get_postimg(0,$post->ID,true);
                    $lazyhold = "";
                    if($lazysrc!='src'){
                        $lazyhold = 'data-src="'.$postimg.'"';
                        $postimg = $loadimg;
                    }
                    $post_feeling = get_post_meta($post->ID, "post_feeling", true);
                    $post_orderby = get_post_meta($post->ID, "post_orderby", true);
                    $post_rights = get_post_meta($post->ID, "post_rights", true);
                    $notes_slug = get_cat_by_template('notes','slug');
                    $news_slug = get_cat_by_template('news','slug');
                    $weblog_slug = get_cat_by_template('weblog','slug');
                    $acg_slug = get_cat_by_template('acg','slug');
                    if(!$post_styles){
        ?>
                        <article class="<?php if($post_orderby>1) echo 'topset'; ?> cat-<?php echo $post->ID ?>">
                            <h1>
                                <a href="<?php the_permalink() ?>" target="_blank"><?php the_title() ?></a>
                                <?php if($post_rights&&$post_rights!="原创") echo '<sup>'.get_post_meta($post->ID, "post_rights", true).'</sup>'; ?>
                            </h1>
                            <p><?php custom_excerpt(150); ?></p>
                            <div class="info">
                                <span class="classify" id="">
                                    <i class="icom"></i>
                                    <?php 
                                        $cats = get_the_category();
                                        foreach ($cats as $cat){
                                            if($cat->slug!=$notes_slug) echo '<em>'.$cat->name.'</em> ';  //leave a blank at the end of em
                                        }
                                    ?>
                                </span>
                                <span class="valine-comment-count icom" data-xid="<?php echo parse_url(get_the_permalink(), PHP_URL_PATH) ?>"> <?php echo $post->comment_count; ?></span>
                                <span class="date"><?php the_time("d-m-Y"); ?></span>
                                <span id="slider"></span>
                            </div>
                        </article>
            <?php
                    }else{
                        if(in_category($news_slug)){
            ?>
                            <article class="<?php if($post_orderby>1) echo 'topset icom'; ?> news-window wow" data-wow-delay="0.1s" post-orderby="<?php echo $post_orderby; ?>">
                                <div class="news-window-inside">
                                    <?php
                                        if(has_post_thumbnail() || get_option('site_default_postimg_switcher')) echo '<span class="news-window-img"><a href="'.get_the_permalink().'"><img class="lazy" '.$lazyhold.' src="'.$postimg.'" /></a></span>';
                                    ?>
                                    <div class="news-inside-content">
                                        <h2 class="entry-title">
                                            <a href="<?php the_permalink() ?>" title="<?php the_title() ?>"><?php the_title() ?></a>
                                        </h2>
                                        <span class="news-core_area entry-content"><p><?php custom_excerpt(); ?></p></span>
                                        <span class="news-personal_stand" unselectable="on">
                                            <dd><?php echo $post_feeling ? $post_feeling : '...'; ?></dd>
                                        </span>
                                        <div id="news-tail_info">
                                            <ul class="post-info">
                                                <li class="tags author"><?php echo get_tag_list($post->ID); ?></li>
                                                <li title="讨论人数">
                                                    <?php 
                                                        $count = get_option('site_third_comments') ? 0 : $post->comment_count;
                                                        echo '<span class="valine-comment-count icom" data-xid="'.parse_url(get_the_permalink(), PHP_URL_PATH).'">'.$count.'</span>';
                                                    ?>
                                                </li>
                                                <li id="post-date" class="updated" title="发布日期">
                                                    <i class="icom"></i><?php the_time('d-m-Y'); ?>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </article>
            <?php
                        }elseif(in_category($weblog_slug)){
            ?>
                            <article class="weblog-tree-core-record i<?php the_ID() ?>">
                                <div class="weblog-tree-core-l">
                                    <span id="weblog-timeline">
                                        <?php 
                                            echo $rich_date = get_the_tag_list() ? get_the_time('Y年n月j日').' - ' : get_the_time('Y年n月j日');
                                            echo get_tag_list($post->ID,2,'');
                                        ?>
                                    </span>
                                    <span id="weblog-circle"></span>
                                </div>
                                <div class="weblog-tree-core-r">
                                    <div class="weblog-tree-box">
                                        <div class="tree-box-title">
                                            <a href="<?php //the_permalink() ?>" id="<?php the_title(); ?>" target="_self">
                                                <h3><?php the_title() ?></h3>
                                            </a>
                                        </div>
                                        <div class="tree-box-content">
                                            <span id="core-info">
                                                <?php 
                                                    // echo get_the_content();//custom_excerpt(200); 
                                                    echo apply_filters('the_content', get_the_content());
                                                ?>
                                            </span>
                                            <?php
                                                $ps = get_post_meta($post->ID, "post_feeling", true);
                                                if($ps) echo '<span id="other-info"><h4> Ps. </h4><p class="feeling">'.$ps.'</p></span>';
                                            ?>
                                            <p id="sub"><?php echo $rich_date;echo get_tag_list($post->ID,2,''); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </article>
            <?php  
                        }elseif(in_category($acg_slug)){
            ?>
                            <div class="rcmd-boxes flexboxes">
                                <div class="info anime flexboxes">
                                    <div class="inbox flexboxes">
                                        <div class="inbox-headside flexboxes">
                                            <span class="author"><?php echo $post_feeling = get_post_meta($post->ID, "post_feeling", true); ?></span>
                                            <?php
                                                echo '<img '.$lazyhold.' src="'.$postimg.'" alt="'.$post_feeling.'" crossorigin="Anonymous">'; //<img class="bg" '.$lazyhold.' src="'.$postimg.'" alt="'.$post_feeling.'">
                                            ?>
                                        </div>
                                        <div class="inbox-aside">
                                            <span class="lowside-title">
                                                <h4><a href="<?php the_permalink(); ?>" target="_blank"><?php the_title(); ?></a></h4>
                                            </span>
                                            <span class="lowside-description">
                                                <p><?php custom_excerpt(66); ?></p>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
            <?php
                        }else{
                            // results doen't match in_category template, like pages..
            ?>
                            <article class="<?php if($post_orderby>1) echo 'topset'; ?> cat-<?php echo $post->ID ?>">
                                <h1>
                                    <a href="<?php the_permalink() ?>" target="_blank"><?php the_title() ?></a>
                                    <?php if($post_rights&&$post_rights!="原创") echo '<sup>'.get_post_meta($post->ID, "post_rights", true).'</sup>'; ?>
                                </h1>
                                <p><?php custom_excerpt(150); ?></p>
                                <div class="info">
                                    <span class="classify" id="">
                                        <i class="icom"></i>
                                        <?php 
                                            $cats = get_the_category();
                                            foreach ($cats as $cat){
                                                if($cat->slug!=$notes_slug) echo '<em>'.$cat->name.'</em> ';  //leave a blank at the end of em
                                            }
                                        ?>
                                    </span>
                                    <span class="valine-comment-count icom" data-xid="<?php echo parse_url(get_the_permalink(), PHP_URL_PATH) ?>"> <?php echo $post->comment_count; ?></span>
                                    <span class="date"><?php the_time("d-m-Y"); ?></span>
                                    <span id="slider"></span>
                                </div>
                            </article>
            <?php
                        }
                    }
                endwhile;
                wp_reset_query();  // 重置 wp 查询（每次查询后都需重置，否则将影响后续代码查询逻辑）
                $current_page = max(1, get_query_var('paged'));  // update $current_page inside the loop
                // print_r($current_page.' / '.$maximun_page);
                // if($current_page > $maximun_page) return;
                $pages = paginate_links(array(
                    'prev_text' => __('上一页'),
                    'next_text' => __('下一页'),
                    'type' => 'plaintext',
                    'screen_reader_text' => null,
                    'total' => $maximun_page,  //总页数
                    'current' => $current_page, //当前页数
                ));
                if($pages) echo '<div class="pageSwitcher">'.$pages.'</div>';
                // unset($post, $lazysrc, $loadimg, $wp_query);
            }else{
                echo '<div class="empty_card"><i class="icomoon icom icon-'.current_slug().'" data-t=" EMPTY "></i><h1> '.$queryString.' </h1></div>';  //<b>'.current_slug(true).'</b> 
            }
        }
    }
    
    
    
    /*
     *---------------------------------------------------------------------------------------------------------------------------------
     * theme_setup
     *---------------------------------------------------------------------------------------------------------------------------------
    */
    
    // 日志记录函数
    function report_logs($message, $file = 'log.txt') {
        $log_file = WP_CONTENT_DIR . '/uploads/' . $file; // 确保 uploads 目录存在且可写
        if (!file_exists($log_file)) {
            touch($log_file); // 如果文件不存在，则创建文件
        }
        date_default_timezone_set('Asia/Shanghai');
        $time = date('Y-m-d H:i:s'); // 获取当前时间
        $log_message = "[$time] $message\n"; // 格式化日志信息
        file_put_contents($log_file, $log_message, FILE_APPEND); // 将日志信息追加到文件
    }
    
    /*--------------------------------------------------------------------------
     * 页面缓存刷新
     *--------------------------------------------------------------------------
    */
    if(get_option('site_cache_switcher')) {
        //清除（重建）更新链接
        function site_update_link_cache($link_id) {
            //清除（重建）友情链接
            update_option('site_link_list_cache', '');
            
            // 更新（指定所有包含分类） rss 订阅
            $link_category = wp_get_link_cats($link_id);
            foreach ($link_category as $category) {
                $each_category = get_term_field('slug', $category, 'link_category', 'raw');
                update_option('site_rss_' . $each_category . '_cache', '');  // 清除（所有分类）聚合内容
            }
        }
        // add_action('wp_insert_link', 'site_update_link_cache');
        // add_action('wp_update_link', 'site_update_link_cache');
        // add_action('wp_delete_link', 'site_update_link_cache');
        add_action('add_link', 'site_update_link_cache');
        add_action('edit_link', 'site_update_link_cache');
        add_action('delete_link', 'site_update_link_cache');
        
        //清除（重建）指定分类
        function update_category_post_cache($post, $temp_slug, $page_cache) {
            $temp_info = get_cat_by_template($temp_slug);
            if(!$cat) {
                global $cat;
            }
            $cid = get_the_category($post->ID)[0]->term_id;
            // print_r($cid);
            $cat = $cat ? $cat : $cid; //get_the_category($pid)->term_id; // $categories = wp_get_post_categories($pid);
            if(in_category($temp_info->slug, $post) || cat_is_ancestor_of($cat, $temp_info->term_id)){ //in_array($temp_info->term_id, $categories)
                update_option($page_cache, '');
            }
        }
        function site_update_specific_caches($post_id) {
            global $cat;
            $post = get_post($post_id);
            if($post->post_type != 'post') return;  // update post only(no inform)
            
            // 清除（当前）归档数据
            update_option('site_archive_count_cache', '');
            update_option('site_archive_contributions_cache', '');
            // update_option('site_archive_list_cache', '');
            
            $post_year = date('Y', strtotime($post->post_date));
            $archive_years = get_option('site_archive_years_cache');
            if ($archive_years) {
                $archive_years = json_decode($archive_years);
                if (in_array($post_year, $archive_years)) {
                    update_option('site_archive_' . $post_year . '_cache', '');
                    // update_option('site_archive_years_cache', '');
                }
            }
            
            // 清除对应文章分类缓存
            $caches = get_option('site_cache_includes');
            if ($caches) {
                $temp_array = array(get_cat_by_template('news'), get_cat_by_template('notes'), get_cat_by_template('weblog'), get_cat_by_template('acg'), get_cat_by_template('download'));
                $output_sw = false;
                foreach ($temp_array as $temp) {
                    if (empty($temp)) continue;
                    $temp_slug = $temp->slug;
                    $cache = 'site_recent_'.$temp_slug.'_cache';
                    $output_sw = in_array($temp_slug, explode(',', $caches));
                    if($output_sw) {
                        // 清除（重建）更新通用缓存
                        update_category_post_cache($post_id, $temp_slug, $cache);
                        // 清除（重建）更新指定缓存
                        if ($temp_slug==='acg') {
                            update_category_post_cache($post, $temp_slug, 'site_acg_stats_cache');
                            update_category_post_cache($post, $temp_slug, 'site_acg_post_cache');
                        }
                        if ($temp_slug==='download') {
                            update_category_post_cache($post, $temp_slug, 'site_download_list_cache');
                        }
                    }
                }
            }
        }
        add_action('save_post', 'site_update_specific_caches');
        add_action('delete_post', 'site_update_specific_caches');
        
        
        /*****   wp_schedule_event 定时任务   *****/
        
        add_filter( 'cron_schedules', 'custom_add_cron_interval' );
        function custom_add_cron_interval( $schedules ) {
            $update_hours = get_option('site_rss_update_interval', 12);
            $schedules[$update_hours . 'hours'] = array(
                'interval' => $update_hours * HOUR_IN_SECONDS, //600
                'display'  => esc_html__( "Every $update_hours Hours" ), );
            return $schedules;
        }
        
        // 刷新定时任务AJAX动作
        add_action('wp_ajax_update_cronjobs', 'update_all_cronjobs');
        // _ajax_nonce 校验
        add_action('wp_ajax_nopriv_update_cronjobs', 'update_all_cronjobs');
        function update_all_cronjobs() {
            // 安全检查
            check_ajax_referer('update_cronjobs', 'nonce');
            // 取消定时任务
            wp_clear_scheduled_hook('scheduled_rss_feeds_updates_hook');
            // 调用安排定时任务的函数
            $param_interval = isset($_GET['interval']) ? $_GET['interval'] : $_POST['interval'];
            schedule_all_cronjob($param_interval);
            // 返回一个响应
            wp_send_json_success('200');
        }

        add_action('wp', 'schedule_all_cronjob');
        function schedule_all_cronjob($param_interval = false) {
            if (!is_numeric($param_interval) || $param_interval <= 0) {
                $param_interval = get_option('site_rss_update_interval', 12); // 默认值
            }
            if(!wp_next_scheduled('db_caches_cronjob_hook')){
                // 设定定时作业执行时间（东八区时间）
                $timestamp = strtotime('today 06:00 Asia/Shanghai'); // 设置每天上午执行一次定时作业
                wp_schedule_event($timestamp, 'daily', 'db_caches_cronjob_hook'); 
            }
            // 检查是否已经安排了事件，避免重复安排
            if ( ! wp_next_scheduled( 'scheduled_rss_feeds_updates_hook' ) ) {
                date_default_timezone_set('Asia/Shanghai');
                // 当前时间的时间戳
                $timestamp = time(); //current_time( 'timestamp' ); //
                $hours_interval = $param_interval . 'hours';
                // 安排事件，每隔$interval小时执行一次 使用 @ 来抑制错误输出
                // @wp_schedule_event( $timestamp, $hours_interval, 'scheduled_rss_feeds_updates_hook' );
                try {
                    wp_schedule_event( $timestamp, $hours_interval, 'scheduled_rss_feeds_updates_hook' );
                } catch (Exception $e) {
                    report_logs("\n\n".$e->getMessage()."\n\n");  // 记录错误信息到错误日志
                }
            }
        }
        
        //定时清除（重建）缓存
        add_action('db_caches_cronjob_hook', 'site_clear_timeout_caches'); //定时更新 db caches
        function site_clear_timeout_caches() {
            // 定时清除（重建）ACG 缓存
            update_option('site_acg_stats_cache', '');
            update_option('site_rank_list_cache', '');
            // 触发更新
            report_logs("（定时任务）开始更新 ACG 数据..."); // 记录日志
            $response = wp_remote_get(get_category_link(get_cat_by_template('acg', 'term_id')));
            if (!is_wp_error($response)) {
                report_logs("（定时任务）ACG 已更新。\n"); // $body = wp_remote_retrieve_body($response);
            } else {
                report_logs('（定时任务）ACG 更新失败：' . $response->get_error_message() . '）'); // 记录错误日志
            }
            
            // 清除（重建）归档数据
            update_option('site_archive_count_cache', '');
            update_option('site_archive_contributions_cache', ''); //解决bug：切换全年报表后无法判断db数据库中是否已存在全年记录
            $archive_years = json_decode(get_option('site_archive_years_cache'));
            foreach ($archive_years as $archive_year) {
                update_option('site_archive_' . $archive_year . '_cache', '');
            }
            update_option('site_archive_years_cache', '');
            // 触发更新
            report_logs("（定时任务）开始更新归档数据..."); // 记录日志
            $response = wp_remote_get(get_category_link(get_cat_by_template('archive', 'term_id')));
            if (!is_wp_error($response)) {
                report_logs("（定时任务）归档已更新。\n"); // $body = wp_remote_retrieve_body($response);
            } else {
                report_logs('（定时任务）归档更新失败：' . $response->get_error_message() . '）'); // 记录错误日志
            }
        }
        
        // 定时事件安排
        add_action( 'scheduled_rss_feeds_updates_hook', 'scheduled_rss_feeds_updates' );
        // 触发 api 更新（全部） rss 订阅
        function scheduled_rss_feeds_updates() {
            report_logs("（定时任务）开始更新 RSS 缓存....................."); // 记录日志
            date_default_timezone_set('Asia/Shanghai');
            $links_slug = get_links_category('slug');
            foreach ($links_slug as $link_slug) {
                report_logs('（定时任务）正在更新 ' . $link_slug . '..'); // 记录日志
                // update_option('site_rss_' . $link_slug . '_cache', $link_slug);  // 清除（重建）所有聚合内容
                $api_url = get_plugin_refrence('rss', true) . "cat=$link_slug&limit=3&update=1&output=0&clear=0";  // 注：服务端请求无法使用cdn，客户端可用 get_api_refrence
                // 触发 API（更新）聚合内容
                $ch = curl_init($api_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 返回响应，而不是直接输出
                curl_setopt($ch, CURLOPT_HEADER, false); // 不需要返回响应头
                $res = curl_exec($ch);
                if (curl_errno($ch)) {
                    // 触发 curl 重试（一次）
                    report_logs('（定时任务）重试更新：' . curl_error($ch)); // 记录错误日志
                    $response = wp_remote_get($api_url);
                    if (is_wp_error($response)) {
                        report_logs('（定时任务）重试更新 ' . $link_slug . ' 失败！（' . $response->get_error_message() . '）'); // 记录错误日志
                        continue;
                    }
                    $body = wp_remote_retrieve_body($response);
                    report_logs('（定时任务）' . $link_slug . ' 已（重试）更新于：' . date("Y-m-d H:i:s")); // json_decode($body)[0]->lastUpdate
                }
                curl_close($ch);
                if ($res) report_logs('（定时任务）' . $link_slug . ' 已更新于：' . date("Y-m-d H:i:s")); // json_decode($res)[0]->lastUpdate
            }
            report_logs("（定时任务）所有 RSS 缓存已更新.....................\n\n\n"); // 记录日志
            wp_cache_flush(); // bug: to clear wp_options caches
        }
    }
    
    
    /*
     *--------------------------------------------------------------------------
     * 全局初始化操作
     *--------------------------------------------------------------------------
    */
    
    // 初始化 wordpress 执行函数
    function custom_theme_setup(){
        $expire = time() + 1209600;  // 自定义 cookie 函数 darkmode cookie set
        $theme_manual = array_key_exists('theme_manual',$_COOKIE) ? $_COOKIE['theme_manual'] : false;
        if(!isset($theme_manual)){  //auto set manual 0 (reactive with javascript manually)
            setcookie('theme_manual', 0, $expire, COOKIEPATH, COOKIE_DOMAIN, false);
        };
        if(!$theme_manual){  //if theme_manual actived
            if(get_option('timezone_string')!='Asia/Shanghai') {
                update_option('timezone_string', 'Asia/Shanghai'); // update local timezone for 24 hours offset fixes
            }
            $hour = current_time('G');
            $start = get_option('site_darkmode_start');
            $end = get_option('site_darkmode_end');
            $hour>=$end&&$hour<$start || $hour==$end&&current_time('i')>=0&&current_time('s')>=0 ? setcookie('theme_mode', 'light', $expire, COOKIEPATH, COOKIE_DOMAIN, false) : setcookie('theme_mode', 'dark', $expire, COOKIEPATH, COOKIE_DOMAIN, false);
        };
        // ARTICLE FULL-VIEW SET
        // if(!isset($_COOKIE['article_fullview'])){
        //     setcookie('article_fullview', 0, $expire, COOKIEPATH, COOKIE_DOMAIN, false);
        // };
        // ARTICLE FONT-PLUS SET
        if(!isset($_COOKIE['article_fontsize'])){
            setcookie('article_fontsize', 0, $expire, COOKIEPATH, COOKIE_DOMAIN, false);
        };
        
        // SETUP sidebar FULL-VIEW status(default 1 enabled)
        if(!isset($_COOKIE['sidebar_status'])){
            setcookie('sidebar_status', 1, $expire, COOKIEPATH, COOKIE_DOMAIN, false);
        };
        $sidebar_status = array_key_exists('sidebar_status',$_COOKIE) ? $_COOKIE['sidebar_status'] : false;
        if(!get_option('site_ads_switcher')&&!get_option('site_countdown_switcher')&&!get_option('site_pixiv_switcher')&&!get_option('site_mostview_switcher')){
            setcookie('sidebar_status', 0, $expire, COOKIEPATH, COOKIE_DOMAIN, false);
        }else{
            setcookie('sidebar_status', 1, $expire, COOKIEPATH, COOKIE_DOMAIN, false);
        }
    };
    add_action('after_setup_theme', 'custom_theme_setup');
    
    /*
     *--------------------------------------------------------------------------
     * 主题通用功能控制
     *--------------------------------------------------------------------------
    */
    // 自动主题模式
    function theme_mode(){
        if(get_option('site_darkmode_switcher')){
            if(!array_key_exists('sidebar_status',$_COOKIE)){
                if(!$theme_manual){  //if theme_manual actived
                    $hour = current_time('G');
                    $start = get_option('site_darkmode_start');
                    $end = get_option('site_darkmode_end');
                    echo $hour>=$end&&$hour<$start || $hour==$end&&current_time('i')>=0&&current_time('s')>=0 ? 'light' : 'dark';
                };
            }else{
                echo $_COOKIE['theme_mode'];
            }
        }
    }
    //lazyload 图片<img>懒加载
    if(get_option('site_lazyload_switcher')){
        $lazysrc = 'data-src';
        add_filter('the_content', 'lazyload_images', 10);  // 设置 priority 低于 custom_cdn_src
        function lazyload_images($content){
            return preg_replace('/\<img(.*)src=("[^"]*")/i', '<img $1 data-src=$2', $content);
        }
        // replace comments images url
        add_filter('comment_text' , 'lazyload_images', 20, 2);
    }
    // 站点logo
    function site_logo($dark=false){
        if(get_option('site_logo_switcher')){
            // echo $_COOKIE['theme_mode']=='dark' ? '<span style="background: url('.get_option('site_logos').') no-repeat center center /cover;"></span>' : '<span style="background: url('.get_option('site_logo').') no-repeat center center /cover;"></span>';
            $logo = $dark ? get_option('site_logos') : get_option('site_logo');
            echo '<span style="background: url('.$logo.') no-repeat center center /cover;"></span>';
        }else{
            echo '<span>'.get_bloginfo('name').'</span>';
        }
    }
    // 站点公告
    function get_inform(){
        if(get_option('site_inform_switcher')){
            $inform_max = get_option('site_inform_num');
            echo '<div class="scroll-inform"><p><b>近期公告&nbsp;</b><i class="icom inform"></i>:&nbsp;</p><div class="scroll-block" id="informBox">';
            if(get_option('site_leancloud_switcher')){ //strpos(get_option('site_leancloud_category'), 'site_leancloud_inform')!==false
                $leancloud_arr = explode(',', get_option('site_leancloud_category'));
                if(in_array('site_leancloud_inform', $leancloud_arr)){
    ?>
                    <script type="text/javascript">  //addAscending("createdAt")
                        new AV.Query("inform").addDescending("createdAt").limit(<?php echo $inform_max; ?>).find().then(result=>{
                            for (let i=0,resLen=result.length,infobox=document.querySelector("#informBox"); i<resLen;i++) {
                                infobox.innerHTML += `<span>${result[i].attributes.title}</span>`;
                            }
                            const informs = document.querySelectorAll('.scroll-inform div.scroll-block span');
                            informs[0].classList.add("showes");  //init first show(no trans)
                            if(informs.length>1){
                                const cls_move = "move",
                                      cls_show = "show";
                                (function(els,count,delay){
                                    setInterval(() => {
                                        declear(els, cls_move, count)
                                        els[count].className = cls_move;  //current
                                        els[count+1] ? els[count+1].classList.add(cls_show) : els[0].classList.add(cls_show);
                                        count<els.length-1 ? count++ : count=0;
                                    }, delay);
                                })(informs, 0, 3000);
                            }
                        });
                    </script>
    <?php
                }
            }else{
                // $cid = get_option('site_inform_cid');
                query_posts(array(
                    'post_type' => 'inform',
                    // 'meta_key' => 'post_orderby',
                    'orderby' => array(
                        // 'meta_value_num' => 'DESC',
                        'date' => 'DESC',
                        // 'modified' => 'DESC'
                    ),
                    'posts_per_page' => $inform_max,  //use left_query counts
                    'post_status' => 'publish'  //, draft
                ));
                while(have_posts()) : the_post();
                    echo '<span>'.get_the_title().'</span>';
                endwhile; 
                wp_reset_query();  // 重置 wp 查询
            }
            echo '</div></div>';
        }
    }
    //面包屑导航（site_breadcrumb_switcher开启并传参true时启用）
    function breadcrumb_switch($switch=false, $frame=false){
        if(get_option('site_breadcrumb_switcher')&&$switch){
            if($frame){
                echo '<div class="news-cur-position wow fadeInUp"><ul>';
                    echo(the_breadcrumb());
                echo '</ul></div>';
            }else echo(the_breadcrumb());
        }
    };
    // 面包屑导航 https://www.thatweblook.co.uk/tutorial-wordpress-breadcrumb-function/
    if(get_option('site_breadcrumb_switcher')){
        function the_breadcrumb() {
            $sep = ' » ';
            if (!is_front_page()) {
                echo '<div class="breadcrumbs">';
                echo '<a href="';
                echo get_option('home');
                echo '">';
                bloginfo('name');
                echo '</a>' . $sep;
                if (is_category() || is_single() ){
                    the_category('title_li=');
                } elseif (is_archive() || is_single()){
                    if ( is_day() ) {
                        printf( __( '%s', 'text_domain' ), get_the_date() );
                    } elseif ( is_month() ) {
                        printf( __( '%s', 'text_domain' ), get_the_date( _x( 'F Y', 'monthly archives date format', 'text_domain' ) ) );
                    } elseif ( is_year() ) {
                        printf( __( '%s', 'text_domain' ), get_the_date( _x( 'Y', 'yearly archives date format', 'text_domain' ) ) );
                    } else {
                        _e( 'Blog Archives', 'text_domain' );
                    }
                }
                if (is_single()) {
                    echo $sep;
                    the_title();
                }
                if (is_page()) {
                    echo the_title();
                }
                if (is_home()){
                    $page_for_posts_id = get_option('page_for_posts');
                    if ( $page_for_posts_id ) { 
                        global $post;
                        $post = get_page($page_for_posts_id);
                        setup_postdata($post);
                        // unset($post);
                        the_title();
                        rewind_posts();
                    }
                }
                echo '</div>';
            }
        };
    }
    // 文章 TOC 目录 https://www.ludou.org/wordpress-content-index-plugin.html/comment-page-3#comment-16566
    function article_index($content) {
        if(is_single() && preg_match_all('/<h([2-6]).*?\>(.*?)<\/h[2-6]>/is', $content, $matches) && get_option('site_indexes_switcher')) {
            $match_h = $matches[1];
            $match_m = count($match_h);
            $ul_li = '';
            for($i=0;$i<$match_m;$i++){
                $value = $match_h[$i];
                $title = trim(strip_tags($matches[2][$i]));
                $content = str_replace($matches[0][$i], '<a href="javascript:;" id="title-'.$i.'" class="index_anchor" aria-label="anchor"></a><h'.$value.' id="title_'.$i.'">'.$title.'</h'.$value.'>', $content);
                $value = $match_h[$i];
                $pre_val = array_key_exists($i-1,$match_h) ? $match_h[$i-1] : 9;
                $ul_li .= $value>$pre_val || $value>=3 ? '<li class="child" id="t'.$i.'"><a href="#title-'.$i.'" title="'.$title.'">'.$title.'</a></li>' : '<li id="t'.$i.'"><a href="#title-'.$i.'" title="'.$title.'">'.$title.'</a></li>';
            }
            $article_index = array_key_exists('article_index',$_COOKIE) ? $_COOKIE['article_index'] : false;
            $auto_fold = !$article_index ? 'fold' : '';
            $index_array = explode(',', get_option('site_indexes_includes'));
            $index_array_count = count($index_array);
            for($i=0;$i<$index_array_count;$i++){
                $each_index = trim($index_array[$i]);
                if($each_index){
                    if(in_category($each_index)){
                        $content = '<div class="article_index '.$auto_fold.'" data-index="'.$match_m.'"><div class="in_dex"><p title="折叠/展开"><b>文章目录</b><i class="icom"></i></p><ul>' . $ul_li . '</ul></div></div>' . $content;
                    }
                }
            }
        }
        return $content;
    }
    add_filter( 'the_content', 'article_index');
    // 指定分类文章启用 chatgpt
    function in_chatgpt_cat($post=null){
        $chatgpt_cat = false;
        if(get_option('site_chatgpt_switcher')){  //&&is_single() //canceled for api calling
            if(!$post) global $post;  // global $post;
            $chatgpt_array = explode(',', get_option('site_chatgpt_includes'));
            $chatgpt_array_count = count($chatgpt_array);
            if($chatgpt_array_count>=1){
                for($i=0;$i<$chatgpt_array_count;$i++){
                    if(in_category($chatgpt_array[$i], $post)) {
                        $chatgpt_cat = true;
                    }
                }
            }
        }
        return $chatgpt_cat;
    }
    // 挂载文章 chatGPT AI 摘要 mount article chatgpt
    function article_ai_abstract($content) {
        global $src_cdn; //custom_cdn_src(0, true)
        $chatgpt_cat = in_chatgpt_cat();
        return $chatgpt_cat&&is_single() ? '<blockquote class="chatGPT" status="'.$chatgpt_cat.'"><p><b>文章摘要</b><span title="对话模型">' . get_option('site_chatgpt_model', 'OPENAI') . '</span></p><p class="response load">Standby API Responsing..</p></blockquote><script type="module">const responser = document.querySelector(".chatGPT .response");try {import("'.$src_cdn.'/js/module.js").then((module)=>send_ajax_request("get", "'.get_api_refrence("gpt").'", false, (res)=>{let _json=JSON.parse(res),_string="No response inbound.";if(_json.choices){_string=_json.choices[0].message.content;}else if(_json.text){_string=_json.text;}else{_string=_json.error.message;}module.words_typer(responser, _string, 25);console.log(_json.error)}));}catch(e){console.warn("dom responser not found, check backend.",e)}</script>'.$content : $content; //get_api_refrence("gpt", true)
    }
    add_filter( 'the_content', 'article_ai_abstract', 10);
    
    /*
     *--------------------------------------------------------------------------
     * WP Comment email/wechat notify, ajax/pagination etc
     *--------------------------------------------------------------------------
    */
    
    // 双数据页面类型（分类、页面）切换评论
    function dual_data_comments(){
        if(!is_category()){
            comments_template();
            return;
        }
        if(get_option('site_third_comments')=='Wordpress'){
            echo '<div class="main"><span><h2> 评论留言 </h2></span><p>分类页面无法调用 WP 评论，<b> 开启移除 CATEGORY 后 </b>请前往页面指定当前页面父级，<small>亦可前往后台启用第三方评论。</small></p></div>';
            return;
        }
        load_theme_partial('/comments.php');
    }
    
    // 评论企业微信应用通知
    if(get_option('site_wpwx_notify_switcher') && get_option('site_third_comments')=='Wordpress'){  //微信推送消息
        function push_weixin($comment_id){
            global $src_cdn;
            $comment = get_comment($comment_id);
            $post_id = $comment->comment_post_ID;
            $admin_mail = get_bloginfo('admin_email'); //get_option('site_smtp_mail', get_bloginfo('admin_email'));
            $comment_mail = $comment->comment_author_email;
            $comment_author = $comment->comment_author;
            $comment_title = '《' . get_the_title($post_id) . '》 上有新评论啦~';
            $comment_content = strip_tags($comment->comment_content);
            // 一个 POST 请求
            $options = array(
                'http' => array(
                    'method' => 'POST',
                    'header' => 'Content-type: application/x-www-form-urlencoded',
                    'content' => http_build_query(
                        array(
                            'name' => $comment_author,
                            'mail' => $comment_mail,  // 'avatar' => match_mail_avatar($comment_mail),
                            'title' => $comment_title,
                            'content' => $comment_content,
                            // 'description' => $comment_author.' 在 '.$comment_title.' 上回复道: '.$comment_content,
                            'image' => get_postimg(0, $post_id, true),
                            'url' => urlencode(get_bloginfo('url')."/?p=$post_id#comments"),
                        )
                    )
                )
            );
            // 评论邮件不为博主邮件时，因 wpwx-notify.php 内部需调用 wp core，故不可使用 cdn('src'/'api') 相对路径
            if($comment_mail!=$admin_mail) return file_get_contents(custom_cdn_src(0, 1) . '/plugin/wpwx-notify.php',false,stream_context_create($options));else return false; // $src_cdn custom_cdn_src('api', true)
        }
        // 挂载 WordPress 评论提交的接口
        add_action('comment_post', 'push_weixin', 10, 2);
    }
    
    //*****  WordPress AJAX Comments Setup etc (comment reply/paginate)  *****//
    
    // AJAX 回复评论
    if(get_option('site_ajax_comment_switcher')){
        // Loop-back child-comments (recursive)
        function wp_child_comments_loop($cur_comment){
            $child_comment = $cur_comment->get_children(array(
                'hierarchical' => 'threaded',
                // 'status'       => 'approve',
                'order'        => 'ASC',
                // 'orderby'=>'order_clause',
                // 'meta_query'=>array(
                //   'order_clause' => 'comment_parent'
                // )
            ));
            if(count($child_comment)<=0) return;
            foreach ($child_comment as $child) {
                wp_comments_template($child);
                wp_child_comments_loop($child);
            }
        }
        // Direct comments output
        function wp_comments_template($comment){
            global $lazysrc, $post;
            $id = $comment->comment_ID;
            $nick = $comment->comment_author;
            $link = $comment->comment_author_url;
            $email = $comment->comment_author_email;
            $userAgent = get_userAgent_info($comment->comment_agent);
            $approved = $comment->comment_approved;
            $content = $comment->comment_content; //esc_html();// //strip_tags(); XSS Secure Issues!!!
            $parent = $comment->comment_parent;
            if($approved=='0') $content = '<small style="opacity:.5">[ 评论未审核，通过后显示 ]</small>';
            if($parent>0) $content = '<a href="#comment-'.$parent.'">@'. get_comment_author($parent) . '</a> , ' . $content;
    ?>
            <div class="wp_comments" id="comment-<?php echo $id; ?>">
                <div class="vh" rootid="<?php echo $id; ?>">
                    <div class="vhead">
                        <a rel="nofollow" href="<?php echo $link; ?>" target="_blank">
                            <?php if(get_option('show_avatars')) echo '<img class="avatar" '.$lazysrc.'="'.match_mail_avatar($email).'" width=50 height=50 />'; ?>
                        </a>
                    </div>
                    <div class="vcontent">
                        <div class="vinfo">
                            <a rel="nofollow" href="<?php echo $link; ?>" target="_blank"><?php echo $nick; ?></a>
                            <?php
                                if($email==get_option('site_smtp_mail', get_bloginfo('admin_email'))) echo '<span class="admin">admin</span>';
                                echo '<span class="useragent">'.$userAgent['browser'].' / '.$userAgent['system'].'</span>';
                                if($approved=="0") echo '<span class="auditing">待审核</span>';
                            ?>
                            <div class="vtime"><?php echo date('Y-m-d', strtotime($comment->comment_date)); ?></div>
                            <?php 
                                if($approved){
                                    // global $wp;
                                    // $current_url = home_url( add_query_arg( array(), $wp->request ) );
                                    $locate = 'comment-'.$id;
                                    echo '<a rel="nofollow" class="comment-reply-link" href="javascript:void(0);" data-commentid="'.$id.'" data-postid="'.$post->ID.'" data-belowelement="'.$locate.'" data-respondelement="respond" data-replyto="'.$nick.'" aria-label="正在回复给：@'.$nick.'">回复</a>'; //'.$current_url.'?replytocom='.$id.'#respond  //'.$locate.'
                                    // unset($wp);
                                }
                            ?>
                        </div>
                        <?php echo '<p>'.$content.'</p>'; ?>
                    </div>
                </div>
            </div>
    <?php
            // unset($lazysrc,  $post);
        }
    }
    
    // AJAX 加载评论
    if(get_option('site_ajax_comment_paginate')){
        // Childs comment Loop-load method (recursive)
        function ajax_child_comments_loop($cur_comment){
            $child_comment = $cur_comment->get_children(array(
                'hierarchical' => 'threaded',
                'order'        => 'ASC',
                // 'orderby'=>'order_clause',
                // 'meta_query'=>array(
                //   'order_clause' => 'comment_parent'
                // )
            ));
            if(count($child_comment)>=1){
                // $child_comment = json_decode(json_encode($child_comment), true); // Objects to Array object
                foreach ($child_comment as $child) {
                    if($child->comment_approved=='0') $child->comment_content = '评论未审核，通过后显示';
                    // use privacy data encryption
                    $child->comment_author_IP = sha1($child->comment_author_IP);
                    $child->comment_author_email = md5($child->comment_author_email);
                    // add Objects for frontend calls
                    $child->_comment_reply = get_comment_author($child->comment_parent);
                    $child->_comment_agent = get_userAgent_info($child->comment_agent);
                    $cur_comment->_comment_childs = $child_comment; //$child_comment;//load all-childs but single[$child];
                    ajax_child_comments_loop($child);
                }
            }
            // return first-level(contains sub-more) only
            if($cur_comment->comment_parent==0) return $cur_comment; //$child_comment
        }
        // Ajax request comments output
        function ajaxLoadComments(){
            $pid = $_POST['pid'];
            check_ajax_referer($pid.'_comment_ajax_nonce');  // 检查 nonce
            $comments_array = [];
            $comments = get_comments(array(
                'post_id' => $pid,
                'orderby' => 'comment_date',
                'order'   => 'DESC',
                // 'status'  => 'approve',
                'number'  => $_POST['limit'],
                'offset'  => $_POST['offset'],
                'parent'  => 0,
                // 'comment__not_in' => [2,14],
            ));
            foreach($comments as $each){
                // user privacy data crypt
                $each->comment_author_IP = sha1($each->comment_author_IP);
                $each->comment_author_email = md5($each->comment_author_email);
                // add Objects for frontend calls
                $each->_comment_agent = get_userAgent_info($each->comment_agent);
                if($each->comment_parent==0) array_push($comments_array, ajax_child_comments_loop($each));
            }
            print_r(json_encode($comments_array));
            die();
        }
        add_action('wp_ajax_ajaxLoadComments', 'ajaxLoadComments');
        add_action('wp_ajax_nopriv_ajaxLoadComments', 'ajaxLoadComments');
    }
    
    /*
     *--------------------------------------------------------------------------
     * 额外功能
     *--------------------------------------------------------------------------
    */
    // 自动创建视频截图预览 // Automatic-Generate images captures(jpg/gif) while uploading a video file.(whether uploading inside the article)
    if(get_option('site_video_capture_switcher')){
        $execmd = ['shell_exec','system','exec'];
        $shell = false;
        foreach($execmd as $cmd){
            if(function_exists($cmd)) $shell=$cmd;
        }
        if($shell){
            // https://wp-kama.com/hook/wp_embed_handler_video
            function add_video_attachment_capture($attachment_ID){
                global $shell; //$current_user, 
                get_currentuserinfo();
                function ratio($a, $b){
                    $gcd = function($a, $b) use (&$gcd) {
                        return ($a % $b) ? $gcd($b, $a % $b) : $b;
                    };
                    $g = $gcd($a, $b);
                    return $a/$g . ':' . $b/$g;
                };
                $file = get_post($attachment_ID); // get_post_mime_type($attachment_ID);
                // DO WHAT YOU NEED 
                $fileURI = get_attached_file($attachment_ID); // wp_get_upload_dir()["basedir"]
                if(file_exists($fileURI)){
                    $dirURI = substr($fileURI, 0, strrpos($fileURI,'/')); //wp_upload_dir()["path"];
                    $fileName = $file->post_title;
                    $filePath = $dirURI.'/'.$fileName;// with file-name
                    preg_match('/video\/.+/', $file->post_mime_type, $vdo_upload);
                    //attachment_url_to_postid($file->guid)// get_post_like_slug($fileName)
                    if (array_key_exists(0,$vdo_upload)) {
                        $fileWidth = $shell("ffmpeg -i ".$fileURI." 2>&1 | grep Video: | grep -Po '\d{3,5}x\d{3,5}' | cut -d'x' -f1");
                        $fileHeight = $shell("ffmpeg -i ".$fileURI." 2>&1 | grep Video: | grep -Po '\d{3,5}x\d{3,5}' | cut -d'x' -f2");
                        $file_ratio = ratio($fileWidth,$fileHeight);
                        $preset_ratio = '16:9';
                        $calcH = $fileHeight;
                        $calcW = $fileWidth;
                        if($file_ratio!=$preset_ratio){
                            list($scaleW, $scaleH) = explode(':', $preset_ratio);
                            if($fileHeight < $fileWidth){
                                $calcW = round($fileHeight / $scaleH * $scaleW); //根据高计算比例宽
                            }else{
                                $calcH = round($fileWidth / $scaleW * $scaleH); //根据宽计算比例高
                            }
                        }
                        mkdir($filePath, 0777);
                        $savePath = $filePath.'/'.$fileName;
                        file_put_contents($savePath.'.json', json_encode($file, JSON_UNESCAPED_SLASHES + JSON_PRETTY_PRINT));
                        // file_put_contents($savePath.'.txt', substr($fileURI, 0, strrpos($fileURI,'/')+1));
                        $fileList = glob($savePath.'*.jpeg');
                        // USE FFMPEG CAPTURE
                        if(count($fileList)<=0){
                            $shell('ffmpeg -i '.$fileURI.' -vf "scale='.$calcW.':'.$calcH.',setdar=16:9" -r 0.25 -f image2 "'.$savePath.'_%2d.jpeg"');
                            $fileList = glob($savePath.'*.jpeg');
                            $shell('ffmpeg -i '.$savePath.'_%2d.jpeg -filter_complex "scale=iw:-1,tile='.count($fileList).'x1" "'.$savePath.'.jpg"');
                            $shell('ffmpeg -r 1 -f image2 -i '.$savePath.'_%2d.jpeg -vf "scale=iw/2:-1" '.$savePath.'.gif');
                        }
                        unset($shell);
                    }
                }
            }
            add_action("add_attachment", 'add_video_attachment_capture');
            function delete_video_attachment_capture($attachment_ID){
                $attachment_file = get_post($attachment_ID);
                preg_match('/video\/.+/', $attachment_file->post_mime_type, $vdo_upload);
                if (!array_key_exists(0,$vdo_upload)) {
                    return;
                }else{
                    $fileURI = get_attached_file($attachment_ID); // wp_get_upload_dir()["basedir"]
                    if(file_exists($fileURI)){
                        $dirURI = substr($fileURI, 0, strrpos($fileURI,'/')); //wp_upload_dir()["path"];
                        $fileName = $attachment_file->post_title;
                        $filePath = $dirURI.'/'.$fileName;
                        // https://zhuanlan.zhihu.com/p/557484268
                        if(is_dir($filePath)){
                            $p = scandir($filePath);
                            foreach($p as $val){
                                if($val !="." && $val !=".."){
                                    if(is_dir($filePath.'/'.$val)){
                                        deldir($filePath.'/'.$val);
                                        // @rmdir($filePath.'/'.$val);
                                    }else{
                                        unlink($filePath.'/'.$val);
                                    }
                                }
                            }
                        }
                        @rmdir($filePath);
                    }
                }
            }
            add_action("delete_attachment", 'delete_video_attachment_capture');
        }
    };
    
?>