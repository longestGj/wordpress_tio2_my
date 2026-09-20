document.querySelectorAll('.tio2-media').forEach(button => {
  button.addEventListener('click', () => {
    const picker = wp.media({title: 'Choose hero image', library: {type: 'image'}, multiple: false});
    picker.on('select', () => {
      const image = picker.state().get('selection').first().toJSON();
      document.getElementById(button.dataset.target).value = image.id;
      document.querySelector(`[data-preview="${button.dataset.target}"]`).src = image.url;
    });
    picker.open();
  });
});
