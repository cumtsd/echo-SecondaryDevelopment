<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php $this->need('common/header.php'); ?>

<div class="layui-container">
    <div class="layui-row layui-col-space15 main">
        <div class="map">
            <span class="layui-breadcrumb">
                <a href="<?php $this->options->siteUrl(); ?>"><?php _e('首页'); ?></a>
                <a><cite><?php _e('404'); ?></cite></a>
            </span>
        </div>
        <div class="layui-col-md9 layui-col-lg9">
            <div class="title-article text-center">
                <h1>404</h1>
            </div>
            <div class="text" id="preview" itemprop="articleBody">
                <p style="text-align:center;font-size:16px;color:#666;margin:30px 0 20px;">哎呀，您访问的页面不存在或已被删除~</p>
                <p style="text-align:center;">
                    <a href="<?php $this->options->siteUrl(); ?>" class="layui-btn layui-btn-normal">
                        <i class="layui-icon">&#xe68e;</i> 返回首页
                    </a>
                </p>
                <hr>
                <h3 style="font-size:16px;border-left:3px solid #1E9FFF;padding-left:10px;margin:20px 0 15px;">试试搜索？</h3>
                <form method="post" action="<?php $this->options->siteUrl(); ?>" role="search">
                    <input type="text" name="s" class="layui-input" placeholder="输入关键词搜索..." style="max-width:100%;height:40px;">
                    <button type="submit" class="layui-btn layui-btn-fluid layui-btn-normal" style="margin-top:10px;">搜索</button>
                </form>
                <?php $this->widget('Widget_Contents_Hot')->to($hotPosts); ?>
                <?php if ($hotPosts->have()): ?>
                <h3 style="font-size:16px;border-left:3px solid #1E9FFF;padding-left:10px;margin:25px 0 15px;">热门文章</h3>
                <ul style="list-style:none;padding:0;margin:0;">
                    <?php while($hotPosts->next()): ?>
                    <li style="padding:10px 0;border-bottom:1px dashed #eee;">
                        <a href="<?php $hotPosts->permalink(); ?>" title="<?php $hotPosts->title(); ?>" style="color:#555;text-decoration:none;">
                            <i class="layui-icon" style="font-size:14px;color:#1E9FFF;margin-right:5px;">&#xe68f;</i>
                            <?php $hotPosts->title(); ?>
                        </a>
                    </li>
                    <?php endwhile; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>

        <?php $this->need('common/sidebar.php'); ?>

    </div>
</div>

<?php $this->need('common/footer.php'); ?>
