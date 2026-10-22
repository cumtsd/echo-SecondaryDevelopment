<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<!DOCTYPE HTML>
<html class="no-js" lang="zh-CN">
<head>
    <meta charset="<?php $this->options->charset(); ?>">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="renderer" content="webkit">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title><?php
        // 2026-09-22 SEO: 统一标题规则(文章: 标题 - 站名；首页: 站名 - 描述)，去除"~ 个人博客"双分隔符
        $suyuSiteName = $this->options->title;
        // 2026-09-29 加固: 扫描器伪造 ?page=字符串 时核心 getCurrentPage 会抛 TypeError，兜底为1避免500
        $suyuPageNo = 1;
        try { if (method_exists($this, '____currentPage')) $suyuPageNo = (int)$this->____currentPage(); } catch (\Throwable $e) { $suyuPageNo = 1; }
        if ($this->is('single')) {
            $suyuTitleStr = $this->title . ' - ' . $suyuSiteName;
        } elseif ($this->is('index')) {
            $suyuTitleStr = $suyuSiteName . ' - ' . $this->options->description;
        } else {
            $suyuArchName = trim((string)$this->getArchiveTitle());
            // 2026-09-28 SEO: 归档标题带文章数量，解决标签/分类同名重复及标题过短
            $suyuArchCount = 0;
            try { if (method_exists($this, 'getTotal')) $suyuArchCount = (int)$this->getTotal(); } catch (\Throwable $e) { $suyuArchCount = 0; }
            if ($this->is('tag')) {
                $suyuTitleStr = $suyuArchName . '标签文章' . ($suyuArchCount > 0 ? '（共' . $suyuArchCount . '篇）' : '') . ' - ' . $suyuSiteName;
            } elseif ($this->is('category')) {
                $suyuTitleStr = $suyuArchName . '文章' . ($suyuArchCount > 0 ? '（共' . $suyuArchCount . '篇）' : '') . ' - ' . $suyuSiteName;
            } elseif ($this->is('author')) {
                $suyuTitleStr = $suyuArchName . '发布的文章' . ($suyuArchCount > 0 ? '（共' . $suyuArchCount . '篇）' : '') . ' - ' . $suyuSiteName;
            } else {
                $suyuTitleStr = ($suyuArchName !== '' ? $suyuArchName . ' - ' : '') . $suyuSiteName;
            }
        }
        if ($suyuPageNo > 1) $suyuTitleStr .= ' - 第' . $suyuPageNo . '页';
        echo htmlspecialchars($suyuTitleStr);
    ?></title>

    <!-- SEO 优化 (2026-09-22 重构: 纯文本摘要/OG/JSON-LD，全部使用已有数据不新增查询) -->
    <?php
    $suyuSiteName  = $this->options->title;
    $suyuSiteUrl   = rtrim($this->options->siteUrl, '/');
    $suyuDefaultImg = $this->options->themeUrl . '/public/home/img/blog.jpg';

    if (!function_exists('suyu_seo_excerpt')) {
        // 纯文本摘要：去 script/style、标签、裸URL、折叠空白，避免硬截断产生半句话
        function suyu_seo_excerpt($html, $len = 150) {
            $t = preg_replace('/<(script|style)[^>]*>.*?<\/\1>/is', ' ', $html);
            $t = strip_tags($t);
            $t = preg_replace('~https?://[^\s<>"\']+~u', ' ', $t);
            $t = preg_replace('/\s+/u', ' ', $t);
            $t = trim($t);
            return mb_strlen($t, 'UTF-8') > $len ? mb_substr($t, 0, $len, 'UTF-8') : $t;
        }
    }

    if (!function_exists('suyu_archive_titles')) {
        // 2026-09-28: 取归档最新文章标题。优先用已加载 stack；header 阶段 stack 为空时，
        // 退化为一次 LIMIT 3 的轻量索引查询（实测约 3ms），仅归档页触发。
        function suyu_archive_titles($widget, $limit = 3) {
            $pick = function ($rows) use ($limit) {
                $out = array();
                foreach ($rows as $row) {
                    $t = trim(strip_tags((string)($row['title'] ?? '')));
                    if ($t !== '') {
                        $out[] = mb_strlen($t, 'UTF-8') > 24 ? mb_substr($t, 0, 24, 'UTF-8') : $t;
                    }
                    if (count($out) >= $limit) break;
                }
                return $out;
            };
            try {
                if (!empty($widget->stack) && is_array($widget->stack)) {
                    $out = $pick($widget->stack);
                    if ($out) return $out;
                }
                // 查库兜底
                if (method_exists($widget, 'getPageRow')) {
                    $row = $widget->getPageRow();
                    $db = \Typecho_Db::get();
                    if ($widget->is('tag') || $widget->is('category')) {
                        if (empty($row['mid'])) return array();
                        $sel = $db->select('table.contents.title')
                            ->from('table.contents')
                            ->join('table.relationships', 'table.contents.cid = table.relationships.cid')
                            ->where('table.relationships.mid = ?', (int)$row['mid'])
                            ->where('table.contents.type = ?', 'post')
                            ->where('table.contents.status = ?', 'publish')
                            ->order('table.contents.created', \Typecho_Db::SORT_DESC)
                            ->limit($limit);
                    } elseif ($widget->is('author')) {
                        if (empty($row['uid'])) return array();
                        $sel = $db->select('title')->from('table.contents')
                            ->where('authorId = ?', (int)$row['uid'])
                            ->where('type = ?', 'post')->where('status = ?', 'publish')
                            ->order('created', \Typecho_Db::SORT_DESC)->limit($limit);
                    } else {
                        return array();
                    }
                    return $pick($db->fetchAll($sel));
                }
            } catch (\Throwable $e) {}
            return array();
        }
    }

    // 2026-09-28: 首页固定使用更完整的描述（仅输出层，不改后台设置）
    $suyuIndexDesc = '苏玉博客专注分享网络资源、主机与Linux建站教程、医药研发注册资讯、消费数码评测及电子书资源，内容持续更新。';
    $suyuShortSuffix = '更多网络资源、主机教程、医药研发与消费数码等实用内容，欢迎访问' . $suyuSiteName . '。';

    if ($this->is('single')) {
        $suyuDesc = '';
        if (isset($this->fields['description'])) {
            $suyuDesc = trim((string)$this->fields['description']);
        }
        if ($suyuDesc === '') {
            $suyuDesc = suyu_seo_excerpt($this->content, 150);
        }
        // 2026-09-28: 独立页描述加标题前缀（避免与同题材文章描述重复）；短内容补站点后缀（避免描述过短）
        if ($this->is('page')) {
            $suyuDesc = trim((string)$this->title) . '：' . $suyuDesc;
        }
        if (mb_strlen($suyuDesc, 'UTF-8') < 50) {
            $suyuDesc = rtrim($suyuDesc, '。．.，,！!？?') . '。' . $suyuShortSuffix;
        }
        $suyuCanonical = $this->permalink;
        $suyuOgTitle  = $this->title;
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $this->content, $im)) {
            $suyuOgImage = $im[1];
        } else {
            $suyuOgImage = $suyuDefaultImg;
        }
    } else {
        if ($this->is('category') || $this->is('tag') || $this->is('author')) {
            $suyuArchName = trim((string)$this->getArchiveTitle());
            if (!isset($suyuArchCount)) {
                $suyuArchCount = 0;
                try { if (method_exists($this, 'getTotal')) $suyuArchCount = (int)$this->getTotal(); } catch (\Throwable $e) { $suyuArchCount = 0; }
            }
            // 2026-09-28: 用已加载的前3篇文章标题生成唯一且足够长的描述（零新增查询）
            $suyuArchTitles = suyu_archive_titles($this, 3);
            $suyuListStr = '';
            if ($suyuArchTitles) {
                $suyuListStr = '，最新包括' . implode('、', array_map(function ($t) { return '《' . $t . '》'; }, $suyuArchTitles)) . '等';
            }
            if ($this->is('tag')) {
                $suyuMetaDesc = trim((string)$this->getDescription());
                $suyuDesc = ($suyuMetaDesc !== '' ? rtrim($suyuMetaDesc, '。，,') . '。' : '')
                          . '标签「' . $suyuArchName . '」下共有' . $suyuArchCount . '篇相关文章' . $suyuListStr . '，持续更新。';
            } elseif ($this->is('category')) {
                $suyuMetaDesc = trim((string)$this->getDescription());
                $suyuDesc = ($suyuMetaDesc !== '' ? rtrim($suyuMetaDesc, '。，,') . '。' : '')
                          . '本分类共收录' . $suyuArchCount . '篇文章' . $suyuListStr . '。';
            } else {
                $suyuDesc = '作者「' . $suyuArchName . '」在' . $suyuSiteName . '共发布' . $suyuArchCount . '篇文章' . $suyuListStr . '。';
            }
            // 2026-09-29: 单篇标签等短描述补站点后缀，保证描述长度充足（Bing 阈值约50字）
            if (mb_strlen($suyuDesc, 'UTF-8') < 50) {
                $suyuDesc .= rtrim($suyuShortSuffix, '。') . '。';
            }
        } elseif ($this->is('search')) {
            $suyuKw = htmlspecialchars($this->request->filter('html')->keywords ?? '');
            $suyuDesc = '站内搜索「' . $suyuKw . '」的结果';
        } elseif ($this->is('page')) {
            $suyuDesc = suyu_seo_excerpt($this->content, 150);
        } else {
            $suyuDesc = $suyuPageNo > 1 ? $suyuIndexDesc . ' - 第' . $suyuPageNo . '页'
                                        : $suyuIndexDesc;
        }
        $suyuScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $suyuHost   = $_SERVER['HTTP_HOST'] ?? parse_url($suyuSiteUrl, PHP_URL_HOST);
        $suyuCanonical = $suyuScheme . '://' . $suyuHost . ($_SERVER['REQUEST_URI'] ?? '/');
        $suyuOgTitle  = $suyuTitleStr;
        $suyuLogoOpt  = trim((string)$this->options->logoUrl);
        $suyuOgImage  = $suyuLogoOpt !== '' ? $suyuLogoOpt : $suyuDefaultImg;
    }
    ?>
    <meta name="description" content="<?php echo htmlspecialchars($suyuDesc); ?>" />
    <meta property="og:site_name" content="<?php echo htmlspecialchars($suyuSiteName); ?>" />
    <meta property="og:type" content="<?php echo $this->is('single') ? 'article' : 'website'; ?>" />
    <meta property="og:locale" content="zh_CN" />
    <meta property="og:title" content="<?php echo htmlspecialchars($suyuOgTitle); ?>" />
    <meta property="og:url" content="<?php echo htmlspecialchars($suyuCanonical); ?>" />
    <meta property="og:description" content="<?php echo htmlspecialchars($suyuDesc); ?>" />
    <meta property="og:image" content="<?php echo htmlspecialchars($suyuOgImage); ?>" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="<?php echo htmlspecialchars($suyuOgTitle); ?>" />
    <meta name="twitter:description" content="<?php echo htmlspecialchars($suyuDesc); ?>" />
    <meta name="twitter:image" content="<?php echo htmlspecialchars($suyuOgImage); ?>" />
    <link rel="canonical" href="<?php echo htmlspecialchars($suyuCanonical); ?>" />

    <?php
    /* ===== JSON-LD 结构化数据（json_encode 安全输出，不新增数据库查询） ===== */
    $suyuLdGraph = array();
    $suyuLdGraph[] = array(
        '@type'       => 'WebSite',
        'name'        => $suyuSiteName,
        'url'         => $suyuSiteUrl . '/',
        'description' => $this->options->description,
        'inLanguage'  => 'zh-CN',
        'potentialAction' => array(
            '@type'       => 'SearchAction',
            'target'      => $suyuSiteUrl . '/?s={search_term_string}',
            'query-input' => 'required name=search_term_string'
        )
    );
    if ($this->is('post')) {
        $suyuLdGraph[] = array(
            '@type'          => 'BlogPosting',
            'headline'       => $this->title,
            'description'    => $suyuDesc,
            'url'            => $suyuCanonical,
            'datePublished'  => date('c', $this->date->timeStamp),
            'dateModified'   => date('c', (int)$this->modified),
            'inLanguage'     => 'zh-CN',
            'author'         => array('@type' => 'Person', 'name' => $this->author->screenName),
            'publisher'      => array(
                '@type' => 'Organization',
                'name'  => $suyuSiteName,
                'logo'  => array('@type' => 'ImageObject', 'url' => $suyuDefaultImg)
            ),
            'mainEntityOfPage' => $suyuCanonical,
            'image'           => $suyuOgImage
        );
        $suyuCrumbs = array(
            '@type' => 'BreadcrumbList',
            'itemListElement' => array(
                array('@type' => 'ListItem', 'position' => 1, 'name' => '首页', 'item' => $suyuSiteUrl . '/')
            )
        );
        if (!empty($this->categories) && is_array($this->categories)) {
            $c0 = $this->categories[0];
            $suyuCrumbs['itemListElement'][] = array('@type' => 'ListItem', 'position' => 2,
                'name' => $c0['name'], 'item' => $c0['permalink']);
            $suyuCrumbs['itemListElement'][] = array('@type' => 'ListItem', 'position' => 3,
                'name' => $this->title, 'item' => $suyuCanonical);
        } else {
            $suyuCrumbs['itemListElement'][] = array('@type' => 'ListItem', 'position' => 2,
                'name' => $this->title, 'item' => $suyuCanonical);
        }
        $suyuLdGraph[] = $suyuCrumbs;
    }
    ?>
    <script type="application/ld+json">
    <?php echo json_encode(array('@context' => 'https://schema.org', '@graph' => $suyuLdGraph), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?>
    </script>

    <!-- 通过自有函数输出HTML头部信息 -->
    <?php $this->header('description='); // 2026-09-22 SEO: 抑制核心重复的 meta description ?>
    <link rel="shortcut icon" href="<?php $this->options->themeUrl('public/home/img/favicon.png'); ?>">
    <link rel="stylesheet" href="<?php $this->options->themeUrl('public/lib/layui/css/layui.css'); ?>?v=2.6.8">
    <link rel="stylesheet" href="<?php $this->options->themeUrl('public/home/css/style.css'); ?>?v=20261020d">
    <script src="<?php $this->options->themeUrl('public/lib/layui/layui.js'); ?>?v=2.6.8"></script>
    <script src="<?php $this->options->themeUrl('public/home/js/main.js'); ?>?v=3.0"></script>
    <link rel="stylesheet" href="//cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.3.1/build/styles/github<?php if ($this->options->codeStyle == '1'){echo '-dark';} ?>.min.css">
    <script src="//cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.3.1/build/highlight.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/vditor@3.8.4/dist/index.css" />
    <script src="https://cdn.jsdelivr.net/npm/vditor@3.8.4/dist/index.min.js"></script>

    <style>
        .suyu-sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;}
        <?php if (!empty($this->options->navFixed) && $this->options->navFixed == 'true'): ?>
            .header {
                position: fixed;
                left: 0;
                top: 0;
                z-index: 10000;
                width: 100%;
            }
            .layui-container {
                margin-top: 70px;
            }
            .timeline-menu-fixed {
                top: 70px;
            }
            .timeline-header h2 {
                margin-top: -70px;
                padding-top: 70px;
            }
        <?php endif; ?>
        <?php if ($this->options->codeStyle == '1'): ?>
        pre {
            background: #282C34;
        }
        pre code[class*='language-'] {
            background: #0d1117;
        }
        <?php endif; ?>
        <?php if (!empty($this->options->headerColor)): ?>
            .header {
                background-color: <?php $this->options->headerColor(); ?>;
            }
        <?php endif; ?>
        <?php if (!empty($this->options->headerTextColor)): ?>
            .header a{
                color: <?php $this->options->headerTextColor(); ?>;
            }
            .header .layui-nav .layui-nav-item a {
                color: <?php $this->options->headerTextColor(); ?>;
            }
            .header .layui-nav .layui-nav-item .layui-nav-child a {
                color: #212220;
            }
            /*.header .layui-nav .layui-nav-more {*/
            /*    border-color: */<?php //$this->options->headerTextColor(); ?>/* transparent transparent;*/
            /*}*/
            /*.header .layui-nav .layui-nav-mored {*/
            /*    border-color: transparent transparent */<?php //$this->options->headerTextColor(); ?>/*;*/
            /*}*/
        <?php endif; ?>
        <?php if (!empty($this->options->footerColor)): ?>
            .footer {
                background: <?php $this->options->footerColor(); ?>;
            }
        <?php endif; ?>
        <?php if (!empty($this->options->footerTextColor)): ?>
            .footer span {
                color: <?php $this->options->footerTextColor(); ?>;
            }
            .footer .layui-breadcrumb a {
                color: <?php $this->options->footerTextColor(); ?> !important;
            }
            .footer .layui-breadcrumb span[lay-separator] {
                color: <?php $this->options->footerTextColor(); ?>;
            }
        <?php endif; ?>
        <?php if (!empty($this->options->backgroundColor)): ?>
            body {
                background: <?php $this->options->backgroundColor(); ?>;
            }
            .layui-carousel {
                background: <?php $this->options->backgroundColor(); ?>;
            }
        <?php endif; ?>
        <?php if (!empty($this->options->backgroundImg)): ?>
            @media (min-width: 768px) {
                body {
                    background-image: url(<?php $this->options->backgroundImg(); ?>);
                    background-repeat: no-repeat;
                    background-size: cover;
                    background-position: center center;
                    background-attachment: fixed;
                }
            }
        <?php endif; ?>
        /* 手机端隐藏首页文章列表缩略图 */
        @media (max-width: 767px) {
            .list-pic-mobile-hide {
                display: none !important;
            }
        }
    </style>
    <link rel="stylesheet" href="<?php $this->options->themeUrl('public/home/css/dark.css'); ?>?v=20260922">
