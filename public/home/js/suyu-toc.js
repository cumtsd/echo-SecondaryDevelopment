/* ============================================================
 * 苏玉博客 blog.suyu.uk - 文章悬浮目录树组件 (suyu-toc)
 * 日期: 2026-09-21
 * 说明:
 *   1. 仅在文章页 #preview 容器内扫描 h2~h6 生成层级目录；
 *   2. 文章没有任何标题时不渲染任何元素；
 *   3. 点击平滑滚动定位，滚动时自动高亮当前章节；
 *   4. 标题栏可折叠/展开，中等屏幕默认折叠；窄屏(<1100px)由 CSS 隐藏；
 *   5. 纯原生 JS，无外部依赖，不修改正文 DOM 结构（仅补充 id）。
 * ============================================================ */
(function () {
    'use strict';

    function initSuyuToc() {
        var content = document.getElementById('preview');
        if (!content) return;

        // 只收集正文中可见、且有文字内容的标题
        var headings = Array.prototype.slice
            .call(content.querySelectorAll('h1, h2, h3, h4, h5, h6')) /* 2026-10-19 兼容正文h1章节(419.html) */
            .filter(function (h) {
                return h.textContent.replace(/\s/g, '').length > 0 &&
                    h.offsetParent !== null;
            });

        // 无目录结构：不渲染
        if (headings.length === 0) return;

        /* 为标题补充锚点 id（保留已有 id） */
        headings.forEach(function (h, i) {
            if (!h.id) h.id = 'suyu-toc-anchor-' + (i + 1);
        });

        /* 根据标题层级构建树 */
        function buildTree(nodes) {
            var root = { level: 1, children: [] };
            var stack = [root];
            nodes.forEach(function (el) {
                var node = { level: parseInt(el.tagName.substring(1), 10), el: el, children: [] };
                while (stack.length > 1 && stack[stack.length - 1].level >= node.level) {
                    stack.pop();
                }
                stack[stack.length - 1].children.push(node);
                stack.push(node);
            });
            return root.children;
        }

        var linksById = {};

        function renderTree(nodes) {
            var ul = document.createElement('ul');
            nodes.forEach(function (node) {
                var li = document.createElement('li');
                var a = document.createElement('a');
                a.href = '#' + node.el.id;
                a.textContent = node.el.textContent;
                a.setAttribute('data-suyu-toc', node.el.id);
                a.addEventListener('click', function (e) {
                    e.preventDefault();
                    node.el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    if (window.history && window.history.replaceState) {
                        window.history.replaceState(null, '', '#' + node.el.id);
                    }
                    setActive(node.el.id);
                });
                linksById[node.el.id] = a;
                li.appendChild(a);
                if (node.children.length > 0) {
                    li.appendChild(renderTree(node.children));
                }
                ul.appendChild(li);
            });
            return ul;
        }

        /* 组装面板 */
        var panel = document.createElement('aside');
        panel.className = 'suyu-toc';
        panel.setAttribute('aria-label', '文章目录');

        var head = document.createElement('div');
        head.className = 'suyu-toc__head';
        var title = document.createElement('span');
        title.textContent = '文章目录';
        var toggle = document.createElement('i');
        toggle.className = 'suyu-toc__toggle';
        toggle.innerHTML = '&#9660;';
        head.appendChild(title);
        head.appendChild(toggle);

        var body = document.createElement('div');
        body.className = 'suyu-toc__body';
        body.appendChild(renderTree(buildTree(headings)));

        panel.appendChild(head);
        panel.appendChild(body);
        document.body.appendChild(panel);

        /* 面板顶部与文章白色标题卡片顶部齐平（不垂直居中）；
           注意 .title-article 与 #preview 是同级节点，需在文档范围内查找；
           只在初始/加载完成/窗口尺寸变化时测量，滚动时保持固定不跟随 */
        var alignTarget = document.querySelector('.title-article') ||
            content.parentElement ||
            content.closest('.layui-row') || content;
        /* 实测文章卡片位置，同时设置面板的 top/left/width；
           浏览器缩放会触发 resize，重测后保证任何缩放比例下都不与卡片重叠：
           空间充足→16px 间距停靠；空间不足→面板收窄至剩余宽度；极窄→隐藏 */
        var SUYU_TOC_GAP = 16, SUYU_TOC_MIN_LEFT = 8, SUYU_TOC_MIN_WIDTH = 150;
        // 2026-10-10: 卡片顶部滚出视口后，目录顶部最低钉在视口下 80px(与白卡顶部视觉齐平)，
        // 不再顶到视口最上沿；max-height 在 CSS 中随该变量联动，底部留白行为不变
        var SUYU_TOC_MIN_TOP = 80;
        function updateTocTop() {
            var rt = alignTarget.getBoundingClientRect().top;
            panel.style.setProperty('--suyu-toc-top',
                Math.round(rt < SUYU_TOC_MIN_TOP ? SUYU_TOC_MIN_TOP : rt) + 'px');
        }
        function alignPanel() {
            updateTocTop();
            var r = alignTarget.getBoundingClientRect();

            // 先清掉内联覆盖，读取 CSS 媒体查询决定的自然宽度
            panel.style.display = '';
            panel.style.width = '';
            var naturalW = panel.offsetWidth || 210;
            var avail = Math.round(r.left) - SUYU_TOC_GAP - SUYU_TOC_MIN_LEFT;

            if (avail >= naturalW) {
                panel.style.width = naturalW + 'px';
                panel.style.setProperty('--suyu-toc-left',
                    (Math.round(r.left) - naturalW - SUYU_TOC_GAP) + 'px');
            } else if (avail >= SUYU_TOC_MIN_WIDTH) {
                panel.style.width = avail + 'px';
                panel.style.setProperty('--suyu-toc-left', SUYU_TOC_MIN_LEFT + 'px');
            } else {
                panel.style.display = 'none';
            }
        }
        function scheduleAlign() {
            window.requestAnimationFrame(alignPanel);
        }
        alignPanel();
        window.addEventListener('load', scheduleAlign);
        window.addEventListener('resize', scheduleAlign, { passive: true });
        if (window.ResizeObserver) {
            new ResizeObserver(scheduleAlign).observe(alignTarget);
        }

        // 中等屏幕默认折叠
        if (window.innerWidth < 1500) {
            panel.classList.add('suyu-toc--collapsed');
        }

        head.addEventListener('click', function () {
            panel.classList.toggle('suyu-toc--collapsed');
        });

        /* 当前章节高亮 + 2026-10-10 长目录时内部滚动条跟随到当前章节；
           鼠标悬停在目录上(用户正手动浏览)或目录折叠时不抢滚动 */
        var suyuTocHover = false;
        panel.addEventListener('mouseenter', function () { suyuTocHover = true; });
        panel.addEventListener('mouseleave', function () { suyuTocHover = false; });
        function setActive(id) {
            Object.keys(linksById).forEach(function (key) {
                linksById[key].classList.toggle('is-active', key === id);
            });
            var link = linksById[id];
            if (!link || suyuTocHover || body.clientHeight === 0) return;
            // 相对目录可视区的位置（不依赖 offsetParent 层级），最小幅度滚动
            var visibleTop = link.getBoundingClientRect().top - body.getBoundingClientRect().top;
            var margin = 6;
            if (visibleTop < margin) {
                body.scrollTop += visibleTop - margin;
            } else if (visibleTop + link.offsetHeight > body.clientHeight - margin) {
                body.scrollTop += visibleTop + link.offsetHeight - body.clientHeight + margin;
            }
        }

        /* 滚动监听：取视口顶部下方 90px 处最后一个已越过的标题 */
        var ticking = false;
        function onScroll() {
            if (ticking) return;
            ticking = true;
            window.requestAnimationFrame(function () {
                updateTocTop(); // 滚动时只同步 top(单次轻量测量)，宽度/左缘仍仅在缩放时重算
                var offset = 90;
                var current = null;
                for (var i = 0; i < headings.length; i++) {
                    var top = headings[i].getBoundingClientRect().top;
                    if (top - offset <= 0) {
                        current = headings[i].id;
                    } else {
                        break;
                    }
                }
                if (current) setActive(current);
                ticking = false;
            });
        }
        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll, { passive: true });
        onScroll();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSuyuToc);
    } else {
        initSuyuToc();
    }
})();
