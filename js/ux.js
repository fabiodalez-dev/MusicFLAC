// Global UX enhancements powered by GSAP
// Safe to include on any page (frontend + admin)

(function(){
  function ready(fn){ if(document.readyState!=='loading'){ fn(); } else { document.addEventListener('DOMContentLoaded', fn); } }

  ready(function(){
    if (!window.gsap) return; // GSAP not loaded

    // Basic fade-in for page container
    var containers = document.querySelectorAll('.fade-in, main, .card');
    if (containers.length) {
      gsap.from(containers, { opacity: 0, y: 16, duration: 0.6, stagger: 0.06, ease: 'power2.out' });
    }

    // Header brand + nav
    var header = document.querySelector('header');
    if (header) {
      gsap.from(header, { opacity: 0, y: -20, duration: 0.5, ease: 'power2.out' });
    }

    // Animated buttons hover micro-interaction
    var buttons = document.querySelectorAll('.animated-btn, button');
    buttons.forEach(function(btn){
      btn.addEventListener('mouseenter', function(){
        gsap.to(btn, { y: -2, scale: 1.01, duration: 0.15, ease: 'power1.out' });
      });
      btn.addEventListener('mouseleave', function(){
        gsap.to(btn, { y: 0, scale: 1.0, duration: 0.2, ease: 'power1.inOut' });
      });
      btn.addEventListener('mousedown', function(){ gsap.to(btn, { scale: 0.99, duration: 0.08 }); });
      btn.addEventListener('mouseup', function(){ gsap.to(btn, { scale: 1.01, duration: 0.08 }); });
    });

    // Index services bouncing entrance
    var svc = document.querySelectorAll('.service-label .service-content');
    if (svc.length) {
      gsap.fromTo(svc,
        { opacity: 0, y: 10 },
        { opacity: 1, y: 0, duration: 0.5, stagger: 0.05, ease: 'back.out(1.7)', clearProps: 'transform' }
      );
    }

    // ScrollReveal for cards (if ScrollTrigger available)
    if (gsap.ScrollTrigger) {
      gsap.utils.toArray('.bg-gray-800, .card').forEach(function(el){
        gsap.from(el, {
          scrollTrigger: { trigger: el, start: 'top 85%' },
          opacity: 0,
          y: 12,
          duration: 0.5,
          ease: 'power2.out'
        });
      });
    }
  });
})();