</head>
<body>
<?php if ($this->is('index')): ?><h1 class="suyu-sr-only"><?php $this->options->title(); ?> - <?php $this->options->description(); ?></h1><?php endif; ?>
<!--[if lt IE 8]>
    <div class="browsehappy" role="dialog"><?php _e('当前网页 <strong>不支持</strong> 你正在使用的浏览器. 为了正常的访问, 请 <a href="http://browsehappy.com/">升级你的浏览器</a>'); ?>.</div>
<![endif]-->

<div class="layui-header header">
    <div class="layui-main">
        <a class="logo" href="<?php $this->options->siteUrl(); ?>">
            <?php if ($this->options->logoUrl): ?><img src="<?php $this->options->logoUrl() ?>" alt="<?php $this->options->title() ?>" /><?php endif; ?>
            <?php if ($this->options->title): ?><?php $this->options->title() ?><?php endif; ?>
        </a>

        <ul class="layui-nav">
            <li class="layui-nav-item layui-hide-xs <?php if($this->is('index')): ?>layui-this<?php endif; ?>">
                <a href="<?php $this->options->siteUrl(); ?>"><i class="layui-icon layui-icon-home suyu-nav-ico"></i><?php _e('首页'); ?></a>
            </li>

			<?php
                //栏目分类内容
                $category = '';
                $Category_List = $this->widget('Widget_Metas_Category_List');
                if($Category_List->have()){
                    while($Category_List->next()){
                        if($Category_List->levels==0){
							$layui_this = '';
							if($this->is('page', $Category_List->slug)){
								$layui_this = 'layui-this';
							}

                            $category .= '<li class="layui-nav-item layui-hide-xs '.$layui_this.'">'.
                                '<a href="'.$Category_List->permalink.'" title="'.$Category_List->name.'">'.$Category_List->name.'</a>';

                            $childrens = $this->widget('Widget_Metas_Category_List')->getAllChildren($Category_List->mid);
                            if($childrens){
                                $category .= '<dl class="layui-nav-child">';
                                for ($i=0; $i <count($childrens) ; $i++) {
                                    $thisChild = $this->widget('Widget_Metas_Category_List')->getCategory($childrens[$i]);
                                    $category .= '<dd><a href="'.$thisChild["permalink"].'">'.$thisChild["name"].'</a></dd>';
                                }
                                $category .= '</dl>';
                            }
                            $category .= '</li>';
                        }
                    }
                }
			?>
			<?php
                //独立页面内容
                $page = '';
                $Page_List = $this->widget('Widget_Contents_Page_List');
                if($Page_List->have()){
                    while($Page_List->next()){
						$layui_this = '';
                        if($this->is('page', $Page_List->slug)){
							$layui_this = 'layui-this';
                        }

                        /* 2026-10-20 纯线性微博SVG(无黑底纹)，currentColor随文字 */
                        $suyu_weibo_svg = '<svg class="suyu-nav-ico suyu-nav-weibo" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4.27799 8.59201C7.05085 5.8217 10.282 4.55876 11.4966 5.77587C12.0338 6.31312 12.0848 7.23996 11.741 8.34757C11.5628 8.9052 12.2655 8.5971 12.2655 8.5971C14.5062 7.66008 16.4618 7.60407 17.1747 8.62511C17.5541 9.16746 17.5184 9.93133 17.1671 10.8149C17.0041 11.2223 17.2154 11.2859 17.5261 11.3776C18.789 11.7697 20.1945 12.7144 20.1945 14.3822C20.1945 17.1448 16.2148 20.6205 10.2311 20.6205C5.66569 20.6205 1.00098 18.4078 1.00098 14.7692C1.00098 12.8671 2.20535 10.6672 4.27799 8.59201ZM16.4108 14.3338C16.174 11.9429 13.0294 10.2954 9.38829 10.657C5.74717 11.016 2.9845 13.2465 3.2213 15.6375C3.4581 18.0309 6.60271 19.6758 10.2438 19.3168C13.885 18.9552 16.6451 16.7247 16.4108 14.3338ZM6.16221 14.4382C6.91589 12.9104 8.87395 12.0473 10.6079 12.4979C12.4005 12.9614 13.3146 14.6521 12.5838 16.2969C11.8403 17.98 9.70148 18.8763 7.88856 18.2906C6.13674 17.7254 5.39579 15.9965 6.16221 14.4382ZM8.8765 15.0162C8.31378 14.7794 7.58556 15.0238 7.23672 15.5687C6.88279 16.1162 7.0483 16.7705 7.60847 17.0252C8.17628 17.2823 8.93252 17.0379 9.2839 16.4752C9.63019 15.9074 9.44686 15.2581 8.8765 15.0162ZM10.2642 14.4382C10.0478 14.3542 9.77787 14.456 9.65056 14.6699C9.52834 14.8838 9.59709 15.1282 9.81352 15.2173C10.0325 15.309 10.3151 15.2046 10.4424 14.9856C10.5647 14.7666 10.4857 14.5197 10.2642 14.4382ZM15.9576 2.92408C17.9258 2.50649 20.0545 3.12017 21.5008 4.71918C22.947 6.31822 23.3341 8.50034 22.7204 10.4228C22.5796 10.8639 22.1036 11.1075 21.6612 10.9626C21.2181 10.8174 20.9762 10.3438 21.1188 9.90078C21.5568 8.54108 21.2806 6.98652 20.2531 5.84971C19.2244 4.71154 17.712 4.27613 16.3115 4.57404C15.8558 4.67099 15.41 4.37994 15.3109 3.92475C15.2115 3.46897 15.5018 3.02078 15.9576 2.92408ZM16.584 5.84971C17.5414 5.64601 18.5802 5.94392 19.283 6.72307C19.9858 7.50222 20.1742 8.56655 19.8737 9.49847C19.7511 9.87865 19.3409 10.089 18.9596 9.96443C18.5777 9.83967 18.3714 9.43227 18.4962 9.04779C18.6464 8.59201 18.5522 8.07258 18.2085 7.69064C17.8622 7.31125 17.3555 7.16611 16.887 7.26542C16.4949 7.35199 16.1104 7.09991 16.0263 6.71034C15.9423 6.31822 16.1917 5.93316 16.584 5.84971Z"/></svg>';
                        /* 2026-10-20 导航图标：按独立页标题映射，未配置的页面不显示图标 */
                        $suyu_nav_icons = array(
                            '关于我'   => '<i class="layui-icon layui-icon-username suyu-nav-ico"></i>',
                            '留言板'   => '<i class="layui-icon layui-icon-dialogue suyu-nav-ico"></i>',
                            '网盘搜索' => '<i class="layui-icon layui-icon-search suyu-nav-ico"></i>',
                            '我的说说' => $suyu_weibo_svg
                        );
                        $suyu_nav_ico = isset($suyu_nav_icons[$Page_List->title])
                            ? $suyu_nav_icons[$Page_List->title] : '';
                        $page .= '<li class="layui-nav-item layui-hide-xs '.$layui_this.'">'.
                            '<a href="'.$Page_List->permalink.'" title="'.$Page_List->title.'">'.$suyu_nav_ico.$Page_List->title.'</a>'.
                            '</li>';
                    }
                }
			?>

			<?php if ($this->options->nav==0): ?><?php echo $page; ?><?php endif; ?>
			<?php if ($this->options->nav==1): ?><?php echo $category; ?><?php endif; ?>
			<?php if ($this->options->nav==2): ?><?php echo $page.$category; ?><?php endif; ?>
			<?php if ($this->options->nav==3): ?><?php echo $category.$page; ?><?php endif; ?>

            <?php /* 2026-09-29 导航末尾外链：读书笔记（新窗口打开） */ ?>
            <li class="layui-nav-item layui-hide-xs">
                <a href="https://sdcumt.notion.site/readingnote" target="_blank" rel="noopener" title="读书笔记"><i class="layui-icon layui-icon-read suyu-nav-ico"></i>读书笔记</a>
            </li>

            <?php $this->widget('Widget_Contents_Page_List')->to($pages); ?>
            <li class="layui-nav-item nav-btn layui-hide-sm">
                <a href="javascript:;"><i class='layui-icon layui-icon-more'></i></a>
                <dl class="layui-nav-child">
                    <?php
                    $suyu_m_icons = array(
                        '关于我'   => '<i class="layui-icon layui-icon-username suyu-nav-ico"></i>',
                        '留言板'   => '<i class="layui-icon layui-icon-dialogue suyu-nav-ico"></i>',
                        '网盘搜索' => '<i class="layui-icon layui-icon-search suyu-nav-ico"></i>',
                        '我的说说' => $suyu_weibo_svg
                    );
                    while($pages->next()):
                        $suyu_m_ico = isset($suyu_m_icons[$pages->title])
                            ? $suyu_m_icons[$pages->title] : ''; ?>
                        <dd><a href="<?php $pages->permalink(); ?>"><?php echo $suyu_m_ico; $pages->title(); ?></a></dd>
                    <?php endwhile; ?>
                    <dd><a href="https://sdcumt.notion.site/readingnote" target="_blank" rel="noopener"><i class="layui-icon layui-icon-read suyu-nav-ico"></i>读书笔记</a></dd>
                </dl>
            </li>
        </ul>
    </div>
</div>
