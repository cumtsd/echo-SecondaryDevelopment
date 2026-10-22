<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>

<div class="footer">
    <div class="layui-col-md12 t-copy">
        <span class="layui-breadcrumb">
            <span>&copy; <?php echo date('Y'); ?> <a href="<?php $this->options->siteUrl(); ?>"><?php $this->options->title(); ?></a></span>
            <span class="layui-hide-xs">Powered by <a href="http://typecho.org/" target="_blank" rel="nofollow">Typecho</a></span>
            <span>Theme by <a href="https://www.ncc.wang" target="_blank">Echo</a></span>
        </span>
    </div>
</div>

<script>
// 图片懒加载（手机端跳过，避免隐藏图片仍触发请求）
document.addEventListener('DOMContentLoaded', function() {
  if (window.innerWidth < 768) return;
  const images = document.querySelectorAll('img[data-src]');
  const imageObserver = new IntersectionObserver((entries, observer) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const img = entry.target;
        img.src = img.dataset.src;
        img.removeAttribute('data-src');
        observer.unobserve(img);
      }
    });
  });
  images.forEach(img => imageObserver.observe(img));
});
</script>

<script>hljs.highlightAll()</script>
<?php $this->footer(); ?>
<?php $this->options->script(); ?>
</body>

<!-- Google tag (gtag.js) 谷歌统计的代码202509月加入 - 已注释，国内无法访问 -->
<!--
<script async src="https://www.googletagmanager.com/gtag/js?id=G-PR3NQTS3R0"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-PR3NQTS3R0');
</script>
-->

</html>
