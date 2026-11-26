/** Pagebreak navigation. Retains the public flexibreak.next/previous API. */
(function () {
  'use strict';
  function FlexiBreak(element, options = {}) {
    this.options = {duration: 600, mouseOverClass: 'active', activateOnLoad: 'hash', wrap: false, ...options};
    this.el = typeof element === 'string' ? document.getElementById(element) : element;
    this.elid = this.el?.id;
    this.tocLinksScrolled = [...(this.el?.querySelectorAll('.tocScrolled') || [])];
    this.tocLinksPaginated = [...(this.el?.querySelectorAll('.tocPaginated') || [])];
    this.pages = [...document.querySelectorAll('.articlePage, .articleAnchor')];
    this.tocLinkAll = [...document.querySelectorAll('.tocAll')];
    this.currentIndex = Math.max(0, this.pages.findIndex(page => page.classList.contains('active')));
    this.currentPage = this.pages[this.currentIndex];
    const each = (selector, callback) => document.querySelectorAll(selector).forEach(callback);
    const highlight = links => links.forEach(link => link.parentElement.classList.remove('active'));
    const animate = page => {
      if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        page.animate([{opacity: 0.01}, {opacity: 1}], {duration: this.options.duration, easing: 'ease-in-out'});
      }
    };
    this.activate = (page, skipAnim = false) => {
      const index = this.pages.indexOf(page);
      if (index < 0) return false;
      this.currentPage = page; this.currentIndex = index;
      this.pages.forEach(element => element.classList.toggle('active', element === page));
      if (!skipAnim) animate(page);
      highlight(this.tocLinksPaginated); highlight(this.tocLinkAll);
      this.tocLinksPaginated[index]?.parentElement.classList.add('active');
      each('.tocNav', element => { element.style.display = 'block'; });
      each('.tocReturnAll', element => { element.style.display = 'none'; });
      each('.tocPrev', element => element.classList.toggle('tocNoPrevNext', !this.options.wrap && index === 0));
      each('.tocNext', element => element.classList.toggle('tocNoPrevNext', !this.options.wrap && index === this.pages.length - 1));
      each('.tocPrevNextCnt', element => { element.textContent = '[' + (index + 1) + '/' + this.pages.length + ']'; });
      return false;
    };
    this.showall = link => {
      each('.tocNav', element => { element.style.display = 'none'; });
      each('.tocReturnAll', element => { element.style.display = ''; });
      this.pages.forEach(page => { page.classList.add('active'); animate(page); });
      highlight(this.tocLinksPaginated); highlight(this.tocLinkAll);
      link?.parentElement.classList.add('active');
      this.currentPage = link;
      return false;
    };
    this.next = () => {
      const index = this.currentIndex + 1;
      return this.activate(this.pages[index < this.pages.length ? index : (this.options.wrap ? 0 : -1)]);
    };
    this.previous = () => {
      const index = this.currentIndex - 1;
      return this.activate(this.pages[index >= 0 ? index : (this.options.wrap ? this.pages.length - 1 : -1)]);
    };
    this.tocLinksScrolled.forEach(link => link.addEventListener('click', event => {
      const target = document.getElementById(decodeURIComponent(link.hash.slice(1)));
      if (!target) return;
      event.preventDefault();
      target.scrollIntoView({behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth'});
      history.replaceState(null, '', link.hash);
      highlight(this.tocLinksScrolled); link.parentElement.classList.add('active');
    }));
    this.tocLinksPaginated.forEach((link, index) => link.addEventListener('click', event => {
      event.preventDefault(); this.activate(this.pages[index]);
    }));
    this.tocLinkAll.forEach(link => link.addEventListener('click', event => { event.preventDefault(); this.showall(link); }));
    let initial = this.options.activateOnLoad;
    if (initial === 'hash') {
      if (location.hash === '#showall') { this.showall(this.tocLinkAll[0]); return; }
      let id;
      try { id = decodeURIComponent(location.hash.slice(1)); } catch (error) { id = ''; }
      const index = this.pages.findIndex(page => page.id === id);
      initial = index >= 0 ? index : this.currentIndex;
    }
    if (initial !== 'none') {
      if (this.tocLinksPaginated.length || (!this.tocLinksScrolled.length && this.pages.some(page => !page.classList.contains('active')))) {
        // Preserve server-rendered ?showall=1 instead of hiding pages on initialization.
        if (new URLSearchParams(location.search).get('showall') !== '1') this.activate(this.pages[initial], true);
      }
      this.tocLinksScrolled[initial]?.parentElement.classList.add('active');
    }
  }
  window.FlexiBreak = FlexiBreak;
  function init() { window.flexibreak = new FlexiBreak('articleTOC'); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, {once: true});
  else init();
})();
