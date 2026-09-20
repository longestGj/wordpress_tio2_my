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

// Keep long schema-driven editors easy to scan without changing submitted values.
document.querySelectorAll('.tio2-editor-group').forEach(group => {
  group.addEventListener('toggle', () => group.dataset.open = String(group.open));
  group.dataset.open = String(group.open);
});
