<?php defined('ABSPATH') || exit; ?>
<footer class="global-footer">
  <div class="footer-grid">
    <div class="footer-brand"><div class="footer-logo"><?php tio2_logo('footer'); ?></div><p><?php echo esc_html(tio2_field('footer.description')); ?></p></div>
    <?php foreach([4,3,1] as $i=>$count): ?>
    <div class="footer-col"><h2><?php echo esc_html(tio2_field("footer.column.$i.title")); ?></h2>
      <?php for($j=0;$j<$count;$j++): ?><a <?php echo $i===2?'class="footer-rfq" ':''; ?>href="<?php echo esc_url(tio2_field("footer.column.$i.link.$j.href")); ?>"><?php echo esc_html(tio2_field("footer.column.$i.link.$j.label")); ?></a><?php endfor; ?>
    </div><?php endforeach; ?>
  </div>
  <nav class="legal-utilities" aria-label="Legal and privacy navigation">
    <?php for($i=0;$i<3;$i++): ?><a href="<?php echo esc_url(tio2_field("footer.legal.$i.href")); ?>"><?php echo esc_html(tio2_field("footer.legal.$i.label")); ?></a><?php endfor; ?>
    <button type="button" class="cookie-settings" aria-haspopup="dialog" aria-controls="cookie-dialog"><?php echo esc_html(tio2_field('footer.cookie-label')); ?></button>
  </nav>
  <p class="copyright"><?php echo esc_html(tio2_field('footer.copyright')); ?></p>
</footer>
<dialog id="cookie-dialog" class="cookie-dialog" aria-labelledby="cookie-title" aria-describedby="cookie-description">
  <h2 id="cookie-title">Cookie settings</h2>
  <p id="cookie-description">No optional Analytics or advertising technology is currently active on this site. Necessary functions may use browser storage to operate the site and remember an available privacy setting.</p>
  <div class="cookie-actions"><button type="button" class="primary cookie-close">Close</button><a class="secondary" href="/cookie-policy/">Read Cookie Policy</a></div>
</dialog>
<?php wp_footer(); ?></body></html>
