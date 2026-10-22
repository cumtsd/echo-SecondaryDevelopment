<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php $this->need('common/header.php'); ?>

<?php /* 苏玉博客悬浮目录树(suyu-toc) - CSS 引入 2026-09-21 */ ?>
<link rel="stylesheet" href="<?php $this->options->themeUrl('public/home/css/suyu-toc.css'); ?>?v=20260921e">

<div class="layui-container">
    <div class="layui-row layui-col-space15 main">
        <div class="map">
            <span class="layui-breadcrumb">
                <a href="<?php $this->options->siteUrl(); ?>"><?php _e('首页'); ?></a>
                <?php $this->category(','); ?>
                <a><cite>正文</cite></a>
            </span>
        </div>
        <div class="layui-col-md9 layui-col-lg9">
            <div class="title-article">
                <h1><?php $this->title() ?></h1>
                <div class="title-msg">
                    <span><i class="layui-icon">&#xe612;</i> <?php $this->author(); ?></span>
                    <span><i class="layui-icon">&#xe60e;</i> <?php $this->date('Y-m-d A'); ?> </span>
                    <span><i class="layui-icon">&#xe62c;</i> <?php get_post_view($this); ?>℃</span>
                    <span><i class="layui-icon">&#xe63a;</i> <?php $this->commentsNum('%d'); ?>条</span>
                </div>
            </div>
            <div class="text" id="preview" itemprop="articleBody">
                <?php
                // 2026-09-22 性能优化：正文图片懒加载(首图即时加载保护LCP，其余 loading=lazy)
                // 仅在输出层过滤，不修改数据库中的正文内容
                ob_start();
                $this->content();
                $suyuPostContent = ob_get_clean();
                $suyuImgCount = 0;
                $suyuImgAlt = htmlspecialchars($this->title, ENT_QUOTES);
                echo preg_replace_callback('/<img\b[^>]*>/i', function ($m) use (&$suyuImgCount, $suyuImgAlt) {
                    // 2026-09-28 SEO: 缺 alt 的图片统一补文章标题（输出层，不改数据库）
                    $tag = $m[0];
                    if (stripos($tag, 'alt=') === false) {
                        $tag = preg_replace('/<img\b/i', '<img alt="' . $suyuImgAlt . '"', $tag, 1);
                    }
                    $suyuImgCount++;
                    if ($suyuImgCount === 1 || stripos($tag, 'loading=') !== false) {
                        return $tag;
                    }
                    return preg_replace('/<img\b/i', '<img loading="lazy" decoding="async"', $tag, 1);
                }, $suyuPostContent);
                ?>
            </div>
            <div class="tags-text">
                <i class="layui-icon">&#xe66e;</i><?php _e('标签: '); ?><?php $this->tags(', ', true, 'none'); ?>
            </div>
            <div class="copy-text">
                <div>
                    <p>非特殊说明，本博所有文章均为博主原创。</p>
                    <p class="hidden-xs">如若转载，请注明出处：<a href="<?php $this->permalink() ?>"><?php $this->permalink() ?></a> </p>
                </div>
            </div>
            <div class="page-text">
                <div>
                    <span class="layui-badge layui-bg-gray">上一篇</span>
                    <?php $this->thePrev('%s','没有了'); ?>
                </div>
                <div>
                    <span class="layui-badge layui-bg-gray">下一篇</span>
                    <?php $this->theNext('%s','没有了'); ?>
                </div>
            </div>
            <div class="comment-text layui-form">
                <?php $this->need('common/comments.php'); ?>
            </div>
        </div>

        <?php $this->need('common/sidebar.php'); ?>

    </div>
</div>

<?php /* 苏玉博客悬浮目录树(suyu-toc) - JS 引入 2026-09-21 */ ?>
<script src="<?php $this->options->themeUrl('public/home/js/suyu-toc.js'); ?>?v=20261019a" defer></script>

<?php $this->need('common/footer.php'); ?>
