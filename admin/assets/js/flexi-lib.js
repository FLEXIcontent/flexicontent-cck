/** Reorder manager rows with the same Sortable library used by Choices fields. */
function updateDragImg(index) {
  const rows = document.querySelectorAll('#sortable_fcitems > tr'), row = rows[index];
  if (!row) return;
  const group = el => el?.querySelector('input[name="ord_grp[]"]')?.value;
  const value = group(row), up = index > 0 && group(rows[index - 1]) === value;
  const down = index + 1 < rows.length && group(rows[index + 1]) === value;
  const name = up && down ? 'both' : up ? 'uponly' : down ? 'downonly' : 'none';
  row.querySelectorAll('div.fc_drag_handle').forEach(handle => {
    handle.classList.remove('fc_drag_handle_both', 'fc_drag_handle_uponly', 'fc_drag_handle_downonly', 'fc_drag_handle_none');
    handle.classList.add('fc_drag_handle_' + name);
  });
}

FlexiForm.ready(() => {
  const list = document.getElementById('sortable_fcitems');
  if (!list || typeof Sortable === 'undefined' || Sortable.get(list)) return;
  let previous = [], values = [];
  Sortable.create(list, {
    handle: 'div.fc_drag_handle', draggable: 'tr', animation: 100,
    onStart() {
      previous = Array.from(list.children);
      values = previous.map(row => row.querySelector('input[name="order[]"]')?.value);
    },
    onEnd(event) {
      if (event.oldIndex === event.newIndex) return;
      const group = row => row?.querySelector('input[name="ord_grp[]"]')?.value;
      const target = previous[event.newIndex];
      if (group(target) !== group(event.item)) {
        previous.forEach(row => list.append(row));
        if (window.move_within_ordering_groups_limits) alert(window.move_within_ordering_groups_limits);
        return;
      }
      Array.from(list.children).forEach((row, index) => {
        const order = row.querySelector('input[name="order[]"]');
        if (order && values[index] !== undefined) order.value = values[index];
        updateDragImg(index);
      });
      list.dispatchEvent(new CustomEvent('flexicontent:order-changed', {bubbles: true}));
    }
  });
});
