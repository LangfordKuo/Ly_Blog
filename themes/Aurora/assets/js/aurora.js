/**
 * Aurora Theme — Advanced Interactive Features
 */
(function(){
    'use strict';

    // ===== READING PROGRESS BAR =====
    var progressBar = document.getElementById('reading-progress');
    if (progressBar) {
        window.addEventListener('scroll', function(){
            var scrollTop = window.scrollY;
            var docHeight = document.documentElement.scrollHeight - window.innerHeight;
            var progress = docHeight > 0 ? Math.min((scrollTop / docHeight) * 100, 100) : 0;
            progressBar.style.width = progress + '%';
        }, {passive: true});
    }

    // ===== BACK TO TOP =====
    var backBtn = document.getElementById('back-to-top');
    if (backBtn) {
        window.addEventListener('scroll', function(){
            backBtn.classList.toggle('visible', window.scrollY > 400);
        }, {passive: true});
        backBtn.addEventListener('click', function(){
            window.scrollTo({top: 0, behavior: 'smooth'});
        });
    }

    // ===== HEADER SCROLL EFFECT =====
    var header = document.querySelector('.site-header');
    if (header) {
        window.addEventListener('scroll', function(){
            if (typeof header !== 'undefined' && header !== null) {
                header.classList.toggle('scrolled', window.scrollY > 50);
            }
        }, {passive: true});
    }

    // ===== TABLE OF CONTENTS =====
    var tocContainer = document.getElementById('toc-list');
    if (tocContainer) {
        var headings = document.querySelectorAll('.article-content h2, .article-content h3');
        var tocLinks = [];
        headings.forEach(function(h, i){
            if (!h.id) { h.id = 'heading-' + (i + 1); }
            var li = document.createElement('li');
            var a = document.createElement('a');
            a.href = '#' + h.id;
            a.textContent = h.textContent;
            a.className = h.tagName.toLowerCase() === 'h2' ? 'toc-h2' : 'toc-h3';
            a.addEventListener('click', function(e){
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({behavior:'smooth'});
            });
            li.appendChild(a);
            tocContainer.appendChild(li);
            tocLinks.push({el: h, link: a});
        });

        // Active TOC on scroll
        if (tocLinks.length > 0) {
            window.addEventListener('scroll', function(){
                var scrollPos = window.scrollY + 120;
                var active = null;
                tocLinks.forEach(function(item){
                    if (item.el.offsetTop <= scrollPos) active = item;
                });
                tocLinks.forEach(function(item){ item.link.classList.remove('active'); });
                if (active) active.link.classList.add('active');
            }, {passive: true});
        }
    }

    // ===== AJAX PAGINATION (Infinite Scroll) =====
    var loadMore = document.getElementById('load-more');
    if (loadMore) {
        var loading = false;
        var observer = new IntersectionObserver(function(entries){
            entries.forEach(function(entry){
                if (entry.isIntersecting && !loading) {
                    loading = true;
                    loadMore.click();
                }
            });
        }, {threshold: 0.1});
        observer.observe(loadMore);

        loadMore.addEventListener('click', function(e){
            e.preventDefault();
            if (loading) return;
            var btn = this;
            btn.textContent = '加载中...';
            btn.disabled = true;

            var url = btn.getAttribute('href');
            if (!url) return;

            fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}})
                .then(function(r){ return r.text(); })
                .then(function(html){
                    var parser = new DOMParser();
                    var doc = parser.parseFromString(html, 'text/html');
                    var newCards = doc.querySelectorAll('.article-card');
                    var container = document.getElementById('articles-container');
                    newCards.forEach(function(card){
                        card.style.animation = 'fadeInUp 0.5s ease both';
                        container.appendChild(card);
                    });
                    var nextBtn = doc.querySelector('#load-more');
                    if (nextBtn) {
                        btn.setAttribute('href', nextBtn.getAttribute('href'));
                        btn.textContent = '加载更多';
                        btn.disabled = false;
                        loading = false;
                    } else {
                        btn.remove();
                    }
                })
                .catch(function(){
                    btn.textContent = '加载失败，点击重试';
                    btn.disabled = false;
                    loading = false;
                });
        });
    }

    // ===== LIKE BUTTON =====
    document.querySelectorAll('.like-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            var id = btn.getAttribute('data-id');
            if (!id) return;
            var csrf = btn.getAttribute('data-csrf') || '';
            fetch(window.location.origin + '/like/' + id, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrf
                }
            })
            .then(function(r){ return r.json(); })
            .then(function(d){
                var countEl = btn.querySelector('.count');
                if (countEl) countEl.textContent = d.count;
                btn.classList.toggle('liked', d.liked);
            });
        });
    });

    // ===== SHARE BUTTONS =====
    document.querySelectorAll('.share-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            var platform = btn.getAttribute('data-platform');
            var url = encodeURIComponent(window.location.href);
            var title = encodeURIComponent(document.title);
            var shareUrls = {
                twitter: 'https://twitter.com/intent/tweet?url=' + url + '&text=' + title,
                weibo: 'https://service.weibo.com/share/share.php?url=' + url + '&title=' + title,
                facebook: 'https://www.facebook.com/sharer/sharer.php?u=' + url
            };
            if (shareUrls[platform]) {
                window.open(shareUrls[platform], '_blank', 'width=600,height=400');
            }
        });
    });

    // ===== COPY CODE BLOCKS =====
    document.querySelectorAll('.article-content pre').forEach(function(pre){
        var btn = document.createElement('button');
        btn.className = 'copy-code-btn';
        btn.textContent = '复制';
        btn.style.cssText = 'position:absolute;top:8px;right:8px;padding:4px 12px;border-radius:6px;border:1px solid var(--border);background:var(--card-bg);font-size:12px;cursor:pointer;color:var(--text2);transition:all 0.2s;font-family:inherit';
        btn.addEventListener('click', function(){
            var code = pre.querySelector('code') || pre;
            var text = code.textContent;
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(function(){
                    btn.textContent = '已复制!'; 
                    setTimeout(function(){ btn.textContent = '复制'; }, 2000);
                });
            } else {
                var ta = document.createElement('textarea');
                ta.value = text; ta.style.opacity = '0'; ta.style.position = 'fixed';
                document.body.appendChild(ta); ta.select();
                try { document.execCommand('copy'); btn.textContent = '已复制!'; } catch(e){}
                document.body.removeChild(ta);
                setTimeout(function(){ btn.textContent = '复制'; }, 2000);
            }
        });
        pre.style.position = 'relative';
        pre.appendChild(btn);
    });

    // ===== IMAGE LAZY LOADING =====
    if ('loading' in HTMLImageElement.prototype) {
        document.querySelectorAll('img[data-src]').forEach(function(img){
            img.src = img.getAttribute('data-src');
            img.removeAttribute('data-src');
        });
    } else {
        var imgObserver = new IntersectionObserver(function(entries){
            entries.forEach(function(entry){
                if (entry.isIntersecting) {
                    var img = entry.target;
                    img.src = img.getAttribute('data-src');
                    img.removeAttribute('data-src');
                    imgObserver.unobserve(img);
                }
            });
        });
        document.querySelectorAll('img[data-src]').forEach(function(img){ imgObserver.observe(img); });
    }

    // ===== SCROLL REVEAL =====
    var revealObserver = new IntersectionObserver(function(entries){
        entries.forEach(function(entry){
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in');
                revealObserver.unobserve(entry.target);
            }
        });
    }, {threshold: 0.15});
    document.querySelectorAll('.article-card, .sidebar-widget').forEach(function(el){
        revealObserver.observe(el);
    });

})();
