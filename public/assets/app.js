document.querySelectorAll('form[data-confirm]').forEach(form => {
  form.addEventListener('submit', event => {
    if (!window.confirm(form.dataset.confirm)) event.preventDefault();
  });
});
const comparisons = [...document.querySelectorAll('input[form="compare-form"]')];
comparisons.forEach(input => input.addEventListener('change', () => {
  const selected = comparisons.filter(item => item.checked);
  if (selected.length > 4) { input.checked = false; window.alert('Compare up to four products at a time.'); }
  const count = document.querySelector('[data-compare-count]');
  if (count) count.textContent = comparisons.filter(item => item.checked).length;
}));
document.querySelectorAll('[data-print]').forEach(button => button.addEventListener('click', () => window.print()));
